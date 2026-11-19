<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SIGN-15: POST /api/v1/auth/reset-password on a shop host. Public by design (the emailed token is
 * the credential); throttled by `tenant-password-reset`. The new password follows the SEC-05 policy
 * (PasswordPolicy) and must be confirmed. A bad, used or expired token is a 422 from the Action.
 */
final class ResetTenantPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255', PasswordPolicy::rule(), 'confirmed'],
        ];
    }
}
