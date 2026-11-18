<?php

declare(strict_types=1);

namespace App\Support\Signup;

use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * SIGN-11: the two secrets of a self-signup and how they are stored.
 *
 *  - token: opaque, 64 random chars, returned ONCE to the browser; stored as sha256 (enough for a
 *    high-entropy value, and indexable for the lookup).
 *  - code: 6 digits, emailed; stored as an HMAC-SHA256 keyed with the app key and bound to the row
 *    id, so a leaked table alone cannot be brute-forced offline and a hash cannot be replayed on
 *    another row. Compared with hash_equals() (constant time).
 */
final class SignupSecrets
{
    public const TOKEN_LENGTH = 64;

    public static function newToken(): string
    {
        return Str::random(self::TOKEN_LENGTH);
    }

    public static function hashToken(#[SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    public static function newCode(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    public static function hashCode(int $signupRequestId, #[SensitiveParameter] string $code): string
    {
        return hash_hmac('sha256', $signupRequestId.'|'.$code, (string) config('app.key'));
    }

    public static function codeMatches(?string $storedHash, int $signupRequestId, #[SensitiveParameter] string $code): bool
    {
        return is_string($storedHash) && $storedHash !== ''
            && hash_equals($storedHash, self::hashCode($signupRequestId, $code));
    }

    /** "a***@example.com": enough for the owner to recognise the address, not to read it. */
    public static function maskEmail(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false || $at === 0) {
            return '***';
        }

        return mb_substr($email, 0, 1).'***'.substr($email, $at);
    }
}
