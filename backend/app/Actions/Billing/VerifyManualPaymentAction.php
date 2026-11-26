<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\VerificationQuote;
use App\DTOs\Billing\VerifyManualPaymentDTO;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\TemporaryAccessOutcome;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Services\Billing\TemporaryAccessService;
use App\Services\Billing\UpgradeDifferenceRepricer;
use App\Services\CentralAuditLogger;
use App\Support\Database\TransactionRetry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * PRD-03 / ENTI-3.4: a super-admin verifies a pending manual payment.
 *
 * One CENTRAL transaction through TransactionRetry (a deadlock is retried, then 409 busy).
 *
 * Billing lock order (ONE order for every flow that locks the tenant row): tenant -> payment ->
 * invoice(s) -> subscription. The tenant row comes first because IssueSubscriptionInvoiceAction
 * (and ActivateSubscriptionAction::voidStaleRenewals) lock the tenant and THEN the tenant's open
 * renewal invoices; taking payment -> invoice -> tenant here would invert that and deadlock a
 * verify against a concurrent difference-invoice issue. The payment's tenant_id is read without
 * a lock first (it never changes), then the tenant, payment and invoice rows are locked in order.
 * Actions that never lock the tenant (submit / reject / void / cancel) keep payment -> invoice.
 *
 * Idempotent: verifying an already verified payment returns it untouched, so a double click or a
 * retry can never extend the subscription twice. A rejected payment, or a void/paid invoice, is a 409.
 * The invoice becomes `paid` once its verified payments reach the total; a partial payment leaves it
 * `pending` for the next receipt. Plan / renewal / upgrade invoices then activate the subscription;
 * a `service` invoice is only marked paid (no subscription change).
 *
 * #232 spec A: the operator enters the ACTUAL transfer date-time (`transferred_at`), stored on the
 * payment; ActivateSubscriptionAction starts a new paid term there when none is running. It is
 * required for a manual-gateway payment, never in the future (also checked by the Form Request) and
 * never before the START of the invoice's issue day on the platform clock (owner rule; whether a
 * small tolerance before that day is allowed is an open decision). All three are 422 on
 * `transferred_at`, checked under the locks and after the idempotent "already verified" return, so
 * a retried verify of a verified payment never fails on them. A gateway payment (no transfer date)
 * starts at the confirmation moment. #232 spec D: when the payment settles the invoice, its 48-hour
 * temporary-access window (if any) is settled `verified` (TemporaryAccessService) before activation.
 *
 * Owner decision 2026-11-26: a system-computed upgrade difference is re-priced here from the
 * verified transfer moment (or the approval moment when it comes > 48h after the transfer), through
 * UpgradeDifferenceRepricer (the same quote GET .../verify-preview shows). Old -> new is audited
 * (`billing_invoice_repriced`). Anything paid above the final total is stored on the invoice as
 * `overpaid_amount` when it settles (refund / credit policy is open).
 */
final class VerifyManualPaymentAction
{
    public function __construct(
        private readonly ActivateSubscriptionAction $activate,
        private readonly CentralAuditLogger $audit,
        private readonly UpgradeDifferenceRepricer $repricer,
        private readonly TemporaryAccessService $temporaryAccess,
    ) {}

