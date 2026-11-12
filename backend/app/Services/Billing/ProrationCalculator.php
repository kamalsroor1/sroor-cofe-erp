<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Support\Money\Decimal;

/**
 * BILL-02: the fair upgrade difference for the rest of a paid term:
 *
 *   (newPrice - oldPrice) x remainingSeconds / totalSeconds
 *
 * bcmath strings only; the quotient is computed at scale 12 and rounded half-up to scale 3
 * (the platform rule). Never negative: a downgrade or an expired term suggests '0.000'
 * (the operator decides what to do, there is no refund path here).
 */
final class ProrationCalculator
{
    public function difference(string $oldPrice, string $newPrice, int $remainingSeconds, int $totalSeconds): string
    {
        if ($totalSeconds < 1 || $remainingSeconds < 1) {
            return '0.000';
        }

        $remaining = (string) min($remainingSeconds, $totalSeconds);
        $gap = bcsub($newPrice, $oldPrice, 3);

        if (bccomp($gap, '0', 3) <= 0) {
            return '0.000';
        }

        $exact = bcdiv(bcmul($gap, $remaining, 12), (string) $totalSeconds, 12);

        return Decimal::round($exact, 3);
    }
}
