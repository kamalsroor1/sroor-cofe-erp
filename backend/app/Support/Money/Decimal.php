<?php

declare(strict_types=1);

namespace App\Support\Money;

use InvalidArgumentException;

/**
 * SETG-13 (CTO Settings Q4): the one rounding rule for money and quantities.
 *
 * - Scale 3 for every stored / computed amount (DECIMAL(12,3)).
 * - HALF-UP, symmetric: a 5 in the 4th decimal rounds away from zero
 *   (0.0005 -> 0.001, -0.0005 -> -0.001, 3.147975 -> 3.148).
 * - Operands are first settled at scale 3 (half-up), then the operation is computed
 *   exactly, then the result is rounded half-up to scale 3.
 * - bcmath only: no float, no round()/floor().
 *
 * resources/js/helpers/decimal.js implements the identical rule; both run the shared
 * vectors in tests/Fixtures/rounding-vectors.json (RoundingParityTest + the node test),
 * so the POS and the server produce the same string for the same input.
 *
 * Applies to NEW documents only: stored totals of existing invoices are never
 * recomputed with this rule.
 */
final class Decimal
{
    public const SCALE = 3;

    /** Accepts '12', '-0.5', '.5', '7.', '1.5e3', '+2'. */
    private const PATTERN = '/^([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/';

    private function __construct() {}

    /**
     * Settle any numeric input at scale 3, half-up. Empty / unparseable input is zero.
     */
    public static function normalize(string|int|null $value, int $scale = self::SCALE): string
    {
        return self::round(self::exact($value), $scale);
    }

    /**
     * Round an exact decimal string half-up (away from zero on a tie) to $scale.
     *
     * @param  string  $value  a plain decimal string (no exponent), any number of decimals
     */
    public static function round(string $value, int $scale = self::SCALE): string
    {
        if ($scale < 0) {
            throw new InvalidArgumentException('Scale must be >= 0.');
        }

        $value = self::exact($value);
        $negative = str_starts_with($value, '-');
        $half = '0.'.str_repeat('0', $scale).'5';

        // bcadd truncates toward zero at $scale, so adding half of the next unit (with the
        // value's own sign) gives half-up away from zero.
        $rounded = $negative ? bcsub($value, $half, $scale) : bcadd($value, $half, $scale);

        return self::withoutNegativeZero($rounded, $scale);
    }

    public static function add(string|int $a, string|int $b): string
    {
        return self::round(bcadd(self::normalize($a), self::normalize($b), self::SCALE));
    }

    public static function sub(string|int $a, string|int $b): string
    {
        return self::round(bcsub(self::normalize($a), self::normalize($b), self::SCALE));
    }

    /**
     * a x b, half-up at scale 3 (e.g. 0.255 kg x 12.345 = 3.147975 -> 3.148).
     */
    public static function mul(string|int $a, string|int $b): string
    {
        return self::round(bcmul(self::normalize($a), self::normalize($b), self::SCALE * 2));
    }

    /**
     * $percent % of $amount, half-up at scale 3: amount x percent / 100, computed exactly.
     */
    public static function percent(string|int $amount, string|int $percent): string
    {
        $product = bcmul(self::normalize($amount), self::normalize($percent), self::SCALE * 2);

        return self::round(bcdiv($product, '100', self::SCALE * 2 + 2));
    }

    /** -1, 0 or 1, at scale 3 after normalizing both sides. */
    public static function cmp(string|int $a, string|int $b): int
    {
        return bccomp(self::normalize($a), self::normalize($b), self::SCALE);
    }

    /**
     * Expand any accepted input to a plain, exact decimal string (no exponent, no
     * rounding). Unparseable input is '0'.
     */
    private static function exact(string|int|null $value): string
    {
        if ($value === null) {
            return '0';
        }

        $raw = trim((string) $value);
        if (preg_match(self::PATTERN, $raw, $m) !== 1) {
            return '0';
        }

        $sign = $m[1] === '-' ? '-' : '';
        $intPart = $m[2];
        $fracPart = $m[3] ?? '';
        $exponent = isset($m[4]) ? (int) $m[4] : 0;

        if ($intPart === '' && $fracPart === '') {
            return '0';
        }

        $digits = $intPart.$fracPart;
        $pointPos = strlen($intPart) + $exponent;

        if ($pointPos < 0) {
            $digits = str_repeat('0', -$pointPos).$digits;
            $pointPos = 0;
        }
        if ($pointPos > strlen($digits)) {
            $digits = str_pad($digits, $pointPos, '0');
        }

        $whole = ltrim(substr($digits, 0, $pointPos), '0');
        $frac = rtrim(substr($digits, $pointPos), '0');

        $result = ($whole === '' ? '0' : $whole).($frac === '' ? '' : '.'.$frac);

        return $result === '0' ? '0' : $sign.$result;
    }

    private static function withoutNegativeZero(string $value, int $scale): string
    {
        if (bccomp($value, '0', $scale) === 0) {
            return bcadd('0', '0', $scale);
        }

        return $value;
    }
}
