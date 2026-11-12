<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Requests\Pos\ListPosItemsRequest;
use App\Models\Item;
use App\Models\StorePosSetting;
use App\Support\ItemCostFields;
use App\Support\Money\Decimal;
use App\Support\PosItemPrices;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * POS-04: lean item for the POS grid (no deletion blockers, no per-row queries). Same price keys as
 * /pos/bootstrap and /pos/lookup. Needs the `calculated_stock` and `category_*` attributes selected by
 * GetPosItemsAction. Cost only for items.view_cost; image only when the ACTIVE branch has `show_item_images` on (store_pos_settings).
 *
 * @mixin Item
 */
class PosGridItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Item $it */
        $it = $this->resource;
        $data = [
            'id' => (int) $it->id,
            'code' => (string) ($it->code ?? ''),
            'name' => (string) $it->name,
            'category' => (string) ($it->getAttribute('category_name') ?: $it->category ?: __('inventory.general_category')),
            'category_id' => $it->category_id ? (int) $it->category_id : null,
            'category_color' => $it->getAttribute('category_color'),
            'category_color_light' => $it->getAttribute('category_color_light'),
            'unit' => (string) $it->unit,
            'is_weighted' => $it->isSoldByWeight(),
            'price_retail' => Decimal::normalize((string) $it->selling_price),
            ...PosItemPrices::forItem($it, $request->user()),
            ...ItemCostFields::forItem($it, $request->user()),
            'current_stock' => Decimal::normalize((string) ($it->getAttribute('calculated_stock') ?? $it->current_stock ?? 0)),
            'min_stock_level' => Decimal::normalize((string) ($it->min_stock_level ?: 0)),
            'is_pos_pinned' => (bool) $it->is_pos_pinned,
            'pos_sales_count' => (int) $it->pos_sales_count,
        ];

        if (self::imagesEnabled($request)) {
            $data['image'] = $it->image;
        }

        return $data;
    }

    /**
     * Resolved once per request (the resource runs per row): the branch switch of the active store.
     */
    public static function imagesEnabled(Request $request): bool
    {
        if (! $request->attributes->has('pos_show_item_images')) {
            $enabled = $request instanceof ListPosItemsRequest
                && StorePosSetting::forStore((int) $request->activeStore()->id)->show_item_images;
            $request->attributes->set('pos_show_item_images', $enabled);
        }

        return (bool) $request->attributes->get('pos_show_item_images');
    }
}
