<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Models\BillingInvoice;
use App\Models\PlanChangeRequest;

/**
 * Output of IssueSelfServiceChangeInvoiceAction: the request kept for audit / the console, and
 * either the issued invoice, or nothing because the request is held for a founder-price review.
 */
final readonly class SelfServiceChangeInvoiceResult
{
    public function __construct(
        public PlanChangeRequest $request,
        public ?BillingInvoice $invoice,
        public bool $heldForPriceReview,
    ) {}
}
