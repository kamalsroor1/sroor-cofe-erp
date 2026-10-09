<?php

declare(strict_types=1);

namespace App\Actions\Central\PasswordReset;

use App\Actions\Central\Data\CentralAuthSettings;
use App\Actions\Central\Data\ResetCentralPasswordDTO;
use App\Actions\Central\TwoFactor\TwoFactorChallengeStore;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/v1/super-admin/auth/reset-password (IDEN-1.12).
 *
 * One central transaction: the `central_users` broker checks the token (hashed, expiring),
 * then the operator row is locked, the new password stored (hashed cast), the remember
 * token rotated, EVERY central token of the operator revoked (full and setup) and
 * `password_reset_completed` audited; the broker deletes the reset token.
 *
 * Unknown email and bad/expired token answer the same 422 on `email`
 * (`central_auth.password_reset_invalid`), so the endpoint never reveals an account.
 * 2FA is not touched: the next sign-in still needs the second factor. Every pending login
 * challenge of the operator (password accepted, code not yet given) is burnt, so a challenge
 * obtained with the OLD password cannot be completed after the reset.
 */
final class ResetCentralPasswordAction
{
    public function __construct(
        private readonly CentralAuditLogger $auditLogger,
        private readonly TwoFactorChallengeStore $challenges,
    ) {}

    public function execute(ResetCentralPasswordDTO $dto): void
    {
        $connection = (string) (new CentralUser)->getConnectionName();

        $status = DB::connection($connection)->transaction(fn (): mixed => CentralAuthSettings::passwordBroker()->reset(
            ['email' => $dto->email, 'token' => $dto->token, 'password' => $dto->password],
            function (CentralUser $user, string $password): void {
                $locked = CentralUser::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

                $locked->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $revoked = $locked->tokens()->delete();

                // Cache, not transactional: burning the challenges even if the transaction later
                // rolled back only forces a fresh login, never grants anything.
                $this->challenges->invalidateForUser((int) $locked->getKey());

                $this->auditLogger->record(
                    CentralAuditEvent::PasswordResetCompleted,
                    ['revoked_sessions' => (int) $revoked],
                    actor: $locked,
                    subject: $locked,
                );

                event(new PasswordReset($locked));
            },
        ));

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__('central_auth.password_reset_invalid')],
            ]);
        }
    }
}
