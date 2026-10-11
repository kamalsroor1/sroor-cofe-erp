<?php

namespace App\Http\Resources;

use App\Models\Item;
use App\Models\StoreStock;
use App\Support\CostVisibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Item
 */
class POSItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $storeId = $this->additional['store_id'] ?? session('current_store_id');
        $stock = $storeId
            ? (float) (StoreStock::where('store_id', $storeId)->where('item_id', $this->id)->value('quantity') ?? $this->current_stock)
            : (float) $this->current_stock;

        // SEC-1 (M6): cost (and the cost fallback of the minimum price) only with items.view_cost.
        $showCost = CostVisibility::allows($request->user());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'category' => $this->category ?: 'عام',
            'category_id' => $this->category_id,
            'price_retail' => (float) ($this->price_retail ?? $this->selling_price ?? 0),
            'price_wholesale' => (float) (($this->min_selling_price && $this->min_selling_price > 0) ? $this->min_selling_price : ($this->price_wholesale ?? $this->selling_price ?? 0)),
            'min_selling_price' => $showCost
                ? (float) ($this->min_selling_price ?? $this->cost_price ?? 0)
                : (float) $this->min_selling_price,
            'cost_price' => $showCost ? (float) $this->cost_price : null,
            'current_stock' => $stock,
            'min_stock_level' => (float) ($this->min_stock_level ?: 0),
            'unit' => $this->unit ?: 'كجم',
            'is_weighted' => (bool) $this->resource->is_weighted,
        ];
    }
}
