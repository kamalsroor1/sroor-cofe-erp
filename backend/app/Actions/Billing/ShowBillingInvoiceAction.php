<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\BillingInvoice;

/**
 * Super-admin: one invoice with its tenant, issuer and payments (verifier included), for print.
 */
final class ShowBillingInvoiceAction
{
    public function execute(int $invoiceId): BillingInvoice
    {
        return BillingInvoice::query()
            ->with(['tenant', 'issuer', 'payments.verifier'])
            ->findOrFail($invoiceId);
    }
}
