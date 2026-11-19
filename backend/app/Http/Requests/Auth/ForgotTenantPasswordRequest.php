<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SIGN-15: POST /api/v1/auth/forgot-password on a shop host. Public by design; throttled by the
 * `tenant-password-reset` limiter. Only the shape of the email is validated: whether an account
 * exists is never revealed (the answer is always the same 200).
 */
final class ForgotTenantPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
