<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * PRD-03: void a subscription invoice. Only a `pending` invoice with no verified payment and no
 * payment waiting for review can be voided (409 otherwise). The number is never reused (gap-free
 * numbering keeps the voided row). Central transaction with the invoice locked: a payment being
 * verified at the same moment either wins (the void then fails) or loses.
 */
final class VoidSubscriptionInvoiceAction
{
    public function __construct(private readonly CentralAuditLogger $audit) {}

    public function execute(int $invoiceId, CentralUser $operator): BillingInvoice
    {
        $connection = DB::connection((string) (new BillingInvoice)->getConnectionName());

        return $connection->transaction(function () use ($invoiceId, $operator): BillingInvoice {
            $invoice = BillingInvoice::query()->whereKey($invoiceId)->lockForUpdate()->firstOrFail();

            $blocking = BillingPayment::query()
                ->where('billing_invoice_id', $invoice->getKey())
                ->whereIn('status', [BillingPaymentStatus::Verified->value, BillingPaymentStatus::Pending->value])
                ->exists();

            if ($invoice->status !== BillingInvoiceStatus::Pending || $blocking) {
                throw BillingException::invoiceNotVoidable();
            }

            $invoice->status = BillingInvoiceStatus::Void;
            $invoice->voided_at = now();
            $invoice->save();

            $this->audit->record(
                CentralAuditEvent::BillingInvoiceVoided,
                ['invoice_id' => (int) $invoice->getKey(), 'number' => $invoice->number, 'type' => $invoice->type->value],
                $operator,
                $invoice,
                (string) $invoice->tenant_id,
            );

            return $invoice;
        });
    }
}
