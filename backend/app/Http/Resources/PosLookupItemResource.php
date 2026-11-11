<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Item;
use App\Support\CostVisibility;
use App\Support\Money\Decimal;
use App\Support\PosItemPrices;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * POS-01 lookup item shape (same keys as the /pos/bootstrap `items` rows). Cost fields only for users
 * with items.view_cost. Expects the optional `calculated_stock` attribute (store stock).
 *
 * @mixin Item
 */
class PosLookupItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Item $it */
        $it = $this->resource;
        $showCost = CostVisibility::allows($request->user());

        return [
            'id' => (int) $it->id,
            'name' => (string) $it->name,
            'code' => (string) ($it->code ?? ''),
            'image' => $it->image,
            'category' => (string) ($it->category ?: __('inventory.general_category')),
            'category_id' => $it->category_id ? (int) $it->category_id : null,
            'price_retail' => Decimal::normalize((string) $it->selling_price),
            ...PosItemPrices::forItem($it, $request->user()),
            'cost_price' => $showCost ? Decimal::normalize((string) $it->cost_price) : null,
            'current_stock' => Decimal::normalize((string) ($it->getAttribute('calculated_stock') ?? $it->current_stock ?? 0)),
            'min_stock_level' => Decimal::normalize((string) ($it->min_stock_level ?: 0)),
            'unit' => (string) $it->unit,
            'pos_sort_order' => (int) ($it->pos_sort_order ?? 0),
            'is_pos_pinned' => (bool) ($it->is_pos_pinned ?? false),
            'pos_sales_count' => (int) ($it->pos_sales_count ?? 0),
            'is_weighted' => $it->isSoldByWeight(),
        ];
    }
}
