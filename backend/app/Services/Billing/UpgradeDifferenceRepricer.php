<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\DTOs\Billing\VerificationQuote;
use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentStatus;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Tenant;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * #232 owner decision 2026-11-26: an upgrade's prorated difference is FINAL only at Verify. It is
 * re-priced from the days remaining at the verified TRANSFER moment; when the approval comes more
 * than APPROVAL_GRACE_HOURS (48) after that transfer, from the APPROVAL moment instead (also when the
 * payment has no transfer moment, a gateway payment).
 *
 * quote() is read-only and is the ONE code path for both VerifyManualPaymentAction (which applies
 * the quote under its locks) and the console verify-preview (which only shows it).
 *
 * Re-priced: only a pending upgrade invoice whose difference line was computed by the system
 * (`prorated` with its inputs, IssuePlanChangeInvoiceAction) and which has no verified payment yet
 * (the first verification fixes the amount; a later partial payment does not move it again).
 * A typed / agreed amount, an add-on, plan or renewal invoice is never re-priced.
 * Math: ProrationCalculator (the same as at issue) with the line's stored old/new price and actual
 * period days; remaining days = ceil(term_end - effective moment) on the platform clock, the
 * effective moment never earlier than the issue-time pricing (`priced_at`, a transfer made earlier on
 * the issue day cannot add a day, so the issued amount is the ceiling); then the
 * subtotal and the VAT at the invoice's OWN rate are recomputed the existing way
 * (SubscriptionInvoicePricer). The `renewal` line of an upgrade + renewal is unchanged.
 */
final class UpgradeDifferenceRepricer
{
    /** Approval later than this after the transfer prices from the approval moment instead. */
    public const APPROVAL_GRACE_HOURS = 48;

    public const BASIS_TRANSFER = 'transfer';

    public const BASIS_APPROVAL = 'approval';

    public function __construct(
        private readonly ProrationCalculator $proration,
        private readonly SubscriptionInvoicePricer $pricer,
    ) {}

    /**
     * #232 spec A: required for a manual payment, not in the future, not before the invoice's issue
     * day (start of that day). 422 on `transferred_at`. Shared by Verify and its preview.
     */
    public function assertTransferMoment(?CarbonImmutable $transferredAt, BillingPayment $payment, BillingInvoice $invoice): void
    {
        if ($transferredAt === null) {
            if ($payment->gateway === BillingGateway::Manual) {
                throw ValidationException::withMessages(['transferred_at' => __('billing.errors.transferred_at_required')]);
            }

            return;
        }

        if ($transferredAt->isFuture()) {
            throw ValidationException::withMessages(['transferred_at' => __('billing.errors.transferred_at_future')]);
        }

        if ($transferredAt->lessThan($invoice->earliestTransferAt())) {
            throw ValidationException::withMessages(['transferred_at' => __('billing.errors.transferred_at_before_invoice')]);
        }
    }

