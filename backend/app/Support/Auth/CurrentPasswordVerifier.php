<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
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
 * Callers compare inside their write transaction (on the locked row) but MUST call failed() /
 * passed() AFTER it has finished: the cache store may live on the same database connection
 * (database store on the tenant connection), and a hit written inside a transaction that is then
 * rolled back would be lost, so the lockout would never trip (SELL-01 security review).
 */
final class CurrentPasswordVerifier
{
    /** 422 when the bucket is already exhausted (checked before any write work). */
    public function assertNotLocked(User $user): void
    {
        $key = $this->key($user);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts())) {
            throw ValidationException::withMessages([
                'current_password' => __('auth.account_locked', [
                    'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ]);
        }
    }

    public function matches(User $user, #[SensitiveParameter] string $currentPassword): bool
    {
        return Hash::check($currentPassword, $user->password);
    }

    /** Count the wrong answer and refuse with 422. Call outside (after) any transaction. */
    public function failed(User $user): never
    {
        RateLimiter::hit($this->key($user), max(1, (int) config('rate_limits.tenant_login.account_lockout_minutes', 15)) * 60);

        throw ValidationException::withMessages([
            'current_password' => __('auth.current_password_incorrect'),
        ]);
    }

    /** A right answer resets the bucket. Call after the transaction committed. */
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
        return max(1, (int) config('rate_limits.tenant_login.account_lockout_attempts', 5));
    }
}
