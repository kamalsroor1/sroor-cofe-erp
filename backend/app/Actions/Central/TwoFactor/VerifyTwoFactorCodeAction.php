<?php

declare(strict_types=1);

namespace App\Actions\Central\TwoFactor;

use App\Actions\Central\Data\TwoFactorCodeDTO;
use App\Models\CentralUser;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Throwable;

/**
 * Checks a second factor of an operator with CONFIRMED 2FA (IDEN-1.12), shared by the
 * login challenge and the step-up endpoint.
 *
 *  - TOTP: Fortify's TwoFactorAuthenticationProvider (window from config/fortify.php; a
 *    code accepted once is refused again inside its window: Fortify's replay cache).
 *  - Recovery code: compared in constant time against the decrypted list; a match is
 *    burnt and replaced (Fortify's replaceRecoveryCode) under lockForUpdate on the
 *    operator row, so one recovery code can never be used twice, even concurrently.
 *
 * Returns the method used (METHOD_TOTP | METHOD_RECOVERY_CODE) or null when the factor is wrong.
 */
final class VerifyTwoFactorCodeAction
{
    public const METHOD_TOTP = 'totp';

    public const METHOD_RECOVERY_CODE = 'recovery_code';

    public function __construct(
        private readonly TwoFactorAuthenticationProvider $provider,
    ) {}

    public function execute(CentralUser $user, TwoFactorCodeDTO $factor): ?string
    {
        return DB::connection($user->getConnectionName())->transaction(function () use ($user, $factor): ?string {
            $locked = CentralUser::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if (! $locked instanceof CentralUser
                || $locked->two_factor_confirmed_at === null
                || empty($locked->two_factor_secret)) {
                return null;
            }

            if ($factor->code !== null) {
                return $this->verifyTotp($locked, $factor->code) ? self::METHOD_TOTP : null;
            }

            if ($factor->recoveryCode !== null && $this->consumeRecoveryCode($locked, $factor->recoveryCode)) {
                // Keep the caller's instance in sync with the rotated codes.
                $user->setRawAttributes($locked->getAttributes(), true);

                return self::METHOD_RECOVERY_CODE;
            }

            return null;
        });
    }

    private function verifyTotp(CentralUser $user, string $code): bool
    {
        try {
            $secret = (string) Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);
        } catch (Throwable) {
            return false;
        }

        return (bool) $this->provider->verify($secret, $code);
    }

    private function consumeRecoveryCode(CentralUser $user, string $candidate): bool
    {
        try {
            $codes = $user->recoveryCodes();
        } catch (Throwable) {
            return false;
        }

        foreach ($codes as $code) {
            if (is_string($code) && hash_equals($code, $candidate)) {
                $user->replaceRecoveryCode($code);

                return true;
            }
        }

        return false;
    }
}
