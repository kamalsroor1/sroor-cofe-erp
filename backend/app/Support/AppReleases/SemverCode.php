<?php

declare(strict_types=1);

namespace App\Support\AppReleases;

use InvalidArgumentException;

/**
 * APP-7: the integer version code of a semantic version name, so a desktop build (which only
 * knows `app.getVersion()`) and the server agree on ordering without a second number.
 *
 *   1.2.3   → 1002003
 *   0.0.1   → 1
 *   999.999.999 → 999999999 (fits a signed 32-bit int)
 *
 * Each part is 0..999. Pre-release / build suffixes are rejected: a release name is always
 * MAJOR.MINOR.PATCH (StoreAppVersionRequest enforces the same pattern).
 */
final class SemverCode
{
    public const PATTERN = '/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/D';

    public static function isValid(string $versionName): bool
    {
        return preg_match(self::PATTERN, $versionName) === 1;
    }

    /**
     * @throws InvalidArgumentException when $versionName is not MAJOR.MINOR.PATCH (0..999 each)
     */
    public static function fromName(string $versionName): int
    {
        if (preg_match(self::PATTERN, $versionName, $parts) !== 1) {
            // Developer-facing: callers validate first.
            throw new InvalidArgumentException('Version name must be MAJOR.MINOR.PATCH with parts 0..999.');
        }

        return ((int) $parts[1] * 1_000_000) + ((int) $parts[2] * 1_000) + (int) $parts[3];
    }

    /** Null instead of an exception, for request validation paths. */
    public static function tryFromName(string $versionName): ?int
    {
        return self::isValid($versionName) ? self::fromName($versionName) : null;
    }

    /** Inverse of fromName(): 1002003 → "1.2.3". */
    public static function toName(int $code): string
    {
        if ($code < 0 || $code > 999_999_999) {
            throw new InvalidArgumentException('Version code must be between 0 and 999999999.');
        }

        return intdiv($code, 1_000_000).'.'.intdiv($code % 1_000_000, 1_000).'.'.($code % 1_000);
    }
}
