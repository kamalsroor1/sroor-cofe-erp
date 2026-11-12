<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Exceptions\ManagerPinLockedException;
use App\Jobs\SendManagerPinLockedAlertJob;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\ActiveStore;
use App\Support\RateLimitKey;
use App\Support\TenantClock;
use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;
use Throwable;

/**
 * The single manager-PIN verifier shared by every "a manager approves this" flow
 * (below-floor sale price, over-cap discount, cancelling an invoice with cash payments, a sales
 * return past its window) and by the idle screen unlock (SET-02, the user as their own approver).
 *
 * One limiter key namespace (`manager-pin:{tenant scope}:approver|actor:{id}`) so that wrong PINs
 * guessed through one flow count against every other flow, and a tenant never shares a bucket
 * with another tenant. Callers decide WHAT the approver must be allowed to do via $authorize.
 * Every failure (unknown user / not allowed / no store access / no PIN / wrong PIN) shares one
 * generic message, and the same amount of work (one bcrypt check, against a dummy hash when a
 * precondition fails), so neither the body nor the response time tells which condition failed.
 *
 * Two layers of throttling:
 *  - short buckets (MAX_ATTEMPTS_PER_APPROVER / MAX_ATTEMPTS_PER_ACTOR per DECAY_SECONDS): slow a
 *    burst down; a right PIN clears the approver's short bucket (429 override_throttled);
 *  - SEC-21 daily lockout: MAX_DAILY_FAILURES_PER_APPROVER wrong PINs for one approver on one
 *    tenant BUSINESS day (TenantClock, with the shop's day cutoff) lock that approver's PIN
 *    approvals until the next business day or until the PIN is changed (resetDailyLockout()).
 *    A right PIN does NOT reset this counter. When the lock trips, one Telegram alert is queued
 *    (after commit) to the shop's own chat.
 *
 * Atomic (security review of SEC-21): every attempt RESERVES its slot in each bucket with an atomic
 * increment BEFORE the PIN is checked, and is refused when the reserved count is over the cap. N
 * concurrent requests therefore can never test more than the cap, and exactly one failed attempt
 * reserves the MAX_DAILY_FAILURES_PER_APPROVER-th daily slot, so the alert fires exactly once. A
 * right PIN gives its daily and actor reservations back; a refused (throttled) attempt gives its
 * daily reservation back, since no PIN was tested.
 *
 * Every wrong PIN is activity-logged (module `auth`, action `manager_pin_failed`) with the acting
 * user and the approver, never the PIN, so the owner can see who burned the budget.
 *
 * Callers MUST call verify() outside (before) any DB transaction: the cache store may live on the
 * tenant connection, and a limiter hit (or the activity log row) written inside a transaction that
 * is then rolled back is lost (see CurrentPasswordVerifier). Every current caller runs its
 * preflight before the write transaction.
 */
final class ManagerPinVerifier
{
    public const MAX_ATTEMPTS_PER_APPROVER = 5;

    public const MAX_ATTEMPTS_PER_ACTOR = 10;

    public const DECAY_SECONDS = 300;

    /** SEC-21 (owner decision 2026-11-12): wrong PINs per approver per business day before the lockout. */
    public const MAX_DAILY_FAILURES_PER_APPROVER = 20;

    public const FAILED_ACTION = 'manager_pin_failed';

    /** Per-process dummy bcrypt hash, so a failed precondition costs the same as a wrong PIN. */
    private static ?string $dummyHash = null;

    public static function approverKey(int $approverId): string
    {
        return 'manager-pin:'.RateLimitKey::scope().':approver:'.$approverId;
    }

    public static function actorKey(?User $actor): string
    {
        return 'manager-pin:'.RateLimitKey::scope().':actor:'.($actor instanceof User ? (string) $actor->id : 'guest');
    }

    /** SEC-21: the approver's failure counter for one tenant business day (Y-m-d, today by default). */
    public static function dailyKey(int $approverId, ?string $businessDate = null): string
    {
        $businessDate ??= app(TenantClock::class)->businessDate();

        return 'manager-pin:'.RateLimitKey::scope().':approver-day:'.$approverId.':'.$businessDate;
    }

    /** SEC-21: lift today's lockout of an approver (their PIN was changed or removed). */
    public static function resetDailyLockout(int $approverId): void
    {
        RateLimiter::clear(self::dailyKey($approverId));
        RateLimiter::clear(self::approverKey($approverId));
    }

