<?php

declare(strict_types=1);

namespace App\Actions\Central\TwoFactor;

use App\Actions\Central\Data\CentralAuthSettings;
use App\Actions\Central\Data\TwoFactorCodeDTO;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/v1/super-admin/auth/step-up (IDEN-1.12, full token only).
 *
 * Re-proves the second factor (TOTP or recovery code) and stamps
 * `two_factor_verified_at` on the CURRENT token only, so RequireRecentTwoFactor lets this
 * token through for central.step_up_ttl_minutes. Other sessions of the operator are
 * unaffected. Audited `step_up_confirmed` (+ `recovery_code_used`); a wrong factor is
 * 422 on `code` and audited `two_factor_challenge_failed` (context step_up).
 *
 * @return Carbon the moment the step-up expires
 */
final class ConfirmStepUpAction
{
    public function __construct(
        private readonly VerifyTwoFactorCodeAction $verifier,
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(CentralUser $user, TwoFactorCodeDTO $factor): Carbon
    {
        // AuthenticateCentral always attaches the CentralPersonalAccessToken it resolved.
        $token = $user->currentAccessToken();

        $method = $this->verifier->execute($user, $factor);

        if ($method === null) {
            $this->auditLogger->recordAttempt(
                CentralAuditEvent::TwoFactorChallengeFailed,
                ['context' => 'step_up', 'reason' => 'invalid_code', 'method' => $factor->toArray()['method']],
                actor: $user,
                subject: $user,
            );

            throw ValidationException::withMessages(['code' => [__('central_auth.two_factor_invalid')]]);
        }

        return DB::connection($user->getConnectionName())->transaction(function () use ($user, $token, $method): Carbon {
            $verifiedAt = now();
            $token->forceFill(['two_factor_verified_at' => $verifiedAt])->save();

            $this->auditLogger->record(
                CentralAuditEvent::StepUpConfirmed,
                ['session_id' => (int) $token->getKey(), 'two_factor_method' => $method],
                actor: $user,
                subject: $user,
            );

            if ($method === VerifyTwoFactorCodeAction::METHOD_RECOVERY_CODE) {
                $this->auditLogger->record(
                    CentralAuditEvent::RecoveryCodeUsed,
                    ['context' => 'step_up'],
                    actor: $user,
                    subject: $user,
                );
            }

            return $verifiedAt->copy()->addMinutes(CentralAuthSettings::stepUpTtlMinutes());
        });
    }
}
