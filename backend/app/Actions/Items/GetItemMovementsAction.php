<?php

declare(strict_types=1);

namespace App\Actions\Items;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StoreStock;
use App\Support\Money\Decimal;
use App\Support\StockMovementTypes;

final class GetItemMovementsAction
{
    /**
     * Query movements ledger and calculate aggregates for an item
     *
     * SEC-1 (M6): $includeCost = false (no items.view_cost) returns cost_price as null and
     * hides each movement's unit_cost.
     */
    public function execute(Item $item, ?string $fromDate = null, ?string $toDate = null, ?int $storeId = null, ?string $type = null, int $perPage = 20, bool $includeCost = true): array
    {
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
            if (StockMovementTypes::isInbound((string) $mov->movement_type)) {
                $totalIn = bcadd($totalIn, (string) $mov->quantity, 3);
            } elseif (StockMovementTypes::isOutbound((string) $mov->movement_type)) {
                $totalOut = bcadd($totalOut, (string) $mov->quantity, 3);
            }
        }

        $netMovement = bcsub($totalIn, $totalOut, 3);

        $currentScopeStock = $storeId
            ? Decimal::normalize((string) (StoreStock::where('store_id', $storeId)->where('item_id', $item->id)->value('quantity') ?: 0))
            : Decimal::normalize((string) $item->current_stock);

        $movements = $query->latest('id')->paginate($perPage);
        if (! $includeCost) {
            $movements->getCollection()->each(fn (StockMovement $movement) => $movement->makeHidden('unit_cost'));
        }

        return [
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'category' => $item->category,
                'unit' => $item->unit,
                'current_stock' => Decimal::normalize((string) $item->current_stock),
                'cost_price' => $includeCost ? Decimal::normalize((string) $item->cost_price) : null,
                'selling_price' => Decimal::normalize((string) $item->selling_price),
            ],
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'store_id' => $storeId,
                'type' => $type ?: 'all',
            ],
            'stats' => [
                'total_in' => Decimal::normalize((string) $totalIn),
                'total_out' => Decimal::normalize((string) $totalOut),
                'net_movement' => Decimal::normalize((string) $netMovement),
                'current_scope_stock' => $currentScopeStock,
                'movements_count' => $allMovements->count(),
            ],
            'data' => $movements->items(),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ];
    }
}
