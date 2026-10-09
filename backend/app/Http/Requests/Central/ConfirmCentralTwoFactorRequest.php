<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Models\CentralUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/super-admin/auth/two-factor/confirm (IDEN-1.12): the TOTP code shown by
 * the authenticator app (recovery codes cannot confirm a setup).
 */
final class ConfirmCentralTwoFactorRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:16'],
        ];
    }
}