    public function execute(VerifyManualPaymentDTO $dto, CentralUser $operator): BillingPayment
    {
        $connection = (string) (new BillingPayment)->getConnectionName();
        $paymentId = $dto->paymentId;

        return TransactionRetry::run(function () use ($paymentId, $dto, $operator): BillingPayment {
            $tenantId = BillingPayment::query()->whereKey($paymentId)->valueOrFail('tenant_id');
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();

            $payment = BillingPayment::query()->whereKey($paymentId)->lockForUpdate()->firstOrFail();

            if ($payment->status === BillingPaymentStatus::Verified) {
                return $payment;
            }

            if ($payment->status !== BillingPaymentStatus::Pending) {
                throw BillingException::paymentNotPending();
            }

            $invoice = BillingInvoice::query()->whereKey($payment->billing_invoice_id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== BillingInvoiceStatus::Pending) {
                throw BillingException::invoiceNotPayable();
            }

            $now = Carbon::now();
            $this->repricer->assertTransferMoment($dto->transferredAt, $payment, $invoice);

            // Owner decision 2026-11-26: a system-computed upgrade difference becomes final here,
            // re-priced from the verified transfer (or the approval, > 48h later). Same quote as the
            // console verify-preview. Applied before the payment is counted so `settled` uses it.
            $quote = $this->repricer->quote($invoice, $payment, $dto->transferredAt, CarbonImmutable::instance($now));
            if ($quote->repriced) {
                $this->applyQuote($invoice, $payment, $quote, $operator);
            }

            $payment->status = BillingPaymentStatus::Verified;
            $payment->transferred_at = $dto->transferredAt === null ? null : Carbon::instance($dto->transferredAt);
            $payment->verified_by = (int) $operator->getKey();
            $payment->verified_at = $now;
            $payment->rejection_reason = null;
            $payment->save();

            $paid = '0.000';
            $amounts = BillingPayment::query()
                ->where('billing_invoice_id', $invoice->getKey())
                ->where('status', BillingPaymentStatus::Verified->value)
                ->pluck('amount');
            foreach ($amounts as $amount) {
                $paid = bcadd($paid, (string) $amount, 3);
            }

            $settled = bccomp($paid, $invoice->total, 3) >= 0;

            if ($settled) {
                $invoice->status = BillingInvoiceStatus::Paid;
                $invoice->paid_at = $now;
                // The excess after a re-price (days only shrink) is stored, never refunded or
                // credited here: that policy is an open owner decision.
                $invoice->overpaid_amount = bccomp($paid, $invoice->total, 3) > 0 ? bcsub($paid, $invoice->total, 3) : '0.000';
                $invoice->save();
            }

            $this->audit->record(
                CentralAuditEvent::BillingPaymentVerified,
                [
                    'payment_id' => (int) $payment->getKey(),
                    'invoice_id' => (int) $invoice->getKey(),
                    'invoice_number' => $invoice->number,
                    'amount' => $payment->amount,
                    'invoice_settled' => $settled,
                    'overpaid_amount' => $settled ? $invoice->overpaid_amount : '0.000',
                    'transferred_at' => $dto->transferredAt?->toIso8601String(),
                ],
                $operator,
                $payment,
                (string) $payment->tenant_id,
            );

            // #232 spec D: a confirmed (fully paid) invoice settles its 48h window BEFORE activation,
            // which then reads the tenant row without it. A partial payment leaves the window running.
            if ($settled) {
                $this->temporaryAccess->settle($tenant, $invoice, TemporaryAccessOutcome::Verified, $operator, CarbonImmutable::instance($now));
            }

            if ($settled && $invoice->type !== BillingInvoiceType::Service) {
                $this->activate->execute($invoice, $payment, $operator);
            }

            return $payment;
        }, connectionName: $connection);
    }

    /**
     * Write the re-priced difference onto the (locked) invoice and audit old -> new. A total above
     * what was paid (a transfer earlier on the issue day than the issue moment) simply leaves the
     * invoice pending, partly paid, by the settle check that follows.
     */
    private function applyQuote(BillingInvoice $invoice, BillingPayment $payment, VerificationQuote $quote, CentralUser $operator): void
    {
        $old = ['subtotal' => $invoice->subtotal, 'tax' => $invoice->tax, 'total' => $invoice->total];

        $invoice->lines = $quote->lines;
        $invoice->subtotal = $quote->subtotal;
        $invoice->tax = $quote->tax;
        $invoice->total = $quote->total;
        $invoice->save();

        $this->audit->record(
            CentralAuditEvent::BillingInvoiceRepriced,
            [
                'invoice_id' => (int) $invoice->getKey(),
                'invoice_number' => $invoice->number,
                'payment_id' => (int) $payment->getKey(),
                'basis' => $quote->basis,
                'effective_moment' => $quote->effectiveMoment->toIso8601String(),
                'remaining_days' => $quote->remainingDays,
                'period_days' => $quote->periodDays,
                'old' => $old,
                'new' => ['subtotal' => $quote->subtotal, 'tax' => $quote->tax, 'total' => $quote->total],
                'paid_amount' => $quote->paidAmount,
                'overpaid_amount' => $quote->overpaidAmount,
                'remaining_due' => $quote->remainingDue,
            ],
            $operator,
            $invoice,
            (string) $invoice->tenant_id,
        );
    }
}
