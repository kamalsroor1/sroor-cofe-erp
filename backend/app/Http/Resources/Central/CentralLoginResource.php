<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\DTOs\Central\CentralLoginResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Login payload (IDEN-1.3). With 2FA confirmed only `two_factor_required: true` is
 * returned (no token, no user); otherwise the Bearer token, its expiry and the operator.
 *
 * @property CentralLoginResult $resource
 */
final class CentralLoginResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $result = $this->resource;

        if ($result->twoFactorRequired || $result->user === null) {
            return ['two_factor_required' => true];
        }

        return [
            'two_factor_required' => false,
            'token' => $result->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $result->expiresAt?->toIso8601String(),
            'user' => (new CentralUserResource($result->user->loadMissing('roles.permissions', 'permissions')))->toArray($request),
        ];
    }
}
