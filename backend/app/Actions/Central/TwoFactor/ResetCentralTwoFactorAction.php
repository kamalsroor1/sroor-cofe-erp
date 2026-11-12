<?php

declare(strict_types=1);

namespace App\Actions\Central\TwoFactor;

use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * OPS-20, `central:reset-2fa`: an operator lost the phone that holds the authenticator.
 *
 * This is a deliberate, console-only recovery, NOT a bypass: it never signs anyone in and never
 * touches the password. It wipes the TOTP secret, the recovery codes and the confirmation stamp, so
 * the operator's next sign-in (password still required) only gets the 2FA-setup token (IDEN-1.12)
 * and must enrol a fresh authenticator and receive new recovery codes. Every session token and every
 * pending login challenge of the operator is revoked. Audited `two_factor_reset` (CLI user, no secrets).
 *
 * One central transaction; the operator row is locked first.
 */
final class ResetCentralTwoFactorAction
{
    public function __construct(
        private readonly CentralAuditLogger $audit,
        private readonly TwoFactorChallengeStore $challenges,
    ) {}

    /** @return int number of revoked sessions */
    public function execute(string $email, string $performedBy): int
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            throw new RuntimeException('central:reset-2fa must run in the central context.');
        }

        $email = mb_strtolower(trim($email));
        $connection = (string) (new CentralUser)->getConnectionName();

        $revoked = DB::connection($connection)->transaction(function () use ($email, $performedBy): int {
            $user = CentralUser::query()->where('email', $email)->lockForUpdate()->firstOrFail();

            $hadTwoFactor = $user->two_factor_confirmed_at !== null || ! empty($user->two_factor_secret);

            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
            ])->save();

            $count = (int) $user->tokens()->delete();

            $this->audit->record(
                CentralAuditEvent::TwoFactorReset,
                [
                    'email' => $email,
                    'had_two_factor' => $hadTwoFactor,
                    'revoked_sessions' => $count,
                    'source' => 'console',
                    'performed_by' => $performedBy,
                ],
                subject: $user,
            );

            $this->challenges->invalidateForUser((int) $user->getKey());

            return $count;
        });

        return $revoked;
    }
}
