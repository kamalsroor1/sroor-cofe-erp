<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\RateLimitKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ApiLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'tenant' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('login') && ! $this->filled('phone') && ! $this->filled('email')) {
                $validator->errors()->add('login', __('auth.failed'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'password.required' => __('auth.password'),
        ];
    }

    public function ensureIsNotRateLimited(): void
    {
        $this->ensureAccountIsNotLocked();

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $this->maxFailedAttempts())) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function hitRateLimit(): void
    {
        RateLimiter::hit($this->throttleKey(), 60);
    }

    public function clearRateLimit(): void
    {
        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->accountFailuresKey());
    }

    /**
     * SEC-05: per-account lockout. Refuses the attempt (even with the right password) while the
     * account is locked. Same 422-on-`login` envelope as the IP failure counter.
     */
    public function ensureAccountIsNotLocked(): void
    {
        $until = Cache::get($this->accountLockKey());

        if (! is_int($until) || $until <= now()->getTimestamp()) {
            return;
        }

        $seconds = $until - now()->getTimestamp();

        throw ValidationException::withMessages([
            'login' => trans('auth.account_locked', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * SEC-05: count a failed attempt against the ACCOUNT (any IP; unknown logins count too, so
     * lockout behaviour reveals nothing about which accounts exist). Returns true exactly when
     * this failure triggers the lockout.
     */
    public function hitAccountFailure(): bool
    {
        $decay = $this->lockoutMinutes() * 60;
        RateLimiter::hit($this->accountFailuresKey(), $decay);

        if (RateLimiter::attempts($this->accountFailuresKey()) < $this->lockoutAttempts()) {
            return false;
        }

        Cache::put($this->accountLockKey(), now()->getTimestamp() + $decay, $decay);
        RateLimiter::clear($this->accountFailuresKey());

        return true;
    }

    public function accountIdentifier(): string
    {
        return RateLimitKey::identifier($this->input('login') ?? $this->input('phone') ?? $this->input('email'));
    }

    private function accountFailuresKey(): string
    {
        return 'api-login-account|'.RateLimitKey::scope().'|'.$this->accountIdentifier();
    }

    private function accountLockKey(): string
    {
        return 'api-login-account-lock|'.RateLimitKey::scope().'|'.$this->accountIdentifier();
    }

    private function lockoutAttempts(): int
    {
        $value = config('rate_limits.tenant_login.account_lockout_attempts', 5);

        return max(1, is_numeric($value) ? (int) $value : 5);
    }

    private function lockoutMinutes(): int
    {
        $value = config('rate_limits.tenant_login.account_lockout_minutes', 15);

        return max(1, is_numeric($value) ? (int) $value : 15);
    }

    /**
     * IDEN-4.6: failure counter key = tenant + login + IP. The cache store is shared by all
     * tenants, so without the tenant scope six failures for a login in one shop would lock
     * the same login string in every other shop.
     */
    public function throttleKey(): string
    {
        $identifier = RateLimitKey::identifier($this->input('login') ?? $this->input('phone') ?? $this->input('email'));

        return 'api-login|'.RateLimitKey::scope().'|'.$identifier.'|'.$this->ip();
    }

    private function maxFailedAttempts(): int
    {
        $value = config('rate_limits.tenant_login.max_failed_attempts', 6);

        return max(1, is_numeric($value) ? (int) $value : 6);
    }
}
