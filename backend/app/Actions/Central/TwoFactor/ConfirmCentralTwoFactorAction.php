<?php

declare(strict_types=1);

namespace App\Actions\Central\TwoFactor;

use App\Actions\Central\Data\CentralAuthSettings;
use App\Actions\Central\Data\CentralSignInResult;
use App\Actions\Central\Exceptions\CentralAuthException;
use App\Actions\Central\IssueCentralTokenAction;
use App\Enums\CentralAuditEvent;
use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;

/**
 * POST /api/v1/super-admin/auth/two-factor/confirm (IDEN-1.12, setup or full token).
 *
 * Confirms the pending secret with a TOTP code through Fortify's
 * ConfirmTwoFactorAuthentication. On success, in one central transaction:
 * two_factor_confirmed_at is set, every setup-scoped token of the operator is revoked,
 * a full `central:*` token is issued (password + second factor have both been proven)
 * and `two_factor_confirmed` is audited. The response carries the recovery codes once.
 *
 * Wrong code: 422 on `code`, audited `two_factor_challenge_failed` (context confirm).
 * No pending secret: 409. Already confirmed: 409.
 */
final class ConfirmCentralTwoFactorAction
{
    public function __construct(
        private readonly ConfirmTwoFactorAuthentication $confirm,
        private readonly IssueCentralTokenAction $issueToken,
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(CentralUser $user, string $code, ?string $ipAddress = null): CentralSignInResult
    {
        $result = DB::connection($user->getConnectionName())->transaction(function () use ($user, $code, $ipAddress): ?CentralSignInResult {
            $locked = CentralUser::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->two_factor_confirmed_at !== null) {
                throw CentralAuthException::alreadyConfirmed();
            }

            if (empty($locked->two_factor_secret)) {
                throw CentralAuthException::notEnabled();
            }

            try {
                ($this->confirm)($locked, $code);
            } catch (ValidationException) {
                return null;
            }

            $deviceName = $this->currentTokenName($user);
            $this->revokeSetupTokens($locked);

            $recoveryCodes = array_values(array_filter($locked->recoveryCodes(), 'is_string'));

            $issued = $this->issueToken->execute($locked, $deviceName, CentralSignInResult::SCOPE_FULL, $ipAddress, $recoveryCodes);

            $this->auditLogger->record(
                CentralAuditEvent::TwoFactorConfirmed,
                [],
                actor: $locked,
                subject: $locked,
            );

            return $issued;
        });

        if ($result === null) {
            $this->auditLogger->recordAttempt(
                CentralAuditEvent::TwoFactorChallengeFailed,
                ['context' => 'confirm', 'reason' => 'invalid_code'],
                actor: $user,
                subject: $user,
            );

            throw ValidationException::withMessages([
                'code' => [__('central_auth.two_factor_invalid')],
            ]);
        }

        return $result;
    }

    private function currentTokenName(CentralUser $user): string
    {
        $token = $user->currentAccessToken();

        return $token->name !== '' ? $token->name : 'central-console';
    }

    private function revokeSetupTokens(CentralUser $user): void
    {
        $setup = CentralAuthSettings::setupAbility();

        $user->tokens()
            ->get()
            ->filter(static fn (CentralPersonalAccessToken $token): bool => in_array($setup, (array) $token->abilities, true))
            ->each(static fn (CentralPersonalAccessToken $token) => $token->delete());
    }
}
