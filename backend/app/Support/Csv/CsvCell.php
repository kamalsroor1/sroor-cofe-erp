<?php

declare(strict_types=1);

namespace App\Support\Csv;

/**
 * Safe CSV cell encoding. Every field is quoted, inner quotes are doubled, and a value that
 * a spreadsheet would read as a formula (starts with = + - @ tab or CR) gets a leading
 * single quote so it is shown as text (CSV/formula injection).
 */
final class CsvCell
{
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function escape(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;

        if (self::isDangerous($text)) {
            $text = "'".$text;
        }

        return '"'.str_replace('"', '""', $text).'"';
    }

    private static function isDangerous(string $text): bool
    {
        // Numbers (negative money included, also as number_format() renders them: "-1,234.50",
        // or a percentage "-12.5%") and the lone "-" placeholder are data, not formulas.
        if ($text === '-' || preg_match('/^-?(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?%?$/', $text) === 1) {
            return false;
        }

        // Spreadsheets ignore leading blanks, so judge the first meaningful character.
        $trimmed = ltrim($text, " \n");

        return $trimmed !== '' && in_array($trimmed[0], self::FORMULA_PREFIXES, true);
    }

    /**
     * @param  iterable<mixed>  $cells
     */
    public static function row(iterable $cells): string
    {
        $encoded = [];
        foreach ($cells as $cell) {
            $encoded[] = self::escape($cell);
        }

        return implode(',', $encoded)."\r\n";
    }
}
