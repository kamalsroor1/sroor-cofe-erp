<?php

declare(strict_types=1);

namespace App\Support\Signup;

/**
 * Security review (SIGN-13): the comparable form of the two identities that limit self-signup
 * ("one free shop"), stored next to the raw values (`canonical_email`, `phone_normalized` on
 * `tenants` and `signup_requests`) so the guard is a plain indexed equality.
 *
 *  - canonicalEmail(): lowercase; `+tag` dropped for every domain; googlemail.com folded into
 *    gmail.com, whose local part ignores dots. Two spellings of one mailbox compare equal.
 *  - normalizedPhone(): digits only (Arabic-Indic / Persian digits converted); Egyptian
 *    international forms (+20 / 0020 / 20 + 1x...) folded to the local 01xxxxxxxxx; any other
 *    leading 00 dropped. Null when nothing is left.
 */
final class SignupIdentity
{
    private const GMAIL = 'gmail.com';

    /** @var list<string> */
    private const GMAIL_ALIASES = ['gmail.com', 'googlemail.com'];

    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public static function canonicalEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));
        if ($email === '') {
            return null;
        }

        $at = strrpos($email, '@');
        if ($at === false) {
            return $email;
        }

        $local = substr($email, 0, $at);
        $domain = rtrim(substr($email, $at + 1), '.');

        if (in_array($domain, self::GMAIL_ALIASES, true)) {
            $domain = self::GMAIL;
        }

        $plus = strpos($local, '+');
        if ($plus !== false && $plus > 0) {
            $local = substr($local, 0, $plus);
        }

        if ($domain === self::GMAIL) {
            $local = str_replace('.', '', $local);
        }

        return $local.'@'.$domain;
    }

    public static function normalizedPhone(?string $phone): ?string
    {
        $digits = (string) preg_replace('/\D+/', '', strtr((string) $phone, self::DIGITS));
        if ($digits === '') {
            return null;
        }

        if (preg_match('/^(?:00)?20(1[0125]\d{8})$/', $digits, $match) === 1) {
            return '0'.$match[1];
        }

        if (str_starts_with($digits, '00') && strlen($digits) > 2) {
            return substr($digits, 2);
        }

        return $digits;
    }
}
