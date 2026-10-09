<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Models\CentralUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/super-admin/auth/step-up (IDEN-1.12): exactly one of `code` (TOTP) or
 * `recovery_code`. AuthenticateCentral has already resolved a full central token.
 */
final class CentralTwoFactorCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof CentralUser;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'required_without:recovery_code', 'prohibits:recovery_code', 'string', 'max:16'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:64'],
        ];
    }
}
