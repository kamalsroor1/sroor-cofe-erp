<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use Carbon\CarbonImmutable;

/**
 * #232 owner decision 2026-11-26: what verifying a payment would settle, built by
 * UpgradeDifferenceRepricer for BOTH the Verify action (which applies it) and the console
 * verify-preview (which only shows it). Money is DECIMAL(12,3) strings.
 *
 *  - `repriced`: the invoice carries a system-computed upgrade difference that is re-priced now;
 *    false = the invoice amounts are final as issued (lines/totals below are the stored ones);
 *  - `basis`: 'transfer' (the verified transfer moment) or 'approval' (approval more than 48h after
 *    the transfer, or no transfer moment at all), `effectiveMoment` the moment it stands for;
 *  - `paidAmount`: verified payments + this payment; `overpaidAmount` = max(0, paid - total);
 *    `remainingDue` = max(0, total - paid) (> 0 keeps the invoice pending, partly paid).
 */
final readonly class VerificationQuote
{
    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function __construct(
        public bool $repriced,
        public string $basis,
        public CarbonImmutable $effectiveMoment,
        public ?int $remainingDays,
        public ?int $periodDays,
        public array $lines,
        public string $subtotal,
        public string $taxRate,
        public string $tax,
        public string $total,
        public string $previousTotal,
        public string $paidAmount,
        public string $overpaidAmount,
        public string $remainingDue,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'repriced' => $this->repriced,
            'basis' => $this->basis,
            'effective_moment' => $this->effectiveMoment->toIso8601String(),
            'remaining_days' => $this->remainingDays,
            'period_days' => $this->periodDays,
            'lines' => $this->lines,
            'subtotal' => $this->subtotal,
            'tax_rate' => $this->taxRate,
            'vat' => $this->tax,
            'total' => $this->total,
            'previous_total' => $this->previousTotal,
            'paid_amount' => $this->paidAmount,
            'overpaid_amount' => $this->overpaidAmount,
            'remaining_due' => $this->remainingDue,
        ];
    }
}
