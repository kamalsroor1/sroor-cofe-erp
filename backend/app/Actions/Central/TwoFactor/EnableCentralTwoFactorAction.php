<?php

declare(strict_types=1);

namespace App\Actions\Central\TwoFactor;

use App\Actions\Central\Data\TwoFactorSetupData;
use App\Actions\Central\Exceptions\CentralAuthException;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;

/**
 * POST /api/v1/super-admin/auth/two-factor/enable (IDEN-1.12, setup or full token).
 *
 * Generates a NEW TOTP secret and recovery codes with Fortify's EnableTwoFactorAuthentication
 * (forced: calling it again before confirmation replaces the pending secret) and returns
 * what the authenticator app needs. 2FA is not active until /two-factor/confirm.
 * Already confirmed: 409 (2FA is mandatory and is never re-keyed through this endpoint).
 */
final class EnableCentralTwoFactorAction
{
    public function __construct(
        private readonly EnableTwoFactorAuthentication $enable,
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(CentralUser $user): TwoFactorSetupData
    {
        return DB::connection($user->getConnectionName())->transaction(function () use ($user): TwoFactorSetupData {
            $locked = CentralUser::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->two_factor_confirmed_at !== null) {
                throw CentralAuthException::alreadyConfirmed();
            }

            ($this->enable)($locked, true);

            $this->auditLogger->record(
                CentralAuditEvent::TwoFactorEnabled,
                [],
                actor: $locked,
                subject: $locked,
            );

            return new TwoFactorSetupData(
                secret: (string) Fortify::currentEncrypter()->decrypt((string) $locked->two_factor_secret),
                otpauthUrl: $locked->twoFactorQrCodeUrl(),
                qrCodeSvg: $locked->twoFactorQrCodeSvg(),
            );
        });
    }
}
