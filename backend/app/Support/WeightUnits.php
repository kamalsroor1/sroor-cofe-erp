<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Unit names that mean "sold by weight". Single source for the POSB-2 backfill rules, the item defaults
 * and Item::isSoldByWeight() (the same matching as migration 2026_10_10_600000).
 */
final class WeightUnits
{
    /** Exact (trimmed, lower-cased) unit names treated as weight units. */
    private const NAMES = [
        'كجم', 'كج', 'كغ', 'كغم', 'كيلو', 'كيلوجرام', 'كيلو جرام', 'كيلوغرام', 'كيلو غرام',
        'جم', 'جرام', 'غرام', 'غم',
        'kg', 'kgs', 'kilo', 'kilos', 'kilogram', 'kilograms',
        'g', 'gm', 'gr', 'grm', 'gram', 'grams',
    ];

    /** Unit names containing this fragment are weight units too (legacy POS rule). */
    private const FRAGMENT = 'كيلو';

    public static function matches(?string $unit): bool
    {
        $normalized = mb_strtolower(trim((string) $unit));

        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, self::NAMES, true) || str_contains($normalized, self::FRAGMENT);
    }
}
