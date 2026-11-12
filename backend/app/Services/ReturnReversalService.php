<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\ReturnItem;
use App\Models\StockMovement;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Services\Settings\TenantSettings;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * BE-02: the shared engine that undoes what creating a return did to stock and cost. Used by
 * DeleteReturnAction (deleting a return) and ReverseDeletedReturnAction (the
 * returns:report-unreversed-deletions --fix command, for returns deleted before the reversal existed).
 *
 * - sales return: its stock leaves the store again (`sales_return_cancel_out`);
 * - purchase return: its stock goes back (`purchase_return_cancel_in`) and the item's weighted
 *   average cost returns to the snapshot taken when the return was made (or the moving-average
 *   formula when something moved the cost since).
 *
 * reverseStock() MUST run inside the caller's transaction (TransactionRetry::run) with the return row already
 * locked (lock order: document -> party -> items by id). A return has no treasury effect.
 */
final class ReturnReversalService
{
    /** Movement types written by the reversal, one per return type. */
    public const REVERSAL_MOVEMENTS = ['sales_return_cancel_out', 'purchase_return_cancel_in'];

    public function __construct(
        private readonly StockService $stockService,
        private readonly PurchaseService $purchaseService,
        private readonly CustomerBalanceService $customerBalanceService,
        private readonly SupplierBalanceService $supplierBalanceService,
    ) {}

    /**
     * Put the return's stock (and, for a purchase return, the average cost) back.
     *
     * @throws ValidationException 422 when a line's item is gone or a sales return's goods are no longer in stock
     */
    public function reverseStock(ReturnDocument $returnDoc): void
    {
        // Lock the party, then the items in id order (same order as every other writer).
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

        // BUG-07: the store_stocks rows too, in the global (item_id, store_id) order.
        $this->stockService->lockStockRows($itemIds, [$storeId]);

        if ($returnDoc->return_type === 'purchase_return') {
            $this->reversePurchaseReturn($returnDoc, $lines, $items, $storeId);
        } else {
            $this->reverseSalesReturn($returnDoc, $lines, $items, $storeId);
        }
    }

    /**
     * The balance formulas ignore soft-deleted returns, so recomputing (once the return is
     * deleted) is the balance reversal.
     */
    public function refreshBalances(ReturnDocument $returnDoc): void
    {
        if ($returnDoc->customer_id !== null) {
            $this->customerBalanceService->updateBalance((int) $returnDoc->customer_id);
        }
        if ($returnDoc->supplier_id !== null) {
            $this->supplierBalanceService->updateBalance((int) $returnDoc->supplier_id);
        }
    }

    /** Whether a reversal movement already exists for this return (it was reversed when deleted). */
    public function hasReversalMovement(int $returnId): bool
    {
        return StockMovement::query()
            ->where('source_type', ReturnDocument::class)
            ->where('source_id', $returnId)
            ->whereIn('movement_type', self::REVERSAL_MOVEMENTS)
            ->exists();
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
        if (! app(TenantSettings::class)->allowNegativeStock()) {
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
