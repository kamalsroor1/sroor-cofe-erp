<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\RateLimitKey;
use Illuminate\Foundation\Http\FormRequest;
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
