<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PRD-03 / ENTI-3.4: a super-admin verifies a pending manual payment.
 *
 * One CENTRAL transaction. Lock order is always payment -> invoice -> tenant -> subscription
 * (ActivateSubscriptionAction), the same order every other billing Action uses.
 *
 * Idempotent: verifying an already verified payment returns it untouched, so a double click or a
 * retry can never extend the subscription twice. A rejected payment, or a void/paid invoice, is a 409.
 * The invoice becomes `paid` once its verified payments reach the total; a partial payment leaves it
 * `pending` for the next receipt. Plan / renewal / upgrade invoices then activate the subscription;
 * a `service` invoice is only marked paid (no subscription change).
 */
final class VerifyManualPaymentAction
{
    public function __construct(
        private readonly ActivateSubscriptionAction $activate,
        private readonly CentralAuditLogger $audit,
    ) {}

    public function execute(int $paymentId, CentralUser $operator): BillingPayment
    {
        $connection = DB::connection((string) (new BillingPayment)->getConnectionName());

        return $connection->transaction(function () use ($paymentId, $operator): BillingPayment {
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

            $payment->status = BillingPaymentStatus::Verified;
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
                ],
                $operator,
                $payment,
                (string) $payment->tenant_id,
            );

            if ($settled && $invoice->type !== BillingInvoiceType::Service) {
                $this->activate->execute($invoice, $payment, $operator);
            }

            return $payment;
        });
    }
}
