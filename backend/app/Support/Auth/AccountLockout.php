<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Support\RateLimitKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * SEC-05: per-account (tenant + login, any IP) login lockout.
 *
 * Every method takes the RAW login (phone, email or whatever was typed); it is normalised
 * with RateLimitKey::identifier() and scoped with RateLimitKey::scope() (current tenant), so
 * the same login string in two tenants has independent counters and locks. Cache reads/writes
 * only, no database access.
 */
final class AccountLockout
{
    public static function failuresKey(mixed $login): string
    {
        return 'api-login-account|'.RateLimitKey::scope().'|'.RateLimitKey::identifier($login);
    }

    public static function lockKey(mixed $login): string
    {
        return 'api-login-account-lock|'.RateLimitKey::scope().'|'.RateLimitKey::identifier($login);
    }

    /** Unix timestamp the lock ends at, or null when the account is not locked. */
    public static function lockedUntil(mixed $login): ?int
    {
        $until = self::parseTimestamp(Cache::get(self::lockKey($login)));

        return $until !== null && $until > now()->getTimestamp() ? $until : null;
    }

    /**
     * Latest lock end across a user's login identifiers (phone and email), null when neither is
     * locked. Blank identifiers are skipped so they can never match the "empty login" key.
     */
    public static function lockedUntilForIdentifiers(?string ...$logins): ?int
    {
        $latest = null;

        foreach (self::usable($logins) as $login) {
            $until = self::lockedUntil($login);
            if ($until !== null && ($latest === null || $until > $latest)) {
                $latest = $until;
            }
        }

        return $latest;
    }

    /**
     * Batch read: one cache round trip for many logins. Returns login => lock end (unix) for the
     * locked ones only; blank logins are skipped.
     *
     * @param  array<int, string|null>  $logins
     * @return array<string, int>
     */
    public static function lockedUntilMany(array $logins): array
    {
        $keys = [];
        foreach (self::usable($logins) as $login) {
            $keys[$login] = self::lockKey($login);
        }

        if ($keys === []) {
            return [];
        }

        $values = Cache::many(array_values($keys));
        $now = now()->getTimestamp();
        $locked = [];

        foreach ($keys as $login => $key) {
            $until = self::parseTimestamp($values[$key] ?? null);
            if ($until !== null && $until > $now) {
                $locked[$login] = $until;
            }
        }

        return $locked;
    }

    /**
     * The two user-payload fields for a lock end (null = not locked).
     *
     * @return array{login_locked: bool, login_locked_until: string|null}
     */
    public static function payload(?int $until): array
    {
        return [
            'login_locked' => $until !== null,
            'login_locked_until' => $until !== null ? Carbon::createFromTimestamp($until)->toIso8601String() : null,
        ];
    }

    public static function isLockedForIdentifiers(?string ...$logins): bool
    {
        return self::lockedUntilForIdentifiers(...$logins) !== null;
    }

    /**
     * Lock every login identifier of an account for the configured lockout window.
     */
    public static function lockForIdentifiers(?string ...$logins): bool
    {
        $usable = self::usable($logins);
        if ($usable === []) {
            return false;
        }

        $decay = self::lockoutSeconds();
        $until = now()->getTimestamp() + $decay;

        foreach ($usable as $login) {
            Cache::put(self::lockKey($login), $until, $decay);
            RateLimiter::clear(self::failuresKey($login));
        }

        return true;
    }

    /** Drop failure counters across all usable login identifiers (successful login). */
    public static function clearFailuresForIdentifiers(?string ...$logins): void
    {
        foreach (self::usable($logins) as $login) {
            self::clearFailures($login);
        }
    }

    /** Admin unlock for every identifier a user can sign in with. Returns true if any was locked. */
    public static function clearForIdentifiers(?string ...$logins): bool
    {
        $wasLocked = false;

        foreach (self::usable($logins) as $login) {
            $wasLocked = self::isLocked($login) || $wasLocked;
            self::clear($login);
        }

        return $wasLocked;
    }

    public static function isLocked(mixed $login): bool
    {
        return self::lockedUntil($login) !== null;
    }

    /**
     * Count a failed attempt. Returns true exactly when this failure triggers the lock.
     */
    public static function hit(mixed $login): bool
    {
        $decay = self::lockoutSeconds();
        $hits = (int) RateLimiter::hit(self::failuresKey($login), $decay);

        if ($hits < self::maxAttempts()) {
            return false;
        }

        Cache::put(self::lockKey($login), now()->getTimestamp() + $decay, $decay);
        RateLimiter::clear(self::failuresKey($login));

        return true;
    }

    /** Drop the failure counter only (successful login). */
    public static function clearFailures(mixed $login): void
    {
        RateLimiter::clear(self::failuresKey($login));
    }

    /** Drop the lock AND the failure counter (admin unlock). */
    public static function clear(mixed $login): void
    {
        Cache::forget(self::lockKey($login));
        RateLimiter::clear(self::failuresKey($login));
    }

    public static function maxAttempts(): int
    {
        $value = config('rate_limits.tenant_login.account_lockout_attempts', 5);

        return max(1, is_numeric($value) ? (int) $value : 5);
    }

    public static function lockoutMinutes(): int
    {
        $value = config('rate_limits.tenant_login.account_lockout_minutes', 15);

        return max(1, is_numeric($value) ? (int) $value : 15);
    }

    /**
     * RedisStore returns unserialized integers as numeric strings, whereas array/database
     * stores preserve PHP ints. Accept both so lockouts work on every cache driver.
     */
    private static function parseTimestamp(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param  array<int, string|null>  $logins
     * @return list<string>
     */
    private static function usable(array $logins): array
    {
        return array_values(array_filter(
            $logins,
            static fn (?string $login): bool => $login !== null && RateLimitKey::identifier($login) !== '',
        ));
    }

    private static function lockoutSeconds(): int
    {
        return self::lockoutMinutes() * 60;
    }
}
