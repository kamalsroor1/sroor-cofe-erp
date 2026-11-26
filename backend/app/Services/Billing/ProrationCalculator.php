<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;

/**
 * #232 spec B (replaces the BILL-02 per-second basis): the fair mid-term upgrade difference
 *
 *   difference = (newPrice - currentPrice) x remainingDays / periodDays
 *
 * for monthly and annual alike, both prices at the CURRENT term's cycle, the period END unchanged.
 *  - remainingDays = WHOLE days left, rounded UP: ceil(periodEnd - moment) in calendar days on the
 *    platform clock (config app.timezone, DST-safe because days are counted with addDays);
 *  - periodDays    = the ACTUAL days of the current period (28-31 for a month, 365/366 for a year);
 *  - bcmath: the product and quotient at scale 12, then Decimal::round half-up to scale 3 (the
 *    platform rule). VAT is added on top by IssueSubscriptionInvoiceAction like on every line.
 * Never negative: a downgrade or an expired term gives '0.000' (no refund path here).
 */
final class ProrationCalculator
{
    public function difference(string $oldPrice, string $newPrice, int $remainingDays, int $periodDays): string
    {
        if ($periodDays < 1 || $remainingDays < 1) {
            return '0.000';
        }

        $remaining = (string) min($remainingDays, $periodDays);
        $gap = bcsub($newPrice, $oldPrice, 3);

        if (bccomp($gap, '0', 3) <= 0) {
            return '0.000';
        }

        $exact = bcdiv(bcmul($gap, $remaining, 12), (string) $periodDays, 12);

        return Decimal::round($exact, 3);
    }

    /**
     * Whole calendar days from $from to $to, a started day counting as a full one (ceil); 0 when
     * $to is not after $from. Both are moved to the platform clock first.
     */
    public function wholeDaysUp(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $timezone = (string) config('app.timezone');
        $from = $from->setTimezone($timezone);
        $to = $to->setTimezone($timezone);

        if (! $to->greaterThan($from)) {
            return 0;
        }

        $days = (int) $from->diffInDays($to, true);

        return $from->addDays($days)->lessThan($to) ? $days + 1 : $days;
    }
}
