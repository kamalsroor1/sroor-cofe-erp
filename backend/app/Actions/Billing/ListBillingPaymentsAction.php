<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\BillingPaymentStatus;
use App\Filters\Billing\PaymentStatusFilter;
use App\Filters\Billing\PaymentTenantFilter;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;

/**
 * Super-admin payment review queue (central DB). Filters: status, tenant_id. The pending queue
 * is oldest-first (review in submission order), every other view newest-first.
 *
 * #232 UI §4.1: in the pending queue, payments whose invoice has a RUNNING 48h temporary access
 * come first, nearest end first; then the rest (manual review) in submission order.
 */
final class ListBillingPaymentsAction
{
    public function execute(Request $request): LengthAwarePaginator
    {
        $query = BillingPayment::query()->with(ShowBillingPaymentAction::RELATIONS);

        $filtered = app(Pipeline::class)
            ->send($query)
            ->through([PaymentStatusFilter::class, PaymentTenantFilter::class])
            ->thenReturn();

        $pendingOnly = $request->query('status') === BillingPaymentStatus::Pending->value;
        if ($pendingOnly) {
            $now = now();
            $active = 'billing_invoices.temporary_access_settled_at is null and billing_invoices.temporary_access_ends_at > ?';

            $filtered
                ->orderBy(BillingInvoice::query()
                    ->selectRaw('case when '.$active.' then 0 else 1 end', [$now])
                    ->whereColumn('billing_invoices.id', 'billing_payments.billing_invoice_id'))
                ->orderBy(BillingInvoice::query()
                    ->selectRaw('case when '.$active.' then billing_invoices.temporary_access_ends_at end', [$now])
                    ->whereColumn('billing_invoices.id', 'billing_payments.billing_invoice_id'));
        }

        $filtered->orderBy('id', $pendingOnly ? 'asc' : 'desc');

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        return $filtered->paginate($perPage);
    }
}
