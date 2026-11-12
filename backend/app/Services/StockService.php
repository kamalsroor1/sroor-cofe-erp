<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\Stock\InsufficientStockException;
use App\Models\Item;
use App\Models\StockDeposit;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Services\Settings\TenantSettings;
use App\Support\Database\TransactionRetry;
use App\Support\TenantClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(
        private readonly TenantClock $tenantClock,
        private readonly DocumentNumberService $documentNumbers,
    ) {}

    /**
     * The user a stock row is attributed to: the authenticated user, else the explicit
     * actor passed by a job/command, else NULL ("system"). Never a made-up user id.
     */
    private function actorId(?int $explicit): ?int
    {
        $authId = Auth::id();

        return $authId !== null ? (int) $authId : $explicit;
    }

    /**
     * Check if item has enough available stock (globally or in a specific store/van)
     */
    public function hasAvailableStock(Item $item, string $requestedQuantity, ?int $storeId = null): bool
    {
        if ($storeId) {
            $stock = StoreStock::where('store_id', $storeId)->where('item_id', $item->id)->first();
            if (! $stock) {
                return false;
            }

            return bccomp((string) $stock->quantity, $requestedQuantity, 3) >= 0;
        }

        return bccomp((string) $item->current_stock, $requestedQuantity, 3) >= 0;
    }

    /**
     * BUG-07: take the row locks of a multi-line stock operation up front: every `items` row by
     * id ascending, then every existing `store_stocks` row by (item_id, store_id) ascending.
     *
     * THE GLOBAL LOCK ORDER (every money/stock writer follows it; a flow skips the steps it has
     * no row for):
     *   1. the existing document being changed (invoice / purchase / transfer / return / shift);
     *   2. the party: customer or supplier (the balance recompute re-locks it later, harmless);
     *   3. items by id, then store_stocks by (item_id, store_id)   <- this method;
     *   4. branch rows of the treasury scope (`stores`, TreasuryService::lockTreasuryScope);
     *   5. document_sequences: the document number first, then payment / expense numbers.
     * Document before party is the order the existing customer flows already used (update and
     * cancel invoice, sales return, receipts); the create flows have no existing document and
     * start at 2. Call this inside the transaction right after steps 1-2, before the first line
     * is processed and before any number is allocated.
     *
     * Without it each flow locked rows in its own line order (cart order, transfer line order),
     * so two sales of [A, B] and [B, A], or two opposing multi-item transfers, could each hold
     * one row and wait for the other (1213 deadlock / 1205 timeout). The per-line code keeps its
     * own lockForUpdate() calls: re-locking a row this transaction already holds is free, and
     * every row read then changed is still read under a lock.
     *
     * Rows are locked one by one by their key so the acquisition order is exactly the sorted
     * order (a multi-row IN scan locks in index order, which for store_stocks is store first).
     * A missing store_stocks pair is skipped here; the line code creates it as before.
     *
     * @param  iterable<int|string|null>  $itemIds
     * @param  iterable<int|string|null>  $storeIds
     */
    public function lockStockRows(iterable $itemIds, iterable $storeIds): void
    {
        $items = $this->sortedIds($itemIds);
        $stores = $this->sortedIds($storeIds);

        foreach ($items as $itemId) {
            DB::table('items')->where('id', $itemId)->lockForUpdate()->value('id');
        }

        foreach ($items as $itemId) {
            foreach ($stores as $storeId) {
                DB::table('store_stocks')->where('item_id', $itemId)->where('store_id', $storeId)->lockForUpdate()->value('id');
            }
        }
    }

    /**
     * @param  iterable<int|string|null>  $ids
     * @return list<int>
     */
    private function sortedIds(iterable $ids): array
    {
        $unique = [];
        foreach ($ids as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $unique[(int) $id] = (int) $id;
            }
        }
        ksort($unique);

        return array_values($unique);
    }

    /**
     * Materialise the zero row for a (store, item) pair that was never seeded and lock it.
     * Only reached for legacy data; new stores/items are seeded by StoreStockInitializer.
     * insertOrIgnore + a withTrashed re-read keep a soft-deleted row from hitting the unique
     * index. When negative stock is disallowed the caller throws and the surrounding
     * transaction rolls the zero row back with everything else.
     */
    private function createMissingStoreStock(int $storeId, Item $item): StoreStock
    {
        DB::table('store_stocks')->insertOrIgnore([
            'store_id' => $storeId,
            'item_id' => $item->id,
            'quantity' => '0.000',
            'min_stock' => (string) $item->min_stock_level,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return StoreStock::withTrashed()
            ->where('store_id', $storeId)
            ->where('item_id', $item->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Deduct stock securely with row locking (at store level and master item level)
     */
    public function deductStock(
        Item $item,
        string $quantity,
        Model $source,
        string $documentNumber,
        string $movementType = 'sales_out',
        ?string $notes = null,
        ?int $storeId = null,
        ?int $actorId = null,
    ): StockMovement {
        // 1. Lock master item row
        $lockedItem = Item::where('id', $item->id)->lockForUpdate()->firstOrFail();

        // BE-04: a missing store_stocks row counts as zero stock, never as "skip the check".
        $allowNegative = app(TenantSettings::class)->allowNegativeStock();

        // Item-level check is skipped only when allow_negative_stock is on (intended; same as DeleteReturnAction).
        if (! $allowNegative && bccomp((string) $lockedItem->current_stock, $quantity, 3) < 0) {
            throw InsufficientStockException::forItem($lockedItem->name, (string) $lockedItem->current_stock);
        }

        // If no storeId passed, try to resolve from user session/default or main store
        if (! $storeId) {
            $storeId = Auth::user()?->getCurrentStore()?->id ?? Store::getMainStore()?->id;
        }

        // 2. Lock and update StoreStock if storeId is determined
        if ($storeId) {
            $storeStock = StoreStock::withTrashed()
                ->where('store_id', $storeId)
                ->where('item_id', $lockedItem->id)
                ->lockForUpdate()
                ->first()
                ?? $this->createMissingStoreStock($storeId, $lockedItem);

            if (! $allowNegative && bccomp((string) $storeStock->quantity, $quantity, 3) < 0) {
                $storeName = (string) (Store::query()->whereKey($storeId)->value('name') ?? "#{$storeId}");
                throw InsufficientStockException::forStore(
                    $lockedItem->name,
                    $storeName,
                    (string) $storeStock->quantity,
                    (string) $lockedItem->unit,
                );
            }

            $storeStock->quantity = bcsub((string) $storeStock->quantity, $quantity, 3);
            $storeStock->save();
        }

        // 3. Deduct from master item stock
        $stockBefore = (string) $lockedItem->current_stock;
        $stockAfter = bcsub($stockBefore, $quantity, 3);
        $lockedItem->current_stock = $stockAfter;
        $lockedItem->save();

        return StockMovement::create([
            'item_id' => $lockedItem->id,
            'store_id' => $storeId,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'unit_cost' => $lockedItem->cost_price,
            'source_type' => get_class($source),
            'source_id' => $source->getKey(),
            'document_number' => $documentNumber,
            'user_id' => $this->actorId($actorId),
            'notes' => $notes ?? "صرف مخزني للمستند {$documentNumber}",
        ]);
    }

    /**
     * Add stock securely (at store level and master item level)
     */
    public function addStock(
        Item $item,
        string $quantity,
        string $unitCost,
        Model $source,
        string $documentNumber,
        string $movementType = 'purchase_in',
        ?string $notes = null,
        ?int $storeId = null,
        ?int $actorId = null,
    ): StockMovement {
        // 1. Lock master item row
        $lockedItem = Item::where('id', $item->id)->lockForUpdate()->firstOrFail();

        // If no storeId passed, resolve
        if (! $storeId) {
            $storeId = Auth::user()?->getCurrentStore()?->id ?? Store::getMainStore()?->id;
        }

        // 2. Lock and update StoreStock if storeId is determined
        if ($storeId) {
            $storeStock = StoreStock::firstOrCreate(
                [
                    'store_id' => $storeId,
                    'item_id' => $lockedItem->id,
                ],
                [
                    'quantity' => '0.000',
                    'min_stock' => $lockedItem->min_stock_level,
                    'custom_selling_price' => null,
                ]
            );

            // Re-lock for update
            $storeStock = StoreStock::where('id', $storeStock->id)->lockForUpdate()->first();
            $storeStock->quantity = bcadd((string) $storeStock->quantity, $quantity, 3);
            $storeStock->save();
        }

        // 3. Add to master item stock
        $stockBefore = (string) $lockedItem->current_stock;
        $stockAfter = bcadd($stockBefore, $quantity, 3);
        $lockedItem->current_stock = $stockAfter;
        $lockedItem->save();

        return StockMovement::create([
            'item_id' => $lockedItem->id,
            'store_id' => $storeId,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'unit_cost' => $unitCost,
            'source_type' => get_class($source),
            'source_id' => $source->getKey(),
            'document_number' => $documentNumber,
            'user_id' => $this->actorId($actorId),
            'notes' => $notes ?? "إضافة مخزنية للمستند {$documentNumber}",
        ]);
    }

    /**
     * Manual deposit / opening balance / adjustment
     */
    public function depositStock(
        Item $item,
        string $quantity,
        string $costPrice,
        string $depositType = 'manual_deposit',
        ?string $reason = null,
        ?string $depositDate = null,
        ?int $storeId = null,
        ?int $actorId = null,
    ): StockDeposit {
        $lockedItem = Item::where('id', $item->id)->lockForUpdate()->firstOrFail();

        if (! $storeId) {
            $storeId = Auth::user()?->getCurrentStore()?->id ?? Store::getMainStore()?->id;
        }

        if ($storeId) {
            $storeStock = StoreStock::firstOrCreate(
                [
                    'store_id' => $storeId,
                    'item_id' => $lockedItem->id,
                ],
                [
                    'quantity' => '0.000',
                    'min_stock' => $lockedItem->min_stock_level,
                    'custom_selling_price' => null,
                ]
            );

            $storeStock = StoreStock::where('id', $storeStock->id)->lockForUpdate()->first();
            $storeStock->quantity = bcadd((string) $storeStock->quantity, $quantity, 3);
            $storeStock->save();
        }

        $stockBefore = (string) $lockedItem->current_stock;
        $stockAfter = bcadd($stockBefore, $quantity, 3);

        $lockedItem->current_stock = $stockAfter;
        if (bccomp($costPrice, '0.000', 3) > 0) {
            $lockedItem->cost_price = $costPrice;
        }
        $lockedItem->save();

        $deposit = StockDeposit::create([
            'item_id' => $lockedItem->id,
            'user_id' => $this->actorId($actorId),
            'deposit_type' => $depositType,
            'quantity' => $quantity,
            'cost_price' => $costPrice,
            'reason' => $reason,
            'deposit_date' => $depositDate ?? $this->tenantClock->businessDate(),
        ]);

        StockMovement::create([
            'item_id' => $lockedItem->id,
            'store_id' => $storeId,
            'movement_type' => 'stock_deposit_in',
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'unit_cost' => $costPrice,
            'source_type' => StockDeposit::class,
            'source_id' => $deposit->id,
            'document_number' => "DEP-{$deposit->id}",
            'user_id' => $this->actorId($actorId),
            'notes' => $reason ?? 'إيداع مخزني يدوي',
        ]);

        return $deposit;
    }

    /**
     * Physical Stock Count Adjustment (تسوية جردية وتصحيح رصيد المخزن)
     * Atomically corrects item quantity to match actual physical count and logs a StockMovement.
     */
    public function adjustStock(
        Item $item,
        string $actualQuantity,
        string $reason,
        ?int $storeId = null,
        ?int $actorId = null,
    ): StockMovement {
        // BUG-04: the day's ADJ counter row exists before the transaction takes its first read.
        $this->documentNumbers->prepareDated('ADJ', StockMovement::class, 'document_number');
        $itemId = (int) $item->id;

        // BUG-07: restarted as a whole on a lock conflict; only the id and plain input are captured.
        return TransactionRetry::run(function () use ($itemId, $actualQuantity, $reason, $storeId, $actorId) {
            $lockedItem = Item::where('id', $itemId)->lockForUpdate()->firstOrFail();

            if (! $storeId) {
                $storeId = Auth::user()?->getCurrentStore()?->id ?? Store::getMainStore()?->id;
            }

            $storeStock = null;
            $currentStoreQty = (string) $lockedItem->current_stock;

            if ($storeId) {
                $storeStock = StoreStock::firstOrCreate(
                    [
                        'store_id' => $storeId,
                        'item_id' => $lockedItem->id,
                    ],
                    [
                        'quantity' => '0.000',
                        'min_stock' => $lockedItem->min_stock_level,
                        'custom_selling_price' => null,
                    ]
                );

                $storeStock = StoreStock::where('id', $storeStock->id)->lockForUpdate()->first();
                $currentStoreQty = (string) $storeStock->quantity;
            }

            $diff = bcsub($actualQuantity, $currentStoreQty, 3);

            if (bccomp($diff, '0.000', 3) === 0) {
                throw BusinessRuleException::make('inventory.adjustment_no_difference', ['quantity' => $actualQuantity]);
            }

            $stockBefore = (string) $lockedItem->current_stock;
            $stockAfter = bcadd($stockBefore, $diff, 3);

            if (bccomp($stockAfter, '0.000', 3) < 0) {
                throw BusinessRuleException::make('inventory.adjustment_negative_total', ['balance' => $stockAfter]);
            }

            // Update StoreStock
            if ($storeStock) {
                $storeStock->quantity = $actualQuantity;
                $storeStock->save();
            }

            // Update Master Item Stock
            $lockedItem->current_stock = $stockAfter;
            $lockedItem->save();

            $isSurplus = bccomp($diff, '0.000', 3) > 0;
            $movementType = $isSurplus ? 'stock_adjustment_in' : 'stock_adjustment_out';
            $adjQty = $isSurplus ? $diff : bcmul($diff, '-1', 3);
            $docNumber = $this->documentNumbers->nextDated('ADJ', 4, StockMovement::class, 'document_number');

            $movement = StockMovement::create([
                'item_id' => $lockedItem->id,
                'store_id' => $storeId,
                'movement_type' => $movementType,
                'quantity' => $adjQty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'unit_cost' => $lockedItem->cost_price,
                'source_type' => Item::class,
                'source_id' => $lockedItem->id,
                'document_number' => $docNumber,
                'user_id' => $this->actorId($actorId),
                'notes' => "تسوية جردية: {$reason} (الرصيد قبل: {$currentStoreQty} | الفعلي الجديد: {$actualQuantity})",
            ]);

            app(ActivityLogService::class)->log(
                module: 'inventory',
                action: 'adjusted',
                description: "تسوية جردية لصنف [{$lockedItem->name}] إلى {$actualQuantity} {$lockedItem->unit} - السبب: {$reason}",
                subject: $lockedItem,
                properties: [
                    'actual_quantity' => $actualQuantity,
                    'diff' => $diff,
                    'movement_type' => $movementType,
                    'reason' => $reason,
                ],
                storeId: $storeId
            );

            return $movement;
        });
    }
}