    public function quote(BillingInvoice $invoice, BillingPayment $payment, ?CarbonImmutable $transferredAt, CarbonImmutable $now): VerificationQuote
    {
        [$basis, $effective] = $this->effectiveMoment($transferredAt, $now);

        $verified = '0.000';
        $amounts = BillingPayment::query()
            ->where('billing_invoice_id', $invoice->getKey())
            ->where('status', BillingPaymentStatus::Verified->value)
            ->whereKeyNot($payment->getKey())
            ->pluck('amount');
        foreach ($amounts as $amount) {
            $verified = bcadd($verified, (string) $amount, 3);
        }
        $paid = $payment->status === BillingPaymentStatus::Pending ? bcadd($verified, (string) $payment->amount, 3) : $verified;

        $lines = $invoice->lines;
        $subtotal = Decimal::normalize($invoice->subtotal);
        $tax = Decimal::normalize($invoice->tax);
        $total = Decimal::normalize($invoice->total);
        $remainingDays = null;
        $periodDays = null;
        $repriced = false;

        $index = $this->repriceableLine($invoice, $verified);
        $termEnd = $index === null ? null : $this->termEnd($invoice, $lines[$index]);

        if ($index !== null && $termEnd !== null) {
            $line = $lines[$index];
            $periodDays = (int) $line['period_days'];
            // A transfer earlier on the issue day than the issue moment never prices MORE days than
            // the invoice was issued for: the issue-time amount is the ceiling (days only shrink).
            $from = $this->notBeforeIssuePricing($effective, $line);
            $remainingDays = min($this->proration->wholeDaysUp($from, $termEnd), $periodDays);
            $amount = $this->proration->difference((string) $line['old_price'], (string) $line['new_price'], $remainingDays, $periodDays);

            $lines[$index] = array_merge($line, [
                'unit_price' => $amount,
                'amount' => $amount,
                'remaining_days' => $remainingDays,
                'priced_at' => $from->toIso8601String(),
                'pricing_basis' => $basis,
            ]);

            $subtotal = $this->pricer->subtotal($lines);
            ['tax' => $tax, 'total' => $total] = $this->pricer->taxAt(bcsub($subtotal, Decimal::normalize($invoice->discount), 3), Decimal::normalize($invoice->tax_rate));
            $repriced = true;
        }

        $overpaid = bccomp($paid, $total, 3) > 0 ? bcsub($paid, $total, 3) : '0.000';
        $remainingDue = bccomp($total, $paid, 3) > 0 ? bcsub($total, $paid, 3) : '0.000';

        return new VerificationQuote(
            repriced: $repriced,
            basis: $basis,
            effectiveMoment: $effective,
            remainingDays: $remainingDays,
            periodDays: $periodDays,
            lines: array_values($lines),
            subtotal: $subtotal,
            taxRate: Decimal::normalize($invoice->tax_rate),
            tax: $tax,
            total: $total,
            previousTotal: Decimal::normalize($invoice->total),
            paidAmount: $paid,
            overpaidAmount: $overpaid,
            remainingDue: $remainingDue,
        );
    }

    /**
     * @return array{0: string, 1: CarbonImmutable}
     */
    private function effectiveMoment(?CarbonImmutable $transferredAt, CarbonImmutable $now): array
    {
        $timezone = (string) config('app.timezone');

        if ($transferredAt === null || $now->greaterThan($transferredAt->addHours(self::APPROVAL_GRACE_HOURS))) {
            return [self::BASIS_APPROVAL, $now->setTimezone($timezone)];
        }

        return [self::BASIS_TRANSFER, $transferredAt->setTimezone($timezone)];
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function notBeforeIssuePricing(CarbonImmutable $effective, array $line): CarbonImmutable
    {
        if (! is_string($line['priced_at'] ?? null)) {
            return $effective;
        }

        $issued = CarbonImmutable::parse($line['priced_at'])->setTimezone((string) config('app.timezone'));

        return $effective->lessThan($issued) ? $issued : $effective;
    }

    /** Index of the system-prorated difference line, when the invoice may still be re-priced. */
    private function repriceableLine(BillingInvoice $invoice, string $alreadyVerified): ?int
    {
        if ($invoice->type !== BillingInvoiceType::Upgrade
            || $invoice->status !== BillingInvoiceStatus::Pending
            || bccomp($alreadyVerified, '0', 3) > 0) {
            return null;
        }

        foreach ($invoice->lines as $index => $line) {
            if (($line['type'] ?? null) === 'plan'
                && ($line['difference'] ?? false) === true
                && ($line['prorated'] ?? false) === true
                && isset($line['old_price'], $line['new_price'], $line['period_days'])
                && (int) $line['period_days'] > 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The paid-term end the difference priced, when it is still the tenant's term end. Otherwise
     * nothing is re-priced and ActivateSubscriptionAction refuses the stale difference (409).
     *
     * @param  array<string, mixed>  $line
     */
    private function termEnd(BillingInvoice $invoice, array $line): ?CarbonImmutable
    {
        if (! is_string($line['term_end'] ?? null)) {
            return null;
        }

        $priced = CarbonImmutable::parse($line['term_end']);
        $current = Tenant::query()->whereKey($invoice->tenant_id)->first()?->subscription_ends_at;

        if ($current === null || $current->getTimestamp() !== $priced->getTimestamp()) {
            return null;
        }

        return $priced;
    }
}
