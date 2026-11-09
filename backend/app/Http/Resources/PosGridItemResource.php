<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Item;
use App\Models\Setting;
use App\Support\CostVisibility;
use App\Support\PosItemPrices;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * POS-04: lean item for the POS grid (no deletion blockers, no per-row queries). Same price keys as
 * /pos/bootstrap and /pos/lookup. Needs the `calculated_stock` and `category_*` attributes selected by
 * GetPosItemsAction. Cost only for items.view_cost; image only when the tenant setting
 * `pos_show_item_images` is on.
 *
 * @mixin Item
 */
class PosGridItemResource extends JsonResource
{
    public const SETTING_SHOW_IMAGES = 'pos_show_item_images';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Item $it */
        $it = $this->resource;
        $showCost = CostVisibility::allows($request->user());
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
            'price_retail' => (float) $it->selling_price,
            ...PosItemPrices::forItem($it, $request->user()),
            'cost_price' => $showCost ? (float) $it->cost_price : null,
            'current_stock' => (float) ($it->getAttribute('calculated_stock') ?? $it->current_stock ?? 0),
            'min_stock_level' => (float) ($it->min_stock_level ?: 0),
            'is_pos_pinned' => (bool) $it->is_pos_pinned,
            'pos_sales_count' => (int) $it->pos_sales_count,
        ];

        if (self::imagesEnabled()) {
            $data['image'] = $it->image;
        }

        return $data;
    }

    public static function imagesEnabled(): bool
    {
        return filter_var(Setting::get(self::SETTING_SHOW_IMAGES, '0'), FILTER_VALIDATE_BOOLEAN);
    }
}
