<?php

declare(strict_types=1);

namespace App\Actions\Central;

use App\Actions\Central\Data\CentralAuthSettings;
use App\Actions\Central\Data\CentralSignInResult;
use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use Illuminate\Support\Carbon;

/**
 * The only place that mints central tokens for the sign-in flow (IDEN-1.12):
 *
 *  - full (`central:*`, central.token_ttl_minutes): only after a second factor was proven,
 *    so two_factor_verified_at is stamped (step-up is satisfied right after sign-in);
 *  - setup (`central:2fa-setup`, central.two_factor_setup_ttl_minutes): for an operator
 *    without confirmed 2FA; accepted on the 2FA setup endpoints only.
 *
 * Runs inside the caller's central transaction (it does not open one itself).
 */
final class IssueCentralTokenAction
{
    /**
     * @param  list<string>|null  $recoveryCodes  returned once, right after 2FA confirmation
     */
    public function execute(CentralUser $user, string $deviceName, string $scope, ?string $ipAddress = null, ?array $recoveryCodes = null): CentralSignInResult
    {
        if ($scope === CentralSignInResult::SCOPE_TWO_FACTOR_SETUP) {
            $abilities = [CentralAuthSettings::setupAbility()];
            $newToken = $user->createToken($deviceName, $abilities, now()->addMinutes($this->setupTtlMinutes()));

            return CentralSignInResult::setupToken($user, $newToken->plainTextToken, $this->expiresAt($newToken->accessToken), $abilities);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ])->save();

        $abilities = [CentralAuthSettings::fullAbility()];
        $newToken = $user->createToken($deviceName, $abilities);
        $newToken->accessToken->forceFill(['two_factor_verified_at' => now()])->save();

        return CentralSignInResult::fullToken($user, $newToken->plainTextToken, $this->expiresAt($newToken->accessToken), $abilities, $recoveryCodes);
    }

    private function setupTtlMinutes(): int
    {
        $minutes = (int) config('central.two_factor_setup_ttl_minutes', 15);

        return $minutes > 0 ? $minutes : 15;
    }

    private function expiresAt(mixed $accessToken): Carbon
    {
        $expiresAt = $accessToken instanceof CentralPersonalAccessToken ? $accessToken->expires_at : null;

        return $expiresAt instanceof Carbon ? $expiresAt : now();
    }
}
