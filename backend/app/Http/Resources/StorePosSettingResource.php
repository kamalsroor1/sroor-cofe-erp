<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StorePosSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * POSB-2: POS settings of one store. Same shape in GET/PUT /stores/{store}/pos-settings
 * and in /pos/bootstrap `pos_settings`. Percent is a scale-3 string or null (= no limit).
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
            'updated_at' => $setting->updated_at?->toIso8601String(),
        ];
    }
}
