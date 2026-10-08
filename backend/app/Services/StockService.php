<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockDeposit;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockService
{
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
     * Deduct stock securely with row locking (at store level and master item level)
     */
    public function deductStock(
        Item $item,
        string $quantity,
        Model $source,
        string $documentNumber,
        string $movementType = 'sales_out',
        ?string $notes = null,
        ?int $storeId = null
    ): StockMovement {
        // 1. Lock master item row
        $lockedItem = Item::where('id', $item->id)->lockForUpdate()->firstOrFail();

        if (bccomp((string) $lockedItem->current_stock, $quantity, 3) < 0) {
            throw new Exception("رصيد الصنف [{$lockedItem->name}] غير كافٍ. المتوفر حالياً: {$lockedItem->current_stock}");
        }

        // If no storeId passed, try to resolve from user session/default or main store
        if (! $storeId) {
            $storeId = Auth::user()?->getCurrentStore()?->id ?? Store::getMainStore()?->id;
        }

        // 2. Lock and update StoreStock if storeId is determined
        if ($storeId) {
            $store = Store::find($storeId);
            $storeStock = StoreStock::where('store_id', $storeId)
                ->where('item_id', $lockedItem->id)
                ->lockForUpdate()
                ->first();

            if ($storeStock && bccomp((string) $storeStock->quantity, $quantity, 3) < 0) {
                $available = (string) $storeStock->quantity;
                $storeName = $store ? $store->name : "الفرع #{$storeId}";
                throw new Exception("رصيد الصنف [{$lockedItem->name}] في [{$storeName}] غير كافٍ. المتوفر حالياً: {$available} {$lockedItem->unit}");
            }

            if ($storeStock) {
                $storeStock->quantity = bcsub((string) $storeStock->quantity, $quantity, 3);
                $storeStock->save();
            }
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
            'user_id' => Auth::id() ?? 1,
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
        ?int $storeId = null
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
            'user_id' => Auth::id() ?? 1,
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
        ?int $storeId = null
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
            'user_id' => Auth::id() ?? 1,
            'deposit_type' => $depositType,
            'quantity' => $quantity,
            'cost_price' => $costPrice,
            'reason' => $reason,
            'deposit_date' => $depositDate ?? now()->toDateString(),
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
            'user_id' => Auth::id() ?? 1,
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
        ?int $storeId = null
    ): StockMovement {
        return DB::transaction(function () use ($item, $actualQuantity, $reason, $storeId) {
            $lockedItem = Item::where('id', $item->id)->lockForUpdate()->firstOrFail();

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
                throw new Exception("الرصيد الفعلي المدخل ({$actualQuantity}) مطابق تماماً للرصيد المسجل حالياً بالمخزن.");
            }

            $stockBefore = (string) $lockedItem->current_stock;
            $stockAfter = bcadd($stockBefore, $diff, 3);

            if (bccomp($stockAfter, '0.000', 3) < 0) {
                throw new Exception("لا يمكن تنفيذ التسوية: الرصيد الإجمالي للصنف سيصبح سالباً ({$stockAfter}).");
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
            $docNumber = 'ADJ-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));

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
                'user_id' => Auth::id() ?? 1,
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
