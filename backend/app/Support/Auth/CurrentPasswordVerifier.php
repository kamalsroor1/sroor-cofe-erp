<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Actions\Auth\DispatchAccountLockAlertAction;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\RateLimitKey;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * SEC-05: checks the "current password" a signed-in user types before changing it. A session
 * token must not become a password-guessing oracle, so wrong answers share the login lockout
 * budget (rate_limits.tenant_login.account_lockout_attempts per user per
 * account_lockout_minutes). One bucket per user and tenant, shared by PUT /profile and
 * POST /auth/change-password (SELL-01). Wrong or locked answers are a 422 on `current_password`.
 *
 * When the budget is exhausted, the user's account-level login lockout (AccountLockout) is also
 * triggered across all of their login identifiers (phone & email) so login is blocked for the
 * full lockout window, and an existing login lockout also blocks current-password verification.
 *
 * Callers compare inside their write transaction (on the locked row) but MUST call failed() /
 * passed() AFTER it has finished: the cache store may live on the same database connection
 * (database store on the tenant connection), and a hit written inside a transaction that is then
 * rolled back would be lost, so the lockout would never trip (SELL-01 security review).
 */
final class CurrentPasswordVerifier
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly DispatchAccountLockAlertAction $lockAlertAction,
    ) {}

    /** 422 when the bucket or the account login is already locked (checked before any write work). */
    public function assertNotLocked(User $user): void
    {
        $accountLockedUntil = AccountLockout::lockedUntilForIdentifiers($user->phone, $user->email);
        if ($accountLockedUntil !== null) {
            $seconds = max(1, $accountLockedUntil - now()->getTimestamp());

            throw ValidationException::withMessages([
                'current_password' => __('auth.account_locked', [
                    'minutes' => max(1, (int) ceil($seconds / 60)),
                ]),
            ]);
        }

        $key = $this->key($user);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts())) {
            $seconds = max(1, (int) RateLimiter::availableIn($key));

            throw ValidationException::withMessages([
                'current_password' => __('auth.account_locked', [
                    'minutes' => max(1, (int) ceil($seconds / 60)),
                ]),
            ]);
        }
    }

    public function matches(User $user, #[SensitiveParameter] string $currentPassword): bool
    {
        return Hash::check($currentPassword, $user->password);
    }

    /** Count the wrong answer, lock login on threshold, and refuse with 422. Call outside (after) any transaction. */
    public function failed(User $user): never
    {
        $decay = AccountLockout::lockoutMinutes() * 60;
        $hits = (int) RateLimiter::hit($this->key($user), $decay);

        if ($hits >= $this->maxAttempts()) {
            $wasLocked = AccountLockout::isLockedForIdentifiers($user->phone, $user->email);
            $locked = AccountLockout::lockForIdentifiers($user->phone, $user->email);

            if ($locked && ! $wasLocked && tenancy()->initialized) {
                $rawLogin = (string) ($user->phone ?: ($user->email ?: $user->getKey()));
                $identifier = RateLimitKey::identifier($rawLogin);

                $this->activityLogService->log(
                    module: 'auth',
                    action: 'account_locked',
                    description: __('auth.activity_account_locked', ['login' => $identifier]),
                    subject: $user,
                    userId: (int) $user->getKey(),
                    properties: [
                        'login' => $identifier,
                        'ip' => request()->ip(),
                        'source' => 'current_password',
                    ],
                );
                $this->lockAlertAction->execute($rawLogin, request()->ip());
            }
        }

        throw ValidationException::withMessages([
            'current_password' => __('auth.current_password_incorrect'),
        ]);
    }

    /** A right answer (or admin unlock/reset) resets the bucket. Call after the transaction committed. */
    public function passed(User $user): void
    {
        RateLimiter::clear($this->key($user));
    }

    public function key(User $user): string
    {
        return 'profile-current-password|'.RateLimitKey::scope().'|'.$user->getKey();
    }

    private function maxAttempts(): int
    {
        return AccountLockout::maxAttempts();
    }
}
