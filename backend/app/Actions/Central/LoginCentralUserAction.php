<?php

declare(strict_types=1);

namespace App\Actions\Central;

use App\Actions\Central\Data\CentralSignInResult;
use App\Actions\Central\Exceptions\CentralAuthException;
use App\Actions\Central\TwoFactor\TwoFactorChallengeStore;
use App\DTOs\Central\CentralLoginDTO;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Signs a platform operator in (IDEN-1.3, IDEN-1.12).
 *
 * - Unknown email, wrong password and inactive account all answer the same 422
 *   (`central_auth.failed`), and an unknown email still pays for one hash check, so the
 *   response does not reveal whether the account exists. Each failure is audited with
 *   recordAttempt() (survives any rollback) and its reason.
 * - Password accepted but `must_reset_password` set (accounts created by
 *   central:migrate-super-admins): 403 `central_auth.password_reset_required`, no token and
 *   no challenge, until ResetCentralPasswordAction clears the flag.
 * - Password accepted, 2FA confirmed (`two_factor_confirmed_at`): NO token. A single-use
 *   challenge id (5 min, only its hash in the central cache) to exchange together with a
 *   TOTP or recovery code at /auth/two-factor-challenge (CompleteTwoFactorChallengeAction).
 * - Password accepted, 2FA NOT confirmed: only a setup token (`central:2fa-setup`, short
 *   TTL) usable on the 2FA enable / confirm / recovery-codes endpoints. A full `central:*`
 *   token is never issued without a proven second factor. Audited `login_succeeded` with
 *   session_scope `two_factor_setup`.
 */
final class LoginCentralUserAction
{
    /**
     * Precomputed bcrypt hash (cost 12) of a random, discarded string. Not a secret: it only
     * makes an unknown email cost exactly one Hash::check, like a wrong password, on every
     * request (a per-process static would add a Hash::make under PHP-FPM).
     */
    private const TIMING_GUARD_HASH = '$2y$12$K8R7NZlDtiQWFE.LmpU2zutU00dgZVUl62cX17tulBGJkYjwh.n1O';

    public function __construct(
        private readonly CentralAuditLogger $auditLogger,
        private readonly TwoFactorChallengeStore $challenges,
        private readonly IssueCentralTokenAction $issueToken,
    ) {}

    public function execute(CentralLoginDTO $dto, ?string $ipAddress = null): CentralSignInResult
    {
        $user = CentralUser::query()->where('email', $dto->email)->first();

        $passwordMatches = Hash::check($dto->password, $user !== null ? $user->password : self::TIMING_GUARD_HASH);

        if ($user === null || ! $passwordMatches || ! $user->is_active) {
            $this->auditFailure($dto, $user, match (true) {
                $user === null => 'unknown_email',
                ! $passwordMatches => 'invalid_password',
                default => 'inactive',
            });

            throw ValidationException::withMessages([
                'email' => [__('central_auth.failed')],
            ]);
        }

        // W2-B3 security: an account moved by central:migrate-super-admins still has the legacy
        // password; nothing (no challenge, no setup token) until the reset flow clears the flag.
        if ((bool) $user->getAttribute('must_reset_password')) {
            $this->auditFailure($dto, $user, 'password_reset_required');

            throw CentralAuthException::passwordResetRequired();
        }

        if ($user->two_factor_confirmed_at !== null && ! empty($user->two_factor_secret)) {
            $challenge = $this->challenges->create((int) $user->getKey(), $dto->deviceName);

            return CentralSignInResult::challenge($challenge['id'], $challenge['expires_at']);
        }

        return DB::connection($user->getConnectionName())->transaction(function () use ($user, $dto, $ipAddress): CentralSignInResult {
            $result = $this->issueToken->execute($user, $dto->deviceName, CentralSignInResult::SCOPE_TWO_FACTOR_SETUP, $ipAddress);

            $this->auditLogger->record(
                CentralAuditEvent::LoginSucceeded,
                ['device_name' => $dto->deviceName, 'session_scope' => CentralSignInResult::SCOPE_TWO_FACTOR_SETUP],
                actor: $user,
                subject: $user,
            );

            return $result;
        });
    }

    private function auditFailure(CentralLoginDTO $dto, ?CentralUser $user, string $reason): void
    {
        $this->auditLogger->recordAttempt(
            CentralAuditEvent::LoginFailed,
            ['email' => $dto->email, 'reason' => $reason],
            subject: $user,
        );
    }
}
