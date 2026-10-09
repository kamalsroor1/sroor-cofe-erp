<?php

declare(strict_types=1);

namespace App\Actions\Central\TwoFactor;

use App\Actions\Central\Data\CentralAuthSettings;
use App\Actions\Central\Exceptions\CentralAuthException;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;

/**
 * GET /api/v1/super-admin/auth/two-factor/recovery-codes (IDEN-1.12, setup or full token):
 * the operator's current recovery codes (decrypted by Fortify). 409 when no 2FA secret
 * exists yet (call /two-factor/enable first).
 *
 * Security (W2 batch 2):
 *  - an operator whose 2FA is CONFIRMED needs a recent second-factor proof on the current
 *    token (same rule as RequireRecentTwoFactor), else 403 central_auth.step_up_required, so a
 *    stolen full token alone cannot read the codes that bypass TOTP;
 *  - during enrollment (2FA not confirmed yet) the codes stay readable with the setup token;
 *  - every successful read is audited `recovery_codes_viewed` synchronously (recordStrict):
 *    if the audit row cannot be written, the codes are not returned.
 */
final class GetCentralRecoveryCodesAction
{
    public function __construct(
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    /**
     * @return list<string>
     */
    public function execute(CentralUser $user): array
    {
        $user->refresh();

        if (empty($user->two_factor_secret) || empty($user->two_factor_recovery_codes)) {
            throw CentralAuthException::notEnabled();
        }

        $confirmed = $user->two_factor_confirmed_at !== null;
        // AuthenticateCentral always attaches the CentralPersonalAccessToken it resolved.
        $token = $user->currentAccessToken();

        if ($confirmed && ! $token->twoFactorVerifiedWithin(CentralAuthSettings::stepUpTtlMinutes())) {
            throw CentralAuthException::stepUpRequired();
        }

        $codes = array_values(array_filter($user->recoveryCodes(), 'is_string'));

        $this->auditLogger->recordStrict(
            CentralAuditEvent::RecoveryCodesViewed,
            [
                'phase' => $confirmed ? 'confirmed' : 'enrollment',
                'session_id' => (int) $token->getKey(),
                'codes_count' => count($codes),
            ],
            actor: $user,
            subject: $user,
        );

        return $codes;
    }
}
