<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\BillingInvoiceStatus;
use App\Models\BillingInvoice;
use App\Models\Tenant;

/**
 * Tenant context: one of the shop's own invoices. Another tenant's id is a 404 (the lookup is
 * scoped by tenant_id, so existence is never revealed).
 */
final class ShowOwnBillingInvoiceAction
{
    public function execute(Tenant $tenant, int $invoiceId): BillingInvoice
    {
        return BillingInvoice::query()
            ->with('payments')
            ->where('tenant_id', $tenant->getKey())
            ->where('status', '!=', BillingInvoiceStatus::Draft->value)
            ->findOrFail($invoiceId);
    }
}
