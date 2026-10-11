<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StoreStock;
use App\Support\CostVisibility;
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
        $costPrice = (string) ($this->item?->cost_price ?? '0');
        $totalValuation = bcmul($quantity, $costPrice, 3);
        // SEC-1 (M6): cost and the cost-based valuation only with items.view_cost.
        $showCost = CostVisibility::allows($request->user());

        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'item_name' => $this->item?->name,
            'item_code' => $this->item?->code,
            'unit' => $this->item?->unit,
            'quantity' => (float) $quantity,
            'min_stock_level' => (float) ($this->item?->min_stock_level ?? 0),
            'cost_price' => $showCost ? (float) $costPrice : null,
            'total_valuation' => $showCost ? (float) $totalValuation : null,
            'is_low_stock' => bccomp($quantity, (string) ($this->item?->min_stock_level ?? 0), 3) <= 0,
            'is_out_of_stock' => bccomp($quantity, '0', 3) <= 0,
        ];
    }
}
