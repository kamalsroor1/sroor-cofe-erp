<?php

declare(strict_types=1);

namespace App\DTOs\Central;

use App\Models\CentralUser;
use Illuminate\Support\Carbon;

/**
 * Outcome of LoginCentralUserAction: either an issued token, or "two-factor required"
 * (no token yet; the challenge endpoint arrives with IDEN-1.12).
 */
final class CentralLoginResult
{
    private function __construct(
        public readonly bool $twoFactorRequired,
        public readonly ?CentralUser $user = null,
        public readonly ?string $plainTextToken = null,
        public readonly ?Carbon $expiresAt = null,
    ) {}

    public static function issued(CentralUser $user, string $plainTextToken, Carbon $expiresAt): self
    {
        return new self(false, $user, $plainTextToken, $expiresAt);
    }

    public static function twoFactorRequired(): self
    {
        return new self(true);
    }
}
