<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\RejectManualPaymentDTO;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * PRD-03 / ENTI-3.4: a super-admin rejects a pending manual payment with a reason (3-500 chars,
 * validated by RejectBillingPaymentRequest). The invoice stays `pending`, so the shop can submit a
 * corrected receipt. Only a `pending` payment can be rejected (else 409). Central transaction,
 * payment then invoice locked.
 */
final class RejectManualPaymentAction
{
    public function __construct(private readonly CentralAuditLogger $audit) {}

    public function execute(RejectManualPaymentDTO $dto, CentralUser $operator): BillingPayment
    {
        $connection = DB::connection((string) (new BillingPayment)->getConnectionName());

        return $connection->transaction(function () use ($dto, $operator): BillingPayment {
            $payment = BillingPayment::query()->whereKey($dto->paymentId)->lockForUpdate()->firstOrFail();

            if ($payment->status !== BillingPaymentStatus::Pending) {
                throw BillingException::paymentNotPending();
            }

            $invoice = BillingInvoice::query()->whereKey($payment->billing_invoice_id)->lockForUpdate()->firstOrFail();

            $payment->status = BillingPaymentStatus::Rejected;
            $payment->rejection_reason = $dto->reason;
            $payment->verified_by = (int) $operator->getKey();
            $payment->verified_at = now();
            $payment->save();

            $this->audit->record(
                CentralAuditEvent::BillingPaymentRejected,
                [
                    'payment_id' => (int) $payment->getKey(),
                    'invoice_id' => (int) $invoice->getKey(),
                    'invoice_number' => $invoice->number,
                    'amount' => $payment->amount,
                    'reason' => $dto->reason,
                ],
                $operator,
                $payment,
                (string) $payment->tenant_id,
            );

            return $payment;
        });
    }
}
