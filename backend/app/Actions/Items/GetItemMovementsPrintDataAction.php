<?php

declare(strict_types=1);

namespace App\Actions\Items;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Support\StockMovementTypes;
use Illuminate\Database\Eloquent\Builder;

/**
 * Data for the item movements A4 print: filtered ledger plus in/out totals (bcmath, scale 3).
 */
final class GetItemMovementsPrintDataAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Item $item, ?int $storeId, ?string $fromDate, ?string $toDate, ?string $filterType): array
    {
        $storeName = (string) __('common.all_stores');
        $store = $storeId ? Store::query()->find($storeId) : null;
        if ($store !== null) {
            $storeName = (string) $store->name;
        }

        /** @var Builder<StockMovement> $baseQuery */
        $baseQuery = StockMovement::with(['user', 'store'])
            ->where('item_id', $item->id)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($fromDate, fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('created_at', '<=', $toDate))
            ->when($filterType === 'in', fn ($q) => $q->whereIn('movement_type', StockMovementTypes::INBOUND))
            ->when($filterType === 'out', fn ($q) => $q->whereIn('movement_type', StockMovementTypes::OUTBOUND))
            ->when($filterType === 'adjustments', fn ($q) => $q->whereIn('movement_type', StockMovementTypes::ADJUSTMENTS));

        $movements = $baseQuery->oldest('created_at')->get();

        $totalIn = '0.000';
        $totalOut = '0.000';
        foreach ($movements as $mov) {
            if (StockMovementTypes::isInbound((string) $mov->movement_type)) {
                $totalIn = bcadd($totalIn, (string) $mov->quantity, 3);
            } elseif (StockMovementTypes::isOutbound((string) $mov->movement_type)) {
                $totalOut = bcadd($totalOut, (string) $mov->quantity, 3);
            }
        }

        $currentScopeStock = $storeId
            ? (string) (StoreStock::where('store_id', $storeId)->where('item_id', $item->id)->value('quantity') ?: '0.000')
            : (string) $item->current_stock;

        return [
            'item' => $item,
            'storeName' => $storeName,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'movements' => $movements,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'netMovement' => bcsub($totalIn, $totalOut, 3),
            'currentScopeStock' => $currentScopeStock,
        ];
    }
}
