<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Actions\Central\Data\TwoFactorSetupData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * POST /api/v1/super-admin/auth/two-factor/enable (IDEN-1.12): what the authenticator app
 * needs. Returned only to the operator who owns the secret, before confirmation.
 *
 * @property TwoFactorSetupData $resource
 */
final class TwoFactorSetupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'secret' => $this->resource->secret,
            'otpauth_url' => $this->resource->otpauthUrl,
            'qr_code_svg' => $this->resource->qrCodeSvg,
        ];
    }
}
