<?php

declare(strict_types=1);

namespace App\Actions\Items;

use App\DTOs\Items\AdjustStockDTO;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StoreStock;
use Illuminate\Support\Facades\DB;

final class AdjustItemStockAction
{
    /**
     * Adjust item stock in store and record audit movement with bcmath and lockForUpdate
     */
    public function execute(AdjustStockDTO $dto, int $userId): StockMovement
    {
        return DB::transaction(function () use ($dto, $userId) {
            $item = Item::where('id', $dto->item_id)->lockForUpdate()->firstOrFail();
            $storeStock = StoreStock::firstOrCreate(
                ['store_id' => $dto->store_id, 'item_id' => $item->id],
                ['quantity' => '0.000']
            );

            // Lock store stock record
            $storeStock = StoreStock::where('id', $storeStock->id)->lockForUpdate()->first();

            $currentStoreQty = (string)$storeStock->quantity;
            $adjustQty = (string)$dto->quantity;

            $isIn = in_array($dto->movement_type, ['stock_adjustment_in', 'stock_deposit_in'], true);

            if ($isIn) {
                $newStoreQty = bcadd($currentStoreQty, $adjustQty, 3);
            } else {
                $newStoreQty = bcsub($currentStoreQty, $adjustQty, 3);
            }

            $prefix = 'ADJ-' . date('ymd');
            $count = StockMovement::whereDate('created_at', now()->toDateString())->count() + 1;
            $docNumber = $prefix . '-' . str_pad((string)$count, 4, '0', STR_PAD_LEFT);

            $movement = StockMovement::create([
                'item_id'         => $item->id,
                'store_id'        => $dto->store_id,
                'user_id'         => $userId,
                'movement_type'   => $dto->movement_type,
                'quantity'        => $adjustQty,
                'stock_before'    => $currentStoreQty,
                'stock_after'     => $newStoreQty,
                'unit_cost'       => $dto->unit_cost ?: $item->cost_price,
                'source_type'     => Item::class,
                'source_id'       => $item->id,
                'document_number' => $docNumber,
                'notes'           => $dto->notes,
            ]);

            // Update StoreStock
            $storeStock->quantity = $newStoreQty;
            $storeStock->save();

            // Recalculate total item current_stock
            $totalStock = StoreStock::where('item_id', $item->id)->sum('quantity');
            $item->current_stock = (string)($totalStock ?: '0.000');
            $item->save();

            return $movement->load(['item', 'store', 'user']);
        });
    }
}
