<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * PRD-06: turns spreadsheet cell text into canonical values.
 * Numbers become bcmath strings with 3 decimals; no float is ever involved.
 */
final class NumberNormalizer
{
    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /** Arabic-Indic digits to ASCII digits. */
    public static function latinDigits(string $value): string
    {
        return strtr($value, self::DIGITS);
    }

    /** Decimal places a DECIMAL(12,3) column keeps. */
    public const SCALE = 3;

    /**
     * @return string|null canonical `-?\d{1,9}.\d{3}` string, null when the text is not a number,
     *                     does not fit DECIMAL(12,3) or carries more than 3 significant decimals
     *                     (never truncated silently: see exceedsScale())
     */
    public static function decimal(string $value): ?string
    {
        $parts = self::parts($value);
        if ($parts === null) {
            return null;
        }

        [$negative, $int, $fraction] = $parts;
        if (strlen($int) > 9 || self::significantDecimals($fraction) > self::SCALE) {
            return null;
        }

        $fraction = substr(str_pad($fraction, self::SCALE, '0'), 0, self::SCALE);
        $negative = $negative && ($int !== '0' || trim($fraction, '0') !== '');

        return ($negative ? '-' : '').$int.'.'.$fraction;
    }

    /**
     * IMP-02: a well-formed number that only fails because it has more than 3 significant decimals
     * ("0.0005"), so the caller can say exactly that instead of "not a valid number".
     * Trailing zeros do not count ("1.2500" is fine).
     */
    public static function exceedsScale(string $value): bool
    {
        $parts = self::parts($value);

        return $parts !== null && self::significantDecimals($parts[2]) > self::SCALE;
    }

    /**
     * Arabic digits, decimal comma and thousands separators resolved into sign / integer / fraction digits.
     *
     * @return array{0: bool, 1: string, 2: string}|null [negative, integer digits without leading zeros, fraction digits]
     */
    private static function parts(string $value): ?array
    {
        $clean = self::latinDigits(trim($value));
        $clean = str_replace(["\u{00A0}", ' ', "\u{066C}", "\u{2212}"], ['', '', ',', '-'], $clean);
        $clean = str_replace("\u{066B}", '.', $clean);

        if ($clean === '') {
            return null;
        }

        // A lone comma followed by 1-2 digits and no dot is a decimal comma ("12,5"); otherwise commas group thousands.
        if (! str_contains($clean, '.') && preg_match('/^-?\d+,\d{1,2}$/', $clean) === 1) {
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        if (preg_match('/^-?(\d+)(?:\.(\d+))?$/', $clean, $m) !== 1) {
            return null;
        }

        $int = ltrim($m[1], '0');

        return [str_starts_with($clean, '-'), $int === '' ? '0' : $int, $m[2] ?? ''];
    }

    private static function significantDecimals(string $fraction): int
    {
        return strlen(rtrim($fraction, '0'));
    }

    /** Phone: ASCII digits, optional leading +, separators removed; a bare 10-digit Egyptian mobile gets its 0 back. */
    public static function phone(string $value): string
    {
        $clean = preg_replace('/[^\d+]/', '', self::latinDigits(trim($value))) ?? '';

        if (preg_match('/^1\d{9}$/', $clean) === 1) {
            return '0'.$clean;
        }

        return $clean;
    }

    /** @return bool|null null when the text is not a recognised yes/no */
    public static function boolean(string $value): ?bool
    {
        $clean = mb_strtolower(trim(self::latinDigits($value)));

        return match ($clean) {
            '1', 'yes', 'y', 'true', 'نعم', 'ايوه', 'أيوه' => true,
            '0', 'no', 'n', 'false', 'لا', 'لأ' => false,
            default => null,
        };
    }
}
