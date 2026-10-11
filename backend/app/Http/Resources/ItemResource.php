<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Item;
use App\Support\ActiveStore;
use App\Support\ClientStoreGuard;
use App\Support\CostVisibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Item
 */
class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $storeId = $this->resolveStoreId($request);

        $storeStock = $storeId ? (float) $this->getStockInStore($storeId) : (float) $this->current_stock;
        $effectivePrice = $storeId ? (float) $this->getEffectivePriceForStore($storeId) : (float) $this->selling_price;

        // SEC-1 (M6): cost and cost-derived values only with items.view_cost. Without it the
        // minimum selling price is the stored one, never the cost fallback.
        $showCost = CostVisibility::allows($request->user());
        $minSellingPrice = $showCost
            ? (float) ($this->min_selling_price ?? $this->cost_price ?? 0)
            : (float) $this->min_selling_price;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'category_id' => $this->category_id,
            'pos_sales_count' => (int) ($this->pos_sales_count ?? 0),
            'unit' => $this->unit ?? 'كجم',
            'cost_price' => $showCost ? (float) $this->cost_price : null,
            'min_selling_price' => $minSellingPrice,
            'selling_price' => $effectivePrice,
            'current_stock' => $storeStock,
            'total_stock' => (float) $this->current_stock,
            'min_stock_level' => (float) $this->min_stock_level,
            'is_active' => (bool) $this->is_active,
            'is_weighted' => (bool) $this->resource->is_weighted,
            'is_low_stock' => method_exists($this->resource, 'isLowStock') ? $this->isLowStock() : false,
            'notes' => $this->notes,
            'store_stocks' => $this->relationLoaded('storeStocks') ? $this->storeStocks->map(fn ($ss) => [
                'store_id' => $ss->store_id,
                'store_name' => $ss->store?->name,
                'quantity' => (float) $ss->quantity,
                'custom_selling_price' => $ss->custom_selling_price ? (float) $ss->custom_selling_price : null,
            ])->values() : [],
            'can_be_deleted' => method_exists($this->resource, 'canBeDeleted') ? $this->canBeDeleted() : true,
            'deletion_blockers' => method_exists($this->resource, 'getDeletionBlockers') ? $this->getDeletionBlockers() : [],
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    /**
     * The store whose stock/price the row shows: a concrete X-Store-Id (access-checked by
     * ApiTokenAuth / store.active), else a verified client store_id, else the user's current
     * store (the session store store.active wrote). `all` (or anything that is not a store id)
     * means the total over every store.
     */
    private function resolveStoreId(Request $request): ?int
    {
        $raw = $request->header('X-Store-Id');
        if (is_string($raw) && trim($raw) !== '') {
            $header = ActiveStore::parseHeader($raw);

            return is_int($header) ? $header : null;
        }

        $client = ClientStoreGuard::verified($request);
        if (is_int($client)) {
            return $client;
        }

        $current = $request->user()?->getCurrentStore()?->id;

        return $current !== null ? (int) $current : null;
    }
}
