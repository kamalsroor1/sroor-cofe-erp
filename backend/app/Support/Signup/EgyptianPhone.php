<?php

declare(strict_types=1);

namespace App\Support\Signup;

use App\Http\Requests\Signup\StoreSignupRequestRequest;

/**
 * SIGN-13: one Egyptian mobile, whatever way it was typed. normalize() gives the local form
 * (01xxxxxxxxx) from 01x / +201x / 00201x / 201x, Arabic-Indic digits and separators
 * (StoreSignupRequestRequest::normalizePhone, the form's own normaliser); variants() lists the
 * spellings an older central row (tenants.phone) may hold, for exact-match lookups.
 */
final class EgyptianPhone
{
    public static function normalize(string $phone): string
    {
        return StoreSignupRequestRequest::normalizePhone($phone);
    }

    /**
     * @return list<string>
     */
    public static function variants(string $phone): array
    {
        $local = self::normalize($phone);
        if ($local === '') {
            return [];
        }

        if (preg_match(StoreSignupRequestRequest::PHONE_PATTERN, $local) !== 1) {
            return [$local];
        }

        $national = substr($local, 1);

        return [$local, '+20'.$national, '0020'.$national, '20'.$national];
    }
}
