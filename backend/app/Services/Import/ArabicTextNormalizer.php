<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * IMP-01: a matching key for Arabic (and Latin) text typed by hand in a spreadsheet.
 *
 * «قطعة» / «قطعه», «إسبريسو» / «اسبريسو», «مقهى» / «مقهي», «بن  برازيلي» / «بن برازيلي» and
 * «Coffee» / «coffee» all collapse to the same key. The key is for COMPARISON only: the value that
 * gets stored is always the tenant's own spelling (unit list, existing category) or the cell as typed.
 */
final class ArabicTextNormalizer
{
    private const LETTERS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ة' => 'ه',
        'ى' => 'ي', 'ئ' => 'ي',
        'ؤ' => 'و',
    ];

    public static function key(string $value): string
    {
        $value = NumberNormalizer::latinDigits($value);
        // Tashkeel (harakat, shadda, sukun, superscript alef) and tatweel never change the meaning of a name.
        $value = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $value);
        $value = strtr($value, self::LETTERS);
        $value = mb_strtolower($value);

        return trim((string) preg_replace('/[\s\x{00A0}\x{200B}-\x{200F}]+/u', ' ', $value));
    }

    /**
     * The candidate closest to $value (by normalized edit distance), when it is close enough to be a
     * plausible typo: at most one edit for short words, about a third of the length for longer ones.
     *
     * @param  list<string>  $candidates
     */
    public static function closest(string $value, array $candidates): ?string
    {
        $needle = self::key($value);
        if ($needle === '') {
            return null;
        }

        $best = null;
        $bestDistance = PHP_INT_MAX;
        foreach ($candidates as $candidate) {
            $distance = self::distance($needle, self::key($candidate));
            if ($distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        $allowed = max(1, intdiv(mb_strlen($needle), 3));

        return $best !== null && $bestDistance <= $allowed ? $best : null;
    }

    /** Levenshtein distance on characters (PHP's levenshtein() counts bytes, two per Arabic letter). */
    private static function distance(string $a, string $b): int
    {
        $left = mb_str_split($a);
        $right = mb_str_split($b);
        $previous = range(0, count($right));

        foreach ($left as $i => $leftChar) {
            $current = [$i + 1];
            foreach ($right as $j => $rightChar) {
                $current[$j + 1] = min(
                    $previous[$j + 1] + 1,
                    $current[$j] + 1,
                    $previous[$j] + ($leftChar === $rightChar ? 0 : 1),
                );
            }
            $previous = $current;
        }

        return $previous[count($right)];
    }
}
