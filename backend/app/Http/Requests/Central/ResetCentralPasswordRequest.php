<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * POST /api/v1/super-admin/auth/reset-password (IDEN-1.12). Public by design (the emailed
 * token is the credential); gated by EnsureCentralContext and `central-password-reset`.
 * Operator passwords: at least 12 characters, like central:create-super-admin.
 */
final class ResetCentralPasswordRequest extends FormRequest
{
    public const MIN_PASSWORD_LENGTH = 12;

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
            'password' => ['required', 'string', 'confirmed', 'max:255', Password::min(self::MIN_PASSWORD_LENGTH)],
        ];
    }
}
