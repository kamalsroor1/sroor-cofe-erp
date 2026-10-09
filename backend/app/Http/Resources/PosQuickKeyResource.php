<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PosQuickKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * POSB-6: one POS quick key. Same shape in GET /pos/quick-keys, PUT
 * /stores/{store}/pos/quick-keys and /pos/bootstrap `quick_keys`. Price is a scale-3 string.
 * Exactly one of `item` / `category` is non-null (matching `type`).
 *
 * @mixin PosQuickKey
 */
class PosQuickKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PosQuickKey $key */
        $key = $this->resource;
        $item = $key->item;
        $category = $key->category;

        return [
            'id' => (int) $key->id,
            'page' => (int) $key->page,
            'position' => (int) $key->position,
            'type' => $key->type->value,
            'label' => $key->label,
            'color' => $key->color?->value,
            'item' => $item === null ? null : [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'price' => (string) $item->selling_price,
                'unit' => $item->unit,
                'is_weighted' => (bool) $item->is_weighted,
                'image' => $item->image,
            ],
            'category' => $category === null ? null : [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
            ],
        ];
    }
}
