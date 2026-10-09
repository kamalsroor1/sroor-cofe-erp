<?php

declare(strict_types=1);

namespace App\Actions\Central\TwoFactor;

use App\Actions\Central\Data\CentralSignInResult;
use App\Actions\Central\Data\TwoFactorChallengeDTO;
use App\Actions\Central\IssueCentralTokenAction;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/v1/super-admin/auth/two-factor-challenge (IDEN-1.12): exchanges a login
 * challenge + TOTP / recovery code for the full `central:*` token.
 *
 * - The challenge is pulled from the cache first (single use, even under concurrency).
 *   Unknown / used / expired challenge, or an operator deactivated meanwhile: 422 on
 *   `challenge_id`, audited `two_factor_challenge_failed` (recordAttempt).
 * - Wrong code: 422 on `code`, audited; the challenge goes back with attempts + 1 and is
 *   burnt after central.two_factor_challenge_max_attempts wrong codes.
 * - Success, in one central transaction: full token stamped two_factor_verified_at,
 *   last_login_*, `login_succeeded` (+ `recovery_code_used` when a recovery code was used).
 */
final class CompleteTwoFactorChallengeAction
{
    public function __construct(
        private readonly TwoFactorChallengeStore $challenges,
        private readonly VerifyTwoFactorCodeAction $verifier,
        private readonly IssueCentralTokenAction $issueToken,
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(TwoFactorChallengeDTO $dto, ?string $ipAddress = null): CentralSignInResult
    {
        $challenge = $this->challenges->pull($dto->challengeId);
        $user = $challenge !== null ? CentralUser::query()->find($challenge['user_id']) : null;

        if ($challenge === null || ! $user instanceof CentralUser || ! $user->is_active) {
            $this->auditLogger->recordAttempt(
                CentralAuditEvent::TwoFactorChallengeFailed,
                ['context' => 'login', 'reason' => $challenge === null ? 'invalid_challenge' : 'inactive'],
                subject: $user instanceof CentralUser ? $user : null,
            );

            throw ValidationException::withMessages([
                'challenge_id' => [__('central_auth.two_factor_challenge_invalid')],
            ]);
        }

        $method = $this->verifier->execute($user, $dto->secondFactor);

        if ($method === null) {
            $stillOpen = $this->challenges->release($dto->challengeId, $challenge);

            $this->auditLogger->recordAttempt(
                CentralAuditEvent::TwoFactorChallengeFailed,
                [
                    'context' => 'login',
                    'reason' => 'invalid_code',
                    'method' => $dto->toArray()['method'],
                    'attempt' => $challenge['attempts'] + 1,
                    'challenge_burnt' => ! $stillOpen,
                ],
                actor: $user,
                subject: $user,
            );

            throw ValidationException::withMessages([
                $stillOpen ? 'code' : 'challenge_id' => [__($stillOpen ? 'central_auth.two_factor_invalid' : 'central_auth.two_factor_challenge_invalid')],
            ]);
        }

        return DB::connection($user->getConnectionName())->transaction(function () use ($user, $challenge, $method, $ipAddress): CentralSignInResult {
            $result = $this->issueToken->execute($user, $challenge['device_name'], CentralSignInResult::SCOPE_FULL, $ipAddress);

            $this->auditLogger->record(
                CentralAuditEvent::LoginSucceeded,
                [
                    'device_name' => $challenge['device_name'],
                    'session_scope' => CentralSignInResult::SCOPE_FULL,
                    'two_factor_method' => $method,
                ],
                actor: $user,
                subject: $user,
            );

            if ($method === VerifyTwoFactorCodeAction::METHOD_RECOVERY_CODE) {
                $this->auditLogger->record(
                    CentralAuditEvent::RecoveryCodeUsed,
                    ['context' => 'login'],
                    actor: $user,
                    subject: $user,
                );
            }

            return $result;
        });
    }
}
