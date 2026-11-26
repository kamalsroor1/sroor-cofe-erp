<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\VerificationQuote;
use App\DTOs\Billing\VerifyManualPaymentDTO;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingPaymentStatus;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Services\Billing\UpgradeDifferenceRepricer;
use Carbon\CarbonImmutable;

/**
 * #232 owner decision 2026-11-26, console Verify screen: what verifying this payment with this
 * transfer moment would settle, BEFORE approving. Read-only (no lock, no write): the numbers come
 * from UpgradeDifferenceRepricer::quote(), the exact code VerifyManualPaymentAction applies, so the
 * preview equals what Verify writes when nothing changes in between. Same refusals as Verify:
 * 409 payment_not_pending / invoice_not_payable, 422 on `transferred_at`.
 */
final class PreviewPaymentVerificationAction
{
    public function __construct(private readonly UpgradeDifferenceRepricer $repricer) {}

    public function execute(VerifyManualPaymentDTO $dto): VerificationQuote
    {
        $payment = BillingPayment::query()->whereKey($dto->paymentId)->firstOrFail();

        if ($payment->status !== BillingPaymentStatus::Pending) {
            throw BillingException::paymentNotPending();
        }

        $invoice = BillingInvoice::query()->whereKey($payment->billing_invoice_id)->firstOrFail();

        if ($invoice->status !== BillingInvoiceStatus::Pending) {
            throw BillingException::invoiceNotPayable();
        }

        $this->repricer->assertTransferMoment($dto->transferredAt, $payment, $invoice);

        return $this->repricer->quote($invoice, $payment, $dto->transferredAt, CarbonImmutable::now());
    }
}
