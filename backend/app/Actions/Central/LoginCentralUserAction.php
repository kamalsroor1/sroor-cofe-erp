<?php

declare(strict_types=1);

namespace App\Actions\Central;

use App\DTOs\Central\CentralLoginDTO;
use App\DTOs\Central\CentralLoginResult;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Signs a platform operator in (IDEN-1.3).
 *
 * - Unknown email, wrong password and inactive account all answer the same 422
 *   (`central_auth.failed`), and an unknown email still pays for one hash check, so the
 *   response does not reveal whether the account exists. Each failure is audited with
 *   recordAttempt() (survives any rollback) and its reason.
 * - A confirmed 2FA (`two_factor_confirmed_at`) returns "two_factor_required" and NO
 *   token; the challenge endpoint is IDEN-1.12.
 * - Success: one central transaction stamps last_login_*, issues a central token
 *   (ability `central:*`, `expires_at` = now + central.token_ttl_minutes) and audits
 *   `login_succeeded` (record() joins the transaction).
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
    ) {}

    public function execute(CentralLoginDTO $dto, ?string $ipAddress = null): CentralLoginResult
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

        if ($user->two_factor_confirmed_at !== null) {
            return CentralLoginResult::twoFactorRequired();
        }

        return DB::connection($user->getConnectionName())->transaction(function () use ($user, $dto, $ipAddress): CentralLoginResult {
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $ipAddress,
            ])->save();

            $newToken = $user->createToken($dto->deviceName);
            $expiresAt = $newToken->accessToken->getAttribute('expires_at');

            $this->auditLogger->record(
                CentralAuditEvent::LoginSucceeded,
                ['device_name' => $dto->deviceName],
                actor: $user,
                subject: $user,
            );

            return CentralLoginResult::issued(
                $user,
                $newToken->plainTextToken,
                $expiresAt instanceof Carbon ? $expiresAt : Carbon::parse((string) $expiresAt),
            );
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
