<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StoreStock;
use App\Services\Reports\CarryingCost;
use App\Support\CostVisibility;
use App\Support\ItemCostFields;
use App\Support\Money\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StoreStock
 */
class StoreStockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $quantity = (string) ($this->quantity ?? '0');
        // SEC-1 (M6): cost and the cost-based valuation only with items.view_cost.
        $showCost = CostVisibility::allows($request->user());
        // BUG-02: the branch's quantity at the item's carrying cost (one weighted average per
        // item), half-up like the inventory valuation lines; never the last purchase price.
        $totalValuation = $this->item !== null
            ? Decimal::mul(Decimal::normalize($quantity), CarryingCost::forItem($this->item))
            : '0.000';

        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'item_name' => $this->item?->name,
            'item_code' => $this->item?->code,
            'unit' => $this->item?->unit,
            'quantity' => Decimal::normalize((string) $quantity),
            'min_stock_level' => Decimal::normalize((string) ($this->item?->min_stock_level ?? 0)),
            ...ItemCostFields::forItem($this->item, $request->user()),
            'total_valuation' => $showCost ? Decimal::normalize((string) $totalValuation) : null,
            'is_low_stock' => bccomp($quantity, (string) ($this->item?->min_stock_level ?? 0), 3) <= 0,
            'is_out_of_stock' => bccomp($quantity, '0', 3) <= 0,
        ];
    }
}
