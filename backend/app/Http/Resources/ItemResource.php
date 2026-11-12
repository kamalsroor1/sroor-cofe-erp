<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Item;
use App\Services\Settings\TenantSettings;
use App\Support\ActiveStore;
use App\Support\ClientStoreGuard;
use App\Support\CostVisibility;
use App\Support\ItemCostFields;
use App\Support\Money\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Item
 */
class ItemResource extends JsonResource
{
    private const CONTEXT_ATTRIBUTE = 'item_resource.context';

    public function toArray(Request $request): array
    {
        // PERF-02: the request-wide values are resolved once per request, not once per row.
        ['store_id' => $storeId, 'show_cost' => $showCost, 'low_stock_threshold' => $lowStockThreshold] = $this->context($request);

        $storeStock = $storeId ? Decimal::normalize((string) $this->getStockInStore($storeId)) : Decimal::normalize((string) $this->current_stock);
        $effectivePrice = $storeId ? Decimal::normalize((string) $this->getEffectivePriceForStore($storeId)) : Decimal::normalize((string) $this->selling_price);

        // SEC-1 (M6): cost and cost-derived values only with items.view_cost (see context()).
        // BE-08: the floor value is hidden from users without it (null, never 0: the edit form must not
        // send it back); has_min_price still tells them a floor exists.
        $minSellingPrice = $showCost ? Decimal::normalize((string) ($this->min_selling_price ?? 0)) : null;

        $deletionBlockers = $this->resource->getDeletionBlockers();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'category_id' => $this->category_id,
            'pos_sales_count' => (int) ($this->pos_sales_count ?? 0),
            'unit' => $this->unit ?? 'كجم',
            // BUG-02: cost_price (= last_purchase_price) + average_cost, items.view_cost only.
            ...ItemCostFields::forItem($this->resource, $request->user()),
            'min_selling_price' => $minSellingPrice,
            'has_min_price' => bccomp((string) ($this->min_selling_price ?? '0'), '0', 3) > 0,
            // BE-08: NULL/0 = not set (wholesale customers pay retail).
            'wholesale_price' => $this->wholesale_price !== null ? Decimal::normalize((string) $this->wholesale_price) : null,
            'selling_price' => $effectivePrice,
            'current_stock' => $storeStock,
            'total_stock' => Decimal::normalize((string) $this->current_stock),
            'min_stock_level' => Decimal::normalize((string) $this->min_stock_level),
            'is_active' => (bool) $this->is_active,
            'is_weighted' => $this->resource->isSoldByWeight(),
            'is_low_stock' => $this->resource->isLowStock($lowStockThreshold),
            'notes' => $this->notes,
            'store_stocks' => $this->relationLoaded('storeStocks') ? $this->storeStocks->map(fn ($ss) => [
                'store_id' => $ss->store_id,
                'store_name' => $ss->store?->name,
                'quantity' => Decimal::normalize((string) $ss->quantity),
                'custom_selling_price' => $ss->custom_selling_price ? Decimal::normalize((string) $ss->custom_selling_price) : null,
            ])->values() : [],
            'can_be_deleted' => $deletionBlockers === [],
            'deletion_blockers' => $deletionBlockers,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    /**
     * Store, cost visibility and the tenant low-stock threshold: identical for every row of a
     * request, so resolved once and kept on the request (a list resolves this resource per row).
     *
     * @return array{store_id: ?int, show_cost: bool, low_stock_threshold: string}
     */
    private function context(Request $request): array
    {
        $context = $request->attributes->get(self::CONTEXT_ATTRIBUTE);
        if (! is_array($context)) {
            $context = [
                'store_id' => $this->resolveStoreId($request),
                'show_cost' => CostVisibility::allows($request->user()),
                'low_stock_threshold' => app(TenantSettings::class)->lowStockDefaultThreshold(),
            ];
            $request->attributes->set(self::CONTEXT_ATTRIBUTE, $context);
        }

        return $context;
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
