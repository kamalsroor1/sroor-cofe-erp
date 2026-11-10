<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * SEC-05: single source of truth for the tenant-user password policy (new or changed passwords
 * only). Length only by owner decision; the login request must never apply it, so legacy
 * shorter passwords keep working. Failure message is the translated validation.min.string.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    public static function rule(): Password
    {
        return Password::min(self::MIN_LENGTH);
    }
}
