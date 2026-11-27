<?php

declare(strict_types=1);

namespace App\Actions\Items;

use App\DTOs\Items\AdjustStockDTO;
use App\Exceptions\Stock\InsufficientStockException;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Services\DocumentNumberService;
use App\Services\Settings\TenantSettings;
use App\Support\Database\TransactionRetry;
use App\Support\Money\Decimal;
use Illuminate\Support\Facades\DB;

final class AdjustItemStockAction
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly TenantSettings $tenantSettings,
    ) {}

    /**
     * Adjust item stock in store and record audit movement with bcmath and lockForUpdate
     */
    public function execute(AdjustStockDTO $dto, int $userId): StockMovement
    {
        // SETG-2 ext: number and daily counter follow the tenant business day.
        // DATE-03: ADJ-YYYYMMDD-NNNN, the same format and counter as StockService adjustments.
        // BUG-04: the day's counter row exists before the transaction takes its first read.
        $this->documentNumbers->prepareDated('ADJ', StockMovement::class, 'document_number');

        // BUG-07: restarted as a whole on a lock conflict; a failed attempt's number rolls back.
        return TransactionRetry::run(function () use ($dto, $userId) {
            $item = Item::where('id', $dto->item_id)->lockForUpdate()->firstOrFail();

            $storeStock = StoreStock::withTrashed()
                ->where('store_id', $dto->store_id)
                ->where('item_id', $item->id)
                ->lockForUpdate()
                ->first();

            if (! $storeStock) {
                DB::table('store_stocks')->insertOrIgnore([
                    'store_id' => $dto->store_id,
                    'item_id' => $item->id,
                    'quantity' => '0.000',
                    'min_stock' => Decimal::normalize((string) $item->min_stock_level),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $storeStock = StoreStock::withTrashed()
                    ->where('store_id', $dto->store_id)
                    ->where('item_id', $item->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ($storeStock->trashed()) {
                $storeStock->deleted_at = null;
                $storeStock->quantity = '0.000';
            }

            $currentStoreQty = Decimal::normalize((string) $storeStock->quantity);
            $adjustQty = Decimal::normalize($dto->quantity);

            $isIn = in_array($dto->movement_type, ['stock_adjustment_in', 'stock_deposit_in'], true);

            if ($isIn) {
                $newStoreQty = bcadd($currentStoreQty, $adjustQty, 3);
            } else {
                $newStoreQty = bcsub($currentStoreQty, $adjustQty, 3);

                if (! $this->tenantSettings->allowNegativeStock() && bccomp($newStoreQty, '0.000', 3) < 0) {
                    $storeName = (string) (Store::query()->whereKey($dto->store_id)->value('name') ?? "#{$dto->store_id}");

                    throw InsufficientStockException::forStore(
                        (string) $item->name,
                        $storeName,
                        $currentStoreQty,
                        (string) $item->unit,
                    );
                }
            }

            $docNumber = $this->documentNumbers->nextDated('ADJ', 4, StockMovement::class, 'document_number');

            $movement = StockMovement::create([
                'item_id' => $item->id,
                'store_id' => $dto->store_id,
                'user_id' => $userId,
                'movement_type' => $dto->movement_type,
                'quantity' => $adjustQty,
                'stock_before' => $currentStoreQty,
                'stock_after' => $newStoreQty,
                'unit_cost' => $dto->unit_cost ?: $item->cost_price,
                'source_type' => Item::class,
                'source_id' => $item->id,
                'document_number' => $docNumber,
                'notes' => $dto->notes,
            ]);

            // Update StoreStock
            $storeStock->quantity = $newStoreQty;
            $storeStock->save();

            // Recalculate total item current_stock with bcmath
            $totalStock = '0.000';
            foreach (StoreStock::where('item_id', $item->id)->pluck('quantity') as $qty) {
                $totalStock = bcadd($totalStock, Decimal::normalize((string) $qty), 3);
            }
            $item->current_stock = $totalStock;
            $item->save();

            return $movement->load(['item', 'store', 'user']);
        });
    }
}
