<?php

declare(strict_types=1);

namespace App\Actions\Items;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;

final class GetItemMovementsAction
{
    /**
     * Query movements ledger and calculate aggregates for an item
     */
    public function execute(Item $item, ?string $fromDate = null, ?string $toDate = null, ?int $storeId = null, ?string $type = null, int $perPage = 20): array
    {
        $inTypes = [
            'purchase_in', 'stock_deposit_in', 'stock_adjustment_in',
            'cancellation_in', 'transfer_in', 'sales_return_in', 'purchase_restore_in'
        ];
        $outTypes = [
            'sales_out', 'waste_out', 'stock_adjustment_out',
            'transfer_out', 'purchase_cancel_out', 'purchase_return_out'
        ];

        $query = StockMovement::with(['user', 'store'])
            ->where('item_id', $item->id);

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($type && $type !== 'all') {
            $query->where('movement_type', $type);
        }

        // Stats calculation
        $allMovements = (clone $query)->get();
        $totalIn = '0.000';
        $totalOut = '0.000';

        foreach ($allMovements as $mov) {
            if (in_array($mov->movement_type, $inTypes, true)) {
                $totalIn = bcadd($totalIn, (string)$mov->quantity, 3);
            } elseif (in_array($mov->movement_type, $outTypes, true)) {
                $totalOut = bcadd($totalOut, (string)$mov->quantity, 3);
            }
        }

        $netMovement = bcsub($totalIn, $totalOut, 3);

        $currentScopeStock = $storeId
            ? (float)(StoreStock::where('store_id', $storeId)->where('item_id', $item->id)->value('quantity') ?: 0)
            : (float)$item->current_stock;

        $movements = $query->latest('id')->paginate($perPage);

        return [
            'item' => [
                'id'            => $item->id,
                'name'          => $item->name,
                'code'          => $item->code,
                'category'      => $item->category,
                'unit'          => $item->unit,
                'current_stock' => (float)$item->current_stock,
                'cost_price'    => (float)$item->cost_price,
                'selling_price' => (float)$item->selling_price,
            ],
            'filters' => [
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'store_id'  => $storeId,
                'type'      => $type ?: 'all',
            ],
            'stats' => [
                'total_in'            => (float)$totalIn,
                'total_out'           => (float)$totalOut,
                'net_movement'        => (float)$netMovement,
                'current_scope_stock' => $currentScopeStock,
                'movements_count'     => $allMovements->count(),
            ],
            'data' => $movements->items(),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page'    => $movements->lastPage(),
                'per_page'     => $movements->perPage(),
                'total'        => $movements->total(),
            ],
        ];
    }
}
