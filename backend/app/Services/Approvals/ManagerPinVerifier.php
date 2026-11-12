<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Exceptions\ManagerPinLockedException;
use App\Jobs\SendManagerPinLockedAlertJob;
use App\Models\User;
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
 * generic message so nothing is leaked about which condition failed.
 *
 * Two layers of throttling:
 *  - short buckets (MAX_ATTEMPTS_PER_APPROVER / MAX_ATTEMPTS_PER_ACTOR per DECAY_SECONDS): slow a
 *    burst down; a right PIN clears the approver's short bucket (429 override_throttled);
 *  - SEC-21 daily lockout: MAX_DAILY_FAILURES_PER_APPROVER wrong PINs for one approver on one
 *    tenant calendar day lock that approver's PIN approvals until the next day or until the PIN
 *    is changed (resetDailyLockout()). A right PIN does NOT reset this counter (otherwise a
 *    manager approving regularly would re-open the guessing budget every time). When the lock
 *    trips, one Telegram alert is queued (after commit) to the shop's own chat.
 *
 * Callers MUST call verify() outside (before) any DB transaction: the cache store may live on the
 * tenant connection, and a limiter hit written inside a transaction that is then rolled back is
 * lost (see CurrentPasswordVerifier). Every current caller runs its preflight before the write
 * transaction.
 */
final class ManagerPinVerifier
{
    public const MAX_ATTEMPTS_PER_APPROVER = 5;

    public const MAX_ATTEMPTS_PER_ACTOR = 10;

    public const DECAY_SECONDS = 300;

    /** SEC-21 (owner decision 2026-11-12): wrong PINs per approver per day before the lockout. */
    public const MAX_DAILY_FAILURES_PER_APPROVER = 20;

    public static function approverKey(int $approverId): string
    {
        return 'manager-pin:'.RateLimitKey::scope().':approver:'.$approverId;
    }

    /** SEC-21: the approver's failure counter for one tenant calendar day (Y-m-d, today by default). */
    public static function dailyKey(int $approverId, ?string $date = null): string
    {
        $date ??= app(TenantClock::class)->now()->toDateString();

        return 'manager-pin:'.RateLimitKey::scope().':approver-day:'.$approverId.':'.$date;
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
        $scope = RateLimitKey::scope();
        $approverKey = self::approverKey($approverId);
        $dailyKey = self::dailyKey($approverId);
        $actorKey = 'manager-pin:'.$scope.':actor:'.($actor instanceof User ? (string) $actor->id : 'guest');

        // SEC-21: checked first, so the right PIN is refused too while the approver is locked.
        if (RateLimiter::tooManyAttempts($dailyKey, self::MAX_DAILY_FAILURES_PER_APPROVER)) {
            throw ManagerPinLockedException::untilTomorrow($this->secondsUntilTomorrow());
        }

        if (RateLimiter::tooManyAttempts($approverKey, self::MAX_ATTEMPTS_PER_APPROVER)
            || RateLimiter::tooManyAttempts($actorKey, self::MAX_ATTEMPTS_PER_ACTOR)) {
            $wait = max(RateLimiter::availableIn($approverKey), RateLimiter::availableIn($actorKey), 1);

            throw new ThrottleRequestsException(__('pos.override_throttled'), null, ['Retry-After' => (string) $wait]);
        }

        $approver = User::query()->whereKey($approverId)->where('is_active', true)->first();

        $valid = $approver instanceof User
            && $authorize($approver)
            && ($storeId === null || ActiveStore::canAccess($approver, $storeId))
            && is_string($approver->pos_pin)
            && $approver->pos_pin !== ''
            && Hash::check($pin, $approver->pos_pin);

        if (! $valid) {
            RateLimiter::hit($approverKey, self::DECAY_SECONDS);
            RateLimiter::hit($actorKey, self::DECAY_SECONDS);
            $failures = RateLimiter::hit($dailyKey, $this->secondsUntilTomorrow() + 60);

            // Exactly one request reaches the threshold (atomic increment): one alert per lockout.
            if ($failures === self::MAX_DAILY_FAILURES_PER_APPROVER && $approver instanceof User) {
                $this->queueLockAlert($approver, $actor);
            }

            throw ValidationException::withMessages([
                'override.pin' => __('pos.override_invalid'),
            ]);
        }

        RateLimiter::clear($approverKey);

        return $approver;
    }

    private function secondsUntilTomorrow(): int
    {
        $now = app(TenantClock::class)->now();

        return max(1, (int) ceil($now->diffInSeconds($now->addDay()->startOfDay(), true)));
    }

    /** Never throws: an alert that cannot be queued must not change the answer of the request. */
    private function queueLockAlert(User $approver, ?User $actor): void
    {
        try {
            SendManagerPinLockedAlertJob::dispatch(
                (int) $approver->id,
                $actor instanceof User ? (int) $actor->id : null,
                self::MAX_DAILY_FAILURES_PER_APPROVER,
            )->afterCommit();
        } catch (Throwable $e) {
            Log::warning('Manager PIN lock alert could not be queued: '.$e::class);
        }
    }
}
