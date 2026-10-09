<?php

declare(strict_types=1);

namespace App\Actions\Central\Data;

use App\Models\CentralUser;
use Illuminate\Support\Carbon;

/**
 * Outcome of a central sign-in step (IDEN-1.12). Exactly one of:
 *
 *  - challenge(): password accepted, 2FA confirmed: no token, a single-use challenge id
 *    to exchange at /auth/two-factor-challenge;
 *  - setupToken(): password accepted, 2FA NOT confirmed: a token carrying only the
 *    `central:2fa-setup` ability;
 *  - fullToken(): second factor proven: the full `central:*` token.
 *
 * Lives next to the central Actions (lane ownership); candidate to move to app/DTOs/Central.
 */
final class CentralSignInResult
{
    public const SCOPE_FULL = 'full';

    public const SCOPE_TWO_FACTOR_SETUP = 'two_factor_setup';

    /**
     * @param  list<string>  $abilities
     */
    private function __construct(
        public readonly bool $twoFactorRequired,
        public readonly ?string $challengeId = null,
        public readonly ?Carbon $challengeExpiresAt = null,
        public readonly ?CentralUser $user = null,
        public readonly ?string $plainTextToken = null,
        public readonly ?Carbon $expiresAt = null,
        public readonly ?string $scope = null,
        public readonly array $abilities = [],
        public readonly ?array $recoveryCodes = null,
    ) {}

    public static function challenge(string $challengeId, Carbon $expiresAt): self
    {
        return new self(true, challengeId: $challengeId, challengeExpiresAt: $expiresAt);
    }

    /**
     * @param  list<string>  $abilities
     */
    public static function setupToken(CentralUser $user, string $plainTextToken, Carbon $expiresAt, array $abilities): self
    {
        return new self(false, user: $user, plainTextToken: $plainTextToken, expiresAt: $expiresAt, scope: self::SCOPE_TWO_FACTOR_SETUP, abilities: $abilities);
    }

    /**
     * @param  list<string>  $abilities
     * @param  list<string>|null  $recoveryCodes  only right after 2FA confirmation (shown once to save)
     */
    public static function fullToken(CentralUser $user, string $plainTextToken, Carbon $expiresAt, array $abilities, ?array $recoveryCodes = null): self
    {
        return new self(false, user: $user, plainTextToken: $plainTextToken, expiresAt: $expiresAt, scope: self::SCOPE_FULL, abilities: $abilities, recoveryCodes: $recoveryCodes);
    }

    public function isSetupToken(): bool
    {
        return $this->scope === self::SCOPE_TWO_FACTOR_SETUP;
    }
}
