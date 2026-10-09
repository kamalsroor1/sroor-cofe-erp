<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/super-admin/auth/forgot-password (IDEN-1.12). Public by design; gated by
 * EnsureCentralContext and the `central-password-reset` limiter.
 */
final class ForgotCentralPasswordRequest extends FormRequest
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
