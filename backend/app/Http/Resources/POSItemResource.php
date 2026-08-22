<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class POSItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $storeId = $this->additional['store_id'] ?? session('current_store_id');
        $stock = $storeId
            ? (float)(\App\Models\StoreStock::where('store_id', $storeId)->where('item_id', $this->id)->value('quantity') ?? $this->current_stock)
            : (float)$this->current_stock;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'category' => $this->category ?: 'عام',
            'category_id' => $this->category_id,
            'price_retail' => (float)($this->price_retail ?? $this->selling_price ?? 0),
            'price_wholesale' => (float)(($this->min_selling_price && $this->min_selling_price > 0) ? $this->min_selling_price : ($this->price_wholesale ?? $this->selling_price ?? 0)),
            'min_selling_price' => (float)($this->min_selling_price ?? $this->cost_price ?? 0),
            'cost_price' => (float)$this->cost_price,
            'current_stock' => $stock,
            'min_stock_level' => (float)($this->min_stock_level ?: 0),
            'unit' => $this->unit ?: 'كجم',
        ];
    }
}
