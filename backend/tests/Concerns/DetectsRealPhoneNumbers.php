<?php

declare(strict_types=1);

namespace Tests\Concerns;

/**
 * Shared definition of an "obvious dummy" Egyptian mobile number for the
 * real-phone regression guards (F1a / F1d).
 *
 * A literal matching /01[0125]\d{8}/ is accepted only when the 8 digits after
 * the operator prefix are clearly fake:
 *  - start with at least four zeros   (01000000001, 01000000010, 01000001234)
 *  - are one repeated digit, optionally with a 1–2 digit tail (01011111111, 01222222201)
 *  - are a plain ascending/descending run used as UI placeholders (01012345678)
 *
 * Never print a matched value from a test: report file paths only.
 */
trait DetectsRealPhoneNumbers
{
    protected const PHONE_PATTERN = '/01[0125]\d{8}/';

    /** @var list<string> */
    private const SEQUENTIAL_PLACEHOLDER_SUFFIXES = [
        '12345678',
        '23456789',
        '34567890',
        '87654321',
        '98765432',
    ];

    protected static function isObviousDummyPhone(string $phone): bool
    {
        if (preg_match('/^01[0125]\d{8}$/', $phone) !== 1) {
            return false;
        }

        $suffix = substr($phone, 3);

        if (preg_match('/^0{4}\d{4}$/', $suffix) === 1) {
            return true;
        }

        if (preg_match('/^(\d)\1{5,}\d{0,2}$/', $suffix) === 1) {
            return true;
        }

        return in_array($suffix, self::SEQUENTIAL_PLACEHOLDER_SUFFIXES, true);
    }

    /**
     * Count phone-like literals in $contents that are not obvious dummies.
     */
    protected static function countRealLookingPhones(string $contents): int
    {
        preg_match_all(self::PHONE_PATTERN, $contents, $matches);

        $real = array_filter(
            array_unique($matches[0]),
            static fn (string $phone): bool => ! self::isObviousDummyPhone($phone)
        );

        return count($real);
    }
}
