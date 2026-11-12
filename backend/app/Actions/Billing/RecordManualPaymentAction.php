<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\RecordManualPaymentDTO;
use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use App\Support\Money\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * OPS-20: an operator records a payment received outside the system (cash, a transfer reported
 * on WhatsApp) against a pending subscription invoice.
 *
 * One CENTRAL transaction. The invoice row is locked first (so two concurrent records cannot both
 * pass the remaining-due check), a `pending` manual payment is created, then it goes through
 * VerifyManualPaymentAction, the single place that marks the invoice paid and activates the
 * subscription: activation therefore happens exactly once, the same way as for a shop receipt.
 * The new payment row is not visible to anyone else yet, so locking invoice -> (new) payment does
 * not invert the payment -> invoice order used elsewhere.
 *
 * Rules: the invoice is `pending` (else 409 invoice_not_payable, which also makes a repeated request
 * after settlement a harmless 409); no other payment is awaiting review (422, verify it instead);
 * 0 < amount <= total - verified payments (422 amount_out_of_range). Audited `billing_payment_recorded`
 * on top of the `billing_payment_verified` row written by the verify step.
 */
final class RecordManualPaymentAction
{
    public function __construct(
        private readonly VerifyManualPaymentAction $verify,
        private readonly CentralAuditLogger $audit,
    ) {}

    public function execute(RecordManualPaymentDTO $dto, CentralUser $operator): BillingPayment
    {
        $connection = DB::connection((string) (new BillingPayment)->getConnectionName());

        return $connection->transaction(function () use ($dto, $operator): BillingPayment {
            $invoice = BillingInvoice::query()->whereKey($dto->invoiceId)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== BillingInvoiceStatus::Pending) {
                throw BillingException::invoiceNotPayable();
            }

            $hasPending = BillingPayment::query()
                ->where('billing_invoice_id', $invoice->getKey())
                ->where('status', BillingPaymentStatus::Pending->value)
                ->exists();

            if ($hasPending) {
                throw BillingException::paymentAlreadyPending();
            }

            $remaining = $this->remaining($invoice);

            if (bccomp($dto->amount, '0', 3) <= 0 || bccomp($dto->amount, $remaining, 3) > 0) {
                throw BillingException::amountOutOfRange($remaining);
            }

            $payment = BillingPayment::query()->create([
                'billing_invoice_id' => $invoice->getKey(),
                'tenant_id' => $invoice->tenant_id,
                'amount' => $dto->amount,
                'currency' => $invoice->currency,
                'method' => $dto->method,
                'gateway' => BillingGateway::Manual,
                'status' => BillingPaymentStatus::Pending,
                'submitted_by' => null,
                'paid_at' => $dto->paidAt,
                'raw_payload' => [
                    'payer_reference' => $dto->reference,
                    'note' => $dto->note,
                    'source' => 'operator',
                    'recorded_by' => (int) $operator->getKey(),
                ],
            ]);

            $this->audit->record(
                CentralAuditEvent::BillingPaymentRecorded,
                [
                    'payment_id' => (int) $payment->getKey(),
                    'invoice_id' => (int) $invoice->getKey(),
                    'invoice_number' => $invoice->number,
                    'amount' => $dto->amount,
                    'method' => $dto->method->value,
                    'has_reference' => $dto->reference !== null,
                ],
                $operator,
                $payment,
                (string) $invoice->tenant_id,
            );

            return $this->verify->execute((int) $payment->getKey(), $operator);
        });
    }

    /** Invoice total minus the verified payments, scale 3. */
    private function remaining(BillingInvoice $invoice): string
    {
        $paid = '0.000';

        $amounts = BillingPayment::query()
            ->where('billing_invoice_id', $invoice->getKey())
            ->where('status', BillingPaymentStatus::Verified->value)
            ->pluck('amount');

        foreach ($amounts as $amount) {
            $paid = bcadd($paid, (string) $amount, 3);
        }

        return Decimal::sub($invoice->total, $paid);
    }
}
