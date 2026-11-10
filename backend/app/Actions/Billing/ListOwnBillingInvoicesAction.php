<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\BillingInvoiceStatus;
use App\Models\BillingInvoice;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Tenant context: the shop's OWN subscription invoices, newest first. The tenant id comes from
 * the resolved tenant, never from the request. Drafts are internal and never shown.
 */
final class ListOwnBillingInvoicesAction
{
    /**
     * @return LengthAwarePaginator<int, BillingInvoice>
     */
    public function execute(Tenant $tenant, int $perPage): LengthAwarePaginator
    {
        return BillingInvoice::query()
            ->with('payments')
            ->where('tenant_id', $tenant->getKey())
            ->where('status', '!=', BillingInvoiceStatus::Draft->value)
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $perPage)));
    }
}
