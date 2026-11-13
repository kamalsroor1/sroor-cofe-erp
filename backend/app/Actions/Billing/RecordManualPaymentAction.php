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
 *
 * Idempotency (OPS-20 review), both checked under the invoice lock so two concurrent clicks are
 * serialised:
 *  - `idempotency_key` (optional, one UUID per opened dialog, stored in raw_payload): a request whose
 *    key was already recorded on this invoice returns THAT payment and writes nothing;
 *  - independent of the key: the same amount + method + reference on the same invoice, recorded
 *    (pending or verified) within the last DUPLICATE_WINDOW_MINUTES, is refused with 409
 *    billing.payment_duplicate. A genuine second identical payment waits out the window or carries a
 *    different reference.
 */
final class RecordManualPaymentAction
{
    private const DUPLICATE_WINDOW_MINUTES = 10;

    public function __construct(
        private readonly VerifyManualPaymentAction $verify,
        private readonly CentralAuditLogger $audit,
    ) {}

    public function execute(RecordManualPaymentDTO $dto, CentralUser $operator): BillingPayment
    {
        $connection = DB::connection((string) (new BillingPayment)->getConnectionName());

        return $connection->transaction(function () use ($dto, $operator): BillingPayment {
            $invoice = BillingInvoice::query()->whereKey($dto->invoiceId)->lockForUpdate()->firstOrFail();

            // Before the status check: a retry after the payment settled the invoice is a replay, not a 409.
            $replayed = $this->alreadyRecorded($invoice, $dto->idempotencyKey);
            if ($replayed instanceof BillingPayment) {
                return $replayed;
            }

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

            $this->assertNotDuplicate($invoice, $dto);

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
                    'idempotency_key' => $dto->idempotencyKey,
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

    /** The payment this invoice already recorded with the same idempotency key, if any. */
    private function alreadyRecorded(BillingInvoice $invoice, ?string $key): ?BillingPayment
    {
        if ($key === null) {
            return null;
        }

        $payments = BillingPayment::query()
            ->where('billing_invoice_id', $invoice->getKey())
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            if (($payment->raw_payload['idempotency_key'] ?? null) === $key) {
                return $payment;
            }
        }

        return null;
    }

    /** 409 when the same amount + method + reference was recorded on this invoice moments ago. */
    private function assertNotDuplicate(BillingInvoice $invoice, RecordManualPaymentDTO $dto): void
    {
        $recent = BillingPayment::query()
            ->where('billing_invoice_id', $invoice->getKey())
            ->whereIn('status', [BillingPaymentStatus::Verified->value, BillingPaymentStatus::Pending->value])
            ->where('method', $dto->method->value)
            ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
            ->get();

        foreach ($recent as $payment) {
            if (bccomp((string) $payment->amount, $dto->amount, 3) === 0
                && ($payment->raw_payload['payer_reference'] ?? null) === $dto->reference) {
                throw BillingException::duplicatePayment();
            }
        }
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
