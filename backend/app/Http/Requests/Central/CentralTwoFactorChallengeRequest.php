<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/super-admin/auth/two-factor-challenge (IDEN-1.12). Public by design (the
 * challenge id proves the password step); gated by EnsureCentralContext and the
 * `central-two-factor` limiter. Exactly one of `code` (TOTP) or `recovery_code`.
 */
final class CentralTwoFactorChallengeRequest extends FormRequest
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
            'challenge_id' => ['required', 'string', 'max:128'],
            'code' => ['nullable', 'required_without:recovery_code', 'prohibits:recovery_code', 'string', 'max:16'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:64'],
        ];
    }
}
