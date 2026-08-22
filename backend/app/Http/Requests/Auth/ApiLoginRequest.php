<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
            'login'       => ['nullable', 'string'],
            'phone'       => ['nullable', 'string'],
            'email'       => ['nullable', 'string'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'tenant'      => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->filled('login') && !$this->filled('phone') && !$this->filled('email')) {
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
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 6)) {
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

    public function throttleKey(): string
    {
        $identifier = $this->input('login') ?? $this->input('phone') ?? $this->input('email') ?? '';
        return Str::transliterate(Str::lower((string)$identifier) . '|' . $this->ip());
    }
}
