<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: auth()->user()?->getCurrentStore()?->id;

        $storeStock = $storeId ? (float)$this->getStockInStore((int)$storeId) : (float)$this->current_stock;
        $effectivePrice = $storeId ? (float)$this->getEffectivePriceForStore((int)$storeId) : (float)$this->selling_price;

        return [
            'id'                => $this->id,
            'code'              => $this->code,
            'name'              => $this->name,
            'category'          => $this->category,
            'category_id'       => $this->category_id,
            'pos_sales_count'   => (int)($this->pos_sales_count ?? 0),
            'unit'              => $this->unit ?? 'كجم',
            'cost_price'        => (float)$this->cost_price,
            'min_selling_price' => (float)($this->min_selling_price ?? $this->cost_price ?? 0),
            'selling_price'     => $effectivePrice,
            'current_stock'     => $storeStock,
            'total_stock'       => (float)$this->current_stock,
            'min_stock_level'   => (float)$this->min_stock_level,
            'is_active'         => (bool)$this->is_active,
            'is_low_stock'      => method_exists($this->resource, 'isLowStock') ? $this->isLowStock() : false,
            'notes'             => $this->notes,
            'store_stocks'      => $this->relationLoaded('storeStocks') ? $this->storeStocks->map(fn($ss) => [
                'store_id'             => $ss->store_id,
                'store_name'           => $ss->store?->name,
                'quantity'             => (float)$ss->quantity,
                'custom_selling_price' => $ss->custom_selling_price ? (float)$ss->custom_selling_price : null,
            ]) : [],
            'can_be_deleted'    => method_exists($this->resource, 'canBeDeleted') ? $this->canBeDeleted() : true,
            'deletion_blockers' => method_exists($this->resource, 'getDeletionBlockers') ? $this->getDeletionBlockers() : [],
            'created_at'        => $this->created_at?->toDateTimeString(),
        ];
    }
}
