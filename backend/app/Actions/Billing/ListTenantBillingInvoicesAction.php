<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\BillingInvoice;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Super-admin: one tenant's subscription invoices (newest first) plus its subscription summary.
 */
final class ListTenantBillingInvoicesAction
{
    /**
     * @return array{tenant: Tenant, subscription: Subscription|null, invoices: LengthAwarePaginator<int, BillingInvoice>}
     */
    public function execute(string $tenantId, int $perPage): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        $subscription = Subscription::query()
            ->with('plan')
            ->where('tenant_id', $tenant->getKey())
            ->orderByDesc('id')
            ->first();

        $invoices = BillingInvoice::query()
            ->with(['payments', 'issuer'])
            ->where('tenant_id', $tenant->getKey())
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $perPage)));

        return ['tenant' => $tenant, 'subscription' => $subscription, 'invoices' => $invoices];
    }
}
