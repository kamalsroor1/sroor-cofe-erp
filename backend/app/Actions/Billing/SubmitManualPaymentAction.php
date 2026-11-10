<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\SubmitManualPaymentDTO;
use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Services\CentralAuditLogger;
use App\Support\Money\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Throwable;

/**
 * PRD-03 / ENTI-3.3: a shop submits a manual payment (InstaPay / Vodafone Cash / bank transfer)
 * with its receipt. Called from TENANT context.
 *
 * Everything is central: the payment row and the CentralMedia row live in the central DB, the file
 * on the `central_private` disk (an absolute root that is not in tenancy.filesystem.disks, so it is
 * never re-rooted under storage/tenant<id>/). The file name is server-generated
 * (ServerGeneratedFileNamer); the client's name is never used on disk.
 *
 * Rules, all checked under a row lock on the invoice (a double submit cannot create two payments):
 *  - the invoice belongs to the tenant and is `pending` (else 404 / 409);
 *  - at most one `pending` payment per invoice (422 payment_already_pending);
 *  - 0 < amount <= remaining due (total - verified payments) (422 amount_out_of_range).
 * The sender reference is kept in raw_payload (gateway_reference stays null: it is the gateway's key).
 */
final class SubmitManualPaymentAction
{
    public function __construct(private readonly CentralAuditLogger $audit) {}

    public function execute(SubmitManualPaymentDTO $dto): BillingPayment
    {
        $connection = DB::connection((string) (new BillingPayment)->getConnectionName());

        return $connection->transaction(function () use ($dto): BillingPayment {
            $invoice = BillingInvoice::query()
                ->where('tenant_id', $dto->tenantId)
                ->whereKey($dto->invoiceId)
                ->lockForUpdate()
                ->firstOrFail();

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
                'tenant_id' => $dto->tenantId,
                'amount' => $dto->amount,
                'currency' => $invoice->currency,
                'method' => $dto->method,
                'gateway' => BillingGateway::Manual,
                'status' => BillingPaymentStatus::Pending,
                'submitted_by' => $dto->submittedBy,
                'paid_at' => $dto->paidAt,
                'raw_payload' => ['payer_reference' => $dto->payerReference],
            ]);

            try {
                $media = $payment
                    ->addMedia($dto->receipt)
                    ->toMediaCollection(BillingPayment::RECEIPT_COLLECTION, BillingPayment::RECEIPT_DISK);
            } catch (FileCannotBeAdded) {
                // Defence in depth: the collection sniffs the real content type (an SVG renamed to
                // .png passes the name check but not this one). Rolls the payment row back.
                throw ValidationException::withMessages(['receipt' => (string) __('billing.errors.receipt_invalid')]);
            }

            try {
                $payment->proof_path = $media->getPathRelativeToRoot();
                $payment->save();

                $this->audit->record(
                    CentralAuditEvent::BillingPaymentSubmitted,
                    [
                        'payment_id' => (int) $payment->getKey(),
                        'invoice_id' => (int) $invoice->getKey(),
                        'invoice_number' => $invoice->number,
                        'amount' => $dto->amount,
                        'method' => $dto->method->value,
                    ],
                    null,
                    $payment,
                    $dto->tenantId,
                );
            } catch (Throwable $e) {
                // The file is already on disk but the DB is about to roll back: no orphan receipts.
                $media->delete();

                throw $e;
            }

            return $payment;
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
