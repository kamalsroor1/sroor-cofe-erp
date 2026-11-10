<?php

declare(strict_types=1);

namespace App\Actions\Returns;

use App\Actions\Customers\ResolveWalkInCustomerAction;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Services\ReturnSourceService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * BE-01: confirmed invoices (sales) or purchases (purchase) of the active store that a
 * return can be booked against, newest first, searchable by number or party name.
 *
 * @phpstan-type SourceRow array<string, mixed>
 */
final class ListReturnSourcesAction
{
    public function __construct(
        private readonly ReturnSourceService $returnSourceService,
        private readonly ResolveWalkInCustomerAction $walkInCustomer,
    ) {}

    /**
     * @return LengthAwarePaginator<int, SourceRow>
     */
    public function execute(string $type, int $storeId, string $search, int $perPage): LengthAwarePaginator
    {
        $perPage = max(1, min(50, $perPage));
        $like = '%'.$search.'%';

        if ($type === ReturnSourceService::SALES) {
            $page = Invoice::query()
                ->with('customer:id,name,phone')
                ->where('store_id', $storeId)->where('status', 'confirmed')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('invoice_number', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like))))
                ->latest('invoice_date')->latest('id')
                ->paginate($perPage, ['id', 'invoice_number', 'invoice_date', 'customer_id', 'net_total']);

            // One grouped query for the whole page, not one per row.
            $refundable = $this->returnSourceService->cashRefundable($page->getCollection()->map(fn (Invoice $i): int => (int) $i->id)->all());

            return $page->through(fn (Invoice $i): array => $this->salesRow($i, $refundable[(int) $i->id]));
        }

        $page = Purchase::query()
            ->with('supplier:id,name')
            ->where('store_id', $storeId)->where('status', 'confirmed')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('purchase_number', 'like', $like)
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $like))))
            ->latest('purchase_date')->latest('id')
            ->paginate($perPage, ['id', 'purchase_number', 'purchase_date', 'supplier_id', 'net_total']);

        return $page->through(fn (Purchase $p): array => $this->purchaseRow($p));
    }

    /**
     * @return SourceRow
     */
    private function salesRow(Invoice $i, string $refundable): array
    {
        return [
            'id' => (int) $i->id,
            'number' => $i->invoice_number,
            'date' => $i->invoice_date?->toDateString(),
            'party_id' => (int) $i->customer_id,
            'party_name' => $i->customer?->name,
            'net_total' => (string) $i->net_total,
            'is_walk_in' => $i->customer instanceof Customer && $this->walkInCustomer->isWalkIn($i->customer),
            'cash_refundable_amount' => bccomp($refundable, '0.000', 3) < 0 ? '0.000' : $refundable,
        ];
    }

    /**
     * @return SourceRow
     */
    private function purchaseRow(Purchase $p): array
    {
        return [
            'id' => (int) $p->id,
            'number' => $p->purchase_number,
            'date' => $p->purchase_date?->toDateString(),
            'party_id' => (int) $p->supplier_id,
            'party_name' => $p->supplier?->name,
            'net_total' => (string) $p->net_total,
            'is_walk_in' => null,
            'cash_refundable_amount' => null,
        ];
    }
}
