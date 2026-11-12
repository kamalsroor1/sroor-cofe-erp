<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * SELL-07: a shop withdraws its OWN still-pending manual payment (typically a wrong amount) so it can
 * submit a corrected one without waiting for the operator. Marked `rejected` with a fixed reason
 * (the operator-facing history keeps it); the invoice stays `pending`. Only a `pending` payment of the
 * resolved tenant can be withdrawn (else 404 / 409). Central transaction, payment then invoice locked.
 */
final class CancelOwnBillingPaymentAction
{
    public function __construct(private readonly CentralAuditLogger $audit) {}

    public function execute(string $tenantId, int $paymentId): BillingPayment
    {
        $connection = DB::connection((string) (new BillingPayment)->getConnectionName());

        return $connection->transaction(function () use ($tenantId, $paymentId): BillingPayment {
            $payment = BillingPayment::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status !== BillingPaymentStatus::Pending) {
                throw BillingException::paymentNotPending();
            }

            $invoice = BillingInvoice::query()->whereKey($payment->billing_invoice_id)->lockForUpdate()->firstOrFail();

            $payment->status = BillingPaymentStatus::Rejected;
            $payment->rejection_reason = (string) __('billing.messages.payment_cancelled_by_shop');
            $payment->verified_at = now();
            $payment->save();

            $this->audit->record(
                CentralAuditEvent::BillingPaymentRejected,
                [
                    'payment_id' => (int) $payment->getKey(),
                    'invoice_id' => (int) $invoice->getKey(),
                    'invoice_number' => $invoice->number,
                    'amount' => $payment->amount,
                    'cancelled_by_shop' => true,
                ],
                null,
                $payment,
                $tenantId,
            );

            return $payment;
        });
    }
}
