<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\BillingPaymentStatus;
use App\Filters\Billing\PaymentStatusFilter;
use App\Filters\Billing\PaymentTenantFilter;
use App\Models\BillingPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;

/**
 * Super-admin payment review queue (central DB). Filters: status, tenant_id. The pending queue
 * is oldest-first (review in submission order), every other view newest-first.
 */
final class ListBillingPaymentsAction
{
    public function execute(Request $request): LengthAwarePaginator
    {
        $query = BillingPayment::query()->with(['tenant', 'invoice', 'verifier']);

        $filtered = app(Pipeline::class)
            ->send($query)
            ->through([PaymentStatusFilter::class, PaymentTenantFilter::class])
            ->thenReturn();

        $pendingOnly = $request->query('status') === BillingPaymentStatus::Pending->value;
        $filtered->orderBy('id', $pendingOnly ? 'asc' : 'desc');

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        return $filtered->paginate($perPage);
    }
}
