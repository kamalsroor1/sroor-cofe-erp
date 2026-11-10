<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StorePosSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * POSB-2: POS settings of one store. Same shape in GET/PUT /stores/{store}/pos-settings
 * and in /pos/bootstrap `pos_settings`. `max_discount_percent` is the stored scale-3 string, or null (= the store uses the default cap, 10%);
 * `effective_max_discount_percent` is the cap the server enforces (100 = no limit).
 *
 * @mixin StorePosSetting
 */
class StorePosSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StorePosSetting $setting */
        $setting = $this->resource;

        return [
            'store_id' => (int) $setting->store_id,
            ...$setting->scaleConfig()->toArray(),
            'max_discount_percent' => $setting->max_discount_percent === null ? null : (string) $setting->max_discount_percent,
            // POS-07: what the server actually enforces (the stored value, else the default).
            'effective_max_discount_percent' => $setting->effectiveMaxDiscountPercent(),
            'require_shift_for_sales' => (bool) $setting->require_shift_for_sales,
            'catalog_cards_per_row' => (string) $setting->catalog_cards_per_row,
            'show_item_images' => (bool) $setting->show_item_images,
            'updated_at' => $setting->updated_at?->toIso8601String(),
        ];
    }
}
