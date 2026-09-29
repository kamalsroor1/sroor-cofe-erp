<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for weighted-average-cost math.
 * Stored values are DECIMAL(12,3); intermediates use bcmath scale 6 and the result is
 * rounded half-up to 3 dp (plain bcdiv(..., 3) truncates and drifts, e.g. 229.999 instead of 230).
 */
final class WeightedAverageCost
{
    private const WORK_SCALE = 6;

    /** Blend an incoming quantity at $unitCost into the running WAC. */
    public static function add(string $stock, string $wac, string $qty, string $unitCost): string
    {
        $existing = bccomp($stock, '0', 3) > 0 ? $stock : '0.000';
        $totalQty = bcadd($existing, $qty, 3);

        if (bccomp($totalQty, '0', 3) <= 0) {
            return self::round($unitCost);
        }

        $value = bcadd(
            bcmul($existing, $wac, self::WORK_SCALE),
            bcmul($qty, $unitCost, self::WORK_SCALE),
            self::WORK_SCALE
        );

        return self::round(bcdiv($value, $totalQty, self::WORK_SCALE));
    }

    /**
     * Take a quantity back out of the WAC at the cost it originally came in with
     * (purchase cancellation / purchase return). Never returns zero or a negative cost.
     */
    public static function remove(string $stock, string $wac, string $qty, string $unitCost): string
    {
        $existing = bccomp($stock, '0', 3) > 0 ? $stock : '0.000';
        $remainingQty = bcsub($existing, $qty, 3);

        if (bccomp($remainingQty, '0', 3) <= 0) {
            return self::round($wac); // everything leaves; keep the last WAC as reference
        }

        $value = bcsub(
            bcmul($existing, $wac, self::WORK_SCALE),
            bcmul($qty, $unitCost, self::WORK_SCALE),
            self::WORK_SCALE
        );

        if (bccomp($value, '0', self::WORK_SCALE) <= 0) {
            return self::round($wac); // legacy-distorted WAC: never produce a non-positive cost
        }

        return self::round(bcdiv($value, $remainingQty, self::WORK_SCALE));
    }

    /** Half-up rounding to 3 dp for non-negative values. */
    public static function round(string $value): string
    {
        return bcadd($value, '0.0005', 3);
    }
}
