<?php

declare(strict_types=1);

namespace App\Actions\Central\TwoFactor;

use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * OPS-20, `central:reset-2fa`: an operator lost the phone that holds the authenticator.
 *
 * This is a deliberate, console-only recovery, NOT a bypass: it never signs anyone in and never
 * touches the password. It wipes the TOTP secret, the recovery codes and the confirmation stamp, so
 * the operator's next sign-in (password still required) only gets the 2FA-setup token (IDEN-1.12)
 * and must enrol a fresh authenticator and receive new recovery codes. Every session token and every
 * pending login challenge of the operator is revoked. Audited `two_factor_reset` (CLI user, the
 * mandatory reason, no secrets).
 *
 * OPS-20 review: the operator's `central_web` browser sessions (Pulse / Telescope / Horizon) are
 * revoked too when sessions live in the database. They cannot be found by `sessions.user_id`: that
 * column holds the DEFAULT guard's user (tenant `web`), never the central_web one. So each stored
 * payload is decoded (base64, optional SESSION_ENCRYPT, json or php serialization with no classes
 * allowed) and the rows whose `login_central_web_*` key holds this operator are deleted. With any
 * other session driver (file, redis, array...) they cannot be targeted: they expire with
 * SESSION_LIFETIME, and `revoked_web_sessions` is null in the audit row.
 *
 * One central transaction; the operator row is locked first.
 */
final class ResetCentralTwoFactorAction
{
    public function __construct(
        private readonly CentralAuditLogger $audit,
        private readonly TwoFactorChallengeStore $challenges,
    ) {}

    /** @return int number of revoked sessions (API tokens + central_web browser sessions) */
    public function execute(string $email, string $performedBy, string $reason): int
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('central:reset-2fa needs a non-empty reason.');
        }

        if (function_exists('tenancy') && tenancy()->initialized) {
            throw new RuntimeException('central:reset-2fa must run in the central context.');
        }

        $email = mb_strtolower(trim($email));
        $connection = (string) (new CentralUser)->getConnectionName();

        $revoked = DB::connection($connection)->transaction(function () use ($email, $performedBy, $reason): int {
            $user = CentralUser::query()->where('email', $email)->lockForUpdate()->firstOrFail();

            $hadTwoFactor = $user->two_factor_confirmed_at !== null || ! empty($user->two_factor_secret);

            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
            ])->save();

            $count = (int) $user->tokens()->delete();
            $webSessions = $this->revokeWebSessions((int) $user->getKey());

            $this->audit->record(
                CentralAuditEvent::TwoFactorReset,
                [
                    'email' => $email,
                    'had_two_factor' => $hadTwoFactor,
                    'revoked_sessions' => $count,
                    'revoked_web_sessions' => $webSessions,
                    'source' => 'console',
                    'performed_by' => $performedBy,
                    'reason' => $reason,
                ],
                subject: $user,
            );

            $this->challenges->invalidateForUser((int) $user->getKey());

            return $count + (int) $webSessions;
        });

        return $revoked;
    }

    /** Deletes the operator's central_web sessions; null when the session driver is not `database`. */
    private function revokeWebSessions(int $userId): ?int
    {
        if (config('session.driver') !== 'database') {
            return null;
        }

        // SessionGuard::getName() for the `central_web` guard, without building the guard.
        $loginKey = 'login_central_web_'.sha1(SessionGuard::class);
        $sessionConnection = config('session.connection');
        $table = (string) config('session.table', 'sessions');
        $db = DB::connection(is_string($sessionConnection) && $sessionConnection !== '' ? $sessionConnection : null);

        $ids = [];
        foreach ($db->table($table)->select(['id', 'payload'])->orderBy('id')->lazyById(500, 'id') as $row) {
            $data = $this->decodePayload((string) $row->payload);
            if ($data !== null && isset($data[$loginKey]) && (string) $data[$loginKey] === (string) $userId) {
                $ids[] = (string) $row->id;
            }
        }

        $deleted = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            $deleted += $db->table($table)->whereIn('id', $chunk)->delete();
        }

        return $deleted;
    }

    /**
     * The session attributes of one stored payload, or null when it cannot be read. Objects are never
     * instantiated (allowed_classes false): a tampered row cannot run code here.
     *
     * @return array<array-key, mixed>|null
     */
    private function decodePayload(string $payload): ?array
    {
        $raw = base64_decode($payload, true);
        if ($raw === false) {
            return null;
        }

        if ((bool) config('session.encrypt', false)) {
            try {
                $raw = unserialize(Crypt::decryptString($raw), ['allowed_classes' => false]);
            } catch (DecryptException) {
                return null;
            }
            if (! is_string($raw)) {
                return null;
            }
        }

        $data = config('session.serialization', 'php') === 'json'
            ? json_decode($raw, true)
            : @unserialize($raw, ['allowed_classes' => false]);

        return is_array($data) ? $data : null;
    }
}