    /**
     * @param  Closure(User): bool  $authorize  whether this approver may approve the requested action
     *
     * @throws ValidationException
     * @throws ThrottleRequestsException
     */
    public function verify(int $approverId, #[SensitiveParameter] string $pin, ?User $actor, ?int $storeId, Closure $authorize): User
    {
        $clock = app(TenantClock::class);
        $businessDate = $clock->businessDate();
        $approverKey = self::approverKey($approverId);
        $dailyKey = self::dailyKey($approverId, $businessDate);
        $actorKey = self::actorKey($actor);
        $dailyTtl = $this->secondsUntilNextBusinessDay($clock, $businessDate) + 60;

        // Cheap pre-check: an approver locked for the day costs no reservation at all.
        if (RateLimiter::attempts($dailyKey) >= self::MAX_DAILY_FAILURES_PER_APPROVER) {
            throw ManagerPinLockedException::untilTomorrow($dailyTtl - 60);
        }

        // Reserve atomically BEFORE testing the PIN.
        $daily = RateLimiter::increment($dailyKey, $dailyTtl);
        if ($daily > self::MAX_DAILY_FAILURES_PER_APPROVER) {
            throw ManagerPinLockedException::untilTomorrow($dailyTtl - 60);
        }

        $perApprover = RateLimiter::increment($approverKey, self::DECAY_SECONDS);
        $perActor = RateLimiter::increment($actorKey, self::DECAY_SECONDS);
        if ($perApprover > self::MAX_ATTEMPTS_PER_APPROVER || $perActor > self::MAX_ATTEMPTS_PER_ACTOR) {
            RateLimiter::decrement($dailyKey, $dailyTtl); // no PIN was tested
            $wait = max(RateLimiter::availableIn($approverKey), RateLimiter::availableIn($actorKey), 1);

            throw new ThrottleRequestsException(__('pos.override_throttled'), null, ['Retry-After' => (string) $wait]);
        }

        $approver = User::query()->whereKey($approverId)->where('is_active', true)->first();

        $eligible = $approver instanceof User
            && $authorize($approver)
            && ($storeId === null || ActiveStore::canAccess($approver, $storeId))
            && is_string($approver->pos_pin)
            && $approver->pos_pin !== '';

        // Constant work: always exactly one bcrypt check.
        $matches = Hash::check($pin, $eligible ? (string) $approver->pos_pin : self::dummyHash());

        if (! $eligible || ! $matches) {
            $this->recordFailure($approverId, $approver, $actor, $storeId, $daily, $businessDate);

            throw ValidationException::withMessages([
                'override.pin' => __('pos.override_invalid'),
            ]);
        }

        // A right PIN: give back the daily and actor reservations, clear the short approver bucket.
        RateLimiter::decrement($dailyKey, $dailyTtl);
        RateLimiter::decrement($actorKey, self::DECAY_SECONDS);
        RateLimiter::clear($approverKey);

        return $approver;
    }

    private function recordFailure(int $approverId, ?User $approver, ?User $actor, ?int $storeId, int $daily, string $businessDate): void
    {
        try {
            app(ActivityLogService::class)->log(
                module: 'auth',
                action: self::FAILED_ACTION,
                description: __('pos.activity_manager_pin_failed', [
                    'approver' => $approver instanceof User ? $approver->name : '#'.$approverId,
                    'actor' => $actor instanceof User ? $actor->name : '-',
                ]),
                subject: $approver,
                properties: ['approver_id' => $approverId, 'failures_today' => $daily, 'business_date' => $businessDate],
                userId: $actor instanceof User ? (int) $actor->id : null,
                storeId: $storeId,
            );
        } catch (Throwable $e) {
            // The lockout must not depend on the audit trail.
            Log::warning('Manager PIN failure could not be activity-logged: '.$e::class);
        }

        // Exactly one failed attempt holds the MAX-th daily reservation: one alert per lockout.
        if ($daily === self::MAX_DAILY_FAILURES_PER_APPROVER && $approver instanceof User) {
            try {
                SendManagerPinLockedAlertJob::dispatch(
                    (int) $approver->id,
                    $actor instanceof User ? (int) $actor->id : null,
                    self::MAX_DAILY_FAILURES_PER_APPROVER,
                    $businessDate,
                )->afterCommit();
            } catch (Throwable $e) {
                Log::warning('Manager PIN lock alert could not be queued: '.$e::class);
            }
        }
    }

    private function secondsUntilNextBusinessDay(TenantClock $clock, string $businessDate): int
    {
        $nextStart = $clock->businessDayRange($businessDate)[1];

        return max(1, (int) ceil(now()->diffInSeconds($nextStart, true)));
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(bin2hex(random_bytes(16)));
    }
}
