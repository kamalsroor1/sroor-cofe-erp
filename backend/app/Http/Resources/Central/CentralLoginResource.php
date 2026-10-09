<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Actions\Central\Data\CentralSignInResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sign-in payload (IDEN-1.3, IDEN-1.12), shared by /auth/login, /auth/two-factor-challenge
 * and /auth/two-factor/confirm:
 *
 *  - 2FA confirmed, password step only: `two_factor_required: true` + `challenge_id` +
 *    `challenge_expires_at` (no token, no user);
 *  - a token: `two_factor_required: false`, `two_factor_setup_required` (true = the token
 *    only carries `central:2fa-setup`), the Bearer token, its expiry, abilities and the
 *    operator; `recovery_codes` only right after the 2FA confirmation.
 *
 * @property CentralSignInResult $resource
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
            return [
                'two_factor_required' => true,
                'challenge_id' => $result->challengeId,
                'challenge_expires_at' => $result->challengeExpiresAt?->toIso8601String(),
            ];
        }

        $payload = [
            'two_factor_required' => false,
            'two_factor_setup_required' => $result->isSetupToken(),
            'token' => $result->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $result->expiresAt?->toIso8601String(),
            'abilities' => $result->abilities,
            'user' => (new CentralUserResource($result->user->loadMissing('roles.permissions', 'permissions')))->toArray($request),
        ];

        if ($result->recoveryCodes !== null) {
            $payload['recovery_codes'] = $result->recoveryCodes;
        }

        return $payload;
    }
}
