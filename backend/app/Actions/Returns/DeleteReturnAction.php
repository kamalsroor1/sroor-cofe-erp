<?php

declare(strict_types=1);

namespace App\Actions\Returns;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\ReturnItem;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Services\CustomerBalanceService;
use App\Services\PurchaseService;
use App\Services\StockService;
use App\Services\SupplierBalanceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BE-02: deleting a return exactly reverses what creating it did, in one transaction.
 *
 * - sales return: its stock leaves the store again (`sales_return_cancel_out`) and the
 *   customer balance is recomputed;
 * - purchase return: its stock goes back (`purchase_return_cancel_in`), the item's weighted
 *   average cost returns to the snapshot taken when the return was made (or the moving-average
 *   formula when something moved the cost since) and the supplier balance is recomputed.
 *
 * A return has no treasury effect (the `refund_amount` field is not booked anywhere), so
 * there is no treasury row to reverse. Idempotent: the document is locked and a deleted
 * one is invisible to the lookup, so a second delete is a 404 and cannot reverse twice.
 */
final class DeleteReturnAction
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly PurchaseService $purchaseService,
        private readonly CustomerBalanceService $customerBalanceService,
        private readonly SupplierBalanceService $supplierBalanceService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(int $returnId): ReturnDocument
    {
        return DB::transaction(function () use ($returnId): ReturnDocument {
            // 1. Lock the document (a soft-deleted one is not found: no double reversal).
            $returnDoc = ReturnDocument::query()->whereKey($returnId)->lockForUpdate()->firstOrFail();

            // 2. Lock the party, then the items in id order (same order as every other writer).
            if ($returnDoc->customer_id !== null) {
                Customer::query()->whereKey($returnDoc->customer_id)->lockForUpdate()->first();
            }
            if ($returnDoc->supplier_id !== null) {
                Supplier::query()->whereKey($returnDoc->supplier_id)->lockForUpdate()->first();
            }

            /** @var Collection<int, ReturnItem> $lines */
            $lines = ReturnItem::query()->where('return_id', $returnDoc->id)->orderBy('id')->get();
            $itemIds = $lines->pluck('item_id')->unique()->sort()->values()->all();
            /** @var Collection<int, Item> $items */
            $items = Item::query()->whereIn('id', $itemIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if ($items->count() !== count($itemIds)) {
                throw ValidationException::withMessages(['id' => __('returns.cancel_item_unavailable')]);
            }

            $storeId = $returnDoc->store_id !== null ? (int) $returnDoc->store_id : null;

            if ($returnDoc->return_type === 'purchase_return') {
                $this->reversePurchaseReturn($returnDoc, $lines, $items, $storeId);
            } else {
                $this->reverseSalesReturn($returnDoc, $lines, $items, $storeId);
            }

            $returnDoc->delete();

            // 3. The balance formulas ignore soft-deleted returns, so recomputing is the reversal.
            if ($returnDoc->customer_id !== null) {
                $this->customerBalanceService->updateBalance((int) $returnDoc->customer_id);
            }
            if ($returnDoc->supplier_id !== null) {
                $this->supplierBalanceService->updateBalance((int) $returnDoc->supplier_id);
            }

            $this->auditLogService->log(
                action: $returnDoc->return_type === 'purchase_return' ? 'purchase_return_deleted' : 'sales_return_deleted',
                auditable: $returnDoc,
                oldValues: $returnDoc->toArray(),
                newValues: null,
            );

            return $returnDoc;
        });
    }

    /**
     * @param  Collection<int, ReturnItem>  $lines
     * @param  Collection<int, Item>  $items  locked, keyed by id
     */
    private function reverseSalesReturn(ReturnDocument $returnDoc, Collection $lines, Collection $items, ?int $storeId): void
    {
        // Refuse (422) when the returned goods are no longer there, unless the tenant allows
        // negative stock (the same `allow_negative_stock` setting StockService::deductStock
        // honours). A missing store_stocks row counts as zero. Checked per item on the summed
        // quantity, before anything is changed.
        if (! Setting::getBool('allow_negative_stock', false)) {
            foreach ($lines->groupBy('item_id') as $itemId => $itemLines) {
                /** @var Item $item */
                $item = $items[$itemId];
                $quantity = $this->sumQuantity($itemLines);

                $available = (string) $item->current_stock;
                if ($storeId !== null) {
                    $storeStock = StoreStock::query()
                        ->where('store_id', $storeId)->where('item_id', $item->id)
                        ->lockForUpdate()->first();
                    $storeQuantity = $storeStock !== null ? (string) $storeStock->quantity : '0.000';
                    if (bccomp($storeQuantity, $available, 3) < 0) {
                        $available = $storeQuantity;
                    }
                }

                if (bccomp($available, $quantity, 3) < 0) {
                    throw ValidationException::withMessages([
                        'id' => __('returns.cancel_insufficient_stock', [
                            'item' => $item->name,
                            'available' => $available,
                            'required' => $quantity,
                        ]),
                    ]);
                }
            }
        }

        foreach ($lines as $line) {
            $this->stockService->deductStock(
                item: $items[$line->item_id],
                quantity: (string) $line->quantity,
                source: $returnDoc,
                documentNumber: $returnDoc->return_number,
                movementType: 'sales_return_cancel_out',
                notes: __('returns.cancel_movement_note', ['number' => $returnDoc->return_number]),
                storeId: $storeId,
            );
        }
    }

    /**
     * @param  Collection<int, ReturnItem>  $lines
     * @param  Collection<int, Item>  $items  locked, keyed by id
     */
    private function reversePurchaseReturn(ReturnDocument $returnDoc, Collection $lines, Collection $items, ?int $storeId): void
    {
        // Decide each item's average before the stock is added back (the snapshot test looks
        // at the item's latest movement, which must still be this return's own).
        $newCosts = [];
        foreach ($lines->groupBy('item_id') as $itemId => $itemLines) {
            $newCosts[$itemId] = $this->restoredWac($returnDoc, $items[$itemId], $itemLines);
        }

        foreach ($lines as $line) {
            $this->stockService->addStock(
                item: $items[$line->item_id],
                quantity: (string) $line->quantity,
                unitCost: (string) $line->unit_price,
                source: $returnDoc,
                documentNumber: $returnDoc->return_number,
                movementType: 'purchase_return_cancel_in',
                notes: __('returns.cancel_movement_note', ['number' => $returnDoc->return_number]),
                storeId: $storeId,
            );
        }

        foreach ($newCosts as $itemId => $wac) {
            Item::query()->whereKey($itemId)->update(['weighted_avg_cost' => $wac]);
        }
    }

    /**
     * The average the item goes back to. It is the exact snapshot (`wac_before`) when this
     * return is still the item's latest cost-affecting movement and replaying the return
     * from the snapshot lands on the current average (nobody changed the cost since).
     * Otherwise (legacy row without a snapshot, or the cost moved) the moving-average
     * formula adds the returned goods back at their refund price.
     *
     * @param  Collection<int, ReturnItem>  $itemLines  this return's lines of the item, in id order
     */
    private function restoredWac(ReturnDocument $returnDoc, Item $item, Collection $itemLines): string
    {
        $quantity = $this->sumQuantity($itemLines);
        $currentWac = $this->purchaseService->effectiveWeightedAverageCost($item);

        /** @var ReturnItem $first */
        $first = $itemLines->first();

        if ($first->wac_before !== null && $this->isOwnLatestMovement($returnDoc, $item)) {
            $stock = bcadd((string) $item->current_stock, $quantity, 3);
            $wac = (string) $first->wac_before;
            foreach ($itemLines as $line) {
                $wac = $this->purchaseService->reverseWeightedAverageCost(
                    currentStock: $stock,
                    currentWac: $wac,
                    removedQuantity: (string) $line->quantity,
                    removedValuation: bcmul((string) $line->quantity, (string) $line->unit_price, 6),
                ) ?? $wac;
                $stock = bcsub($stock, (string) $line->quantity, 3);
            }

            if (bccomp($wac, $currentWac, 3) === 0) {
                return (string) $first->wac_before;
            }
        }

        $stock = (string) $item->current_stock;
        $wac = $currentWac;
        foreach ($itemLines as $line) {
            $wac = $this->purchaseService->calculateWeightedAverageCost($stock, $wac, (string) $line->quantity, (string) $line->unit_price);
            $stock = bcadd($stock, (string) $line->quantity, 3);
        }

        return $wac;
    }

    private function isOwnLatestMovement(ReturnDocument $returnDoc, Item $item): bool
    {
        $latest = StockMovement::query()
            ->where('item_id', $item->id)
            ->whereNotIn('movement_type', ['transfer_out', 'transfer_in', 'transfer_reversal_out', 'transfer_reversal_in'])
            ->orderByDesc('id')
            ->first(['id', 'movement_type', 'source_type', 'source_id']);

        return $latest !== null
            && $latest->source_type === ReturnDocument::class
            && (int) $latest->source_id === (int) $returnDoc->id
            && $latest->movement_type === 'purchase_return_out';
    }

    /**
     * @param  Collection<int, ReturnItem>  $lines
     */
    private function sumQuantity(Collection $lines): string
    {
        $total = '0.000';
        foreach ($lines as $line) {
            $total = bcadd($total, (string) $line->quantity, 3);
        }

        return $total;
    }
}
