<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Support\PasswordPolicy;

/**
 * SELL-01: a one-time password generated on the server for a reset or a new owner account.
 *
 * Read out to a shop owner over the phone, so the alphabet leaves out look-alikes
 * (0/O/o, 1/l/I). Always mixes letters and digits and is longer than PasswordPolicy::MIN_LENGTH.
 * The plain value is returned ONCE in the API response and never stored, logged or audited; the
 * account it is set on is flagged `must_change_password`.
 */
final class TemporaryPassword
{
    public const LENGTH = 12;

    private const LETTERS = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

    private const DIGITS = '23456789';

    public static function generate(): string
    {
        $length = max(self::LENGTH, PasswordPolicy::MIN_LENGTH + 2);
        $pool = self::LETTERS.self::DIGITS;

        // One guaranteed letter and one guaranteed digit, the rest from the whole pool.
        $chars = [self::pick(self::LETTERS), self::pick(self::DIGITS)];
        for ($i = 2; $i < $length; $i++) {
            $chars[] = self::pick($pool);
        }

        // Fisher-Yates with a CSPRNG (shuffle() is not cryptographically secure).
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    private static function pick(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
