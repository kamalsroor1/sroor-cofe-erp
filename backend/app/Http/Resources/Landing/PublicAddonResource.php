<?php

declare(strict_types=1);

namespace App\Http\Resources\Landing;

use App\Models\Addon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PRD-07: one public add-on with its catalog prices and the plans it is sold on.
 *
 * @property Addon $resource
 */
final class PublicAddonResource extends JsonResource
{
    /**
     * @param  list<array{code: string, unit_price: string, yearly_price: string|null}>  $plans
     */
    public function __construct(
        Addon $addon,
        private readonly array $plans,
    ) {
        parent::__construct($addon);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $addon = $this->resource;

        return [
            'key' => $addon->key,
            'name' => PublicPlanResource::translate($addon->name_key, $addon->key),
            'description' => PublicPlanResource::translate(PublicPlanResource::sibling($addon->name_key, 'description'), null),
            'type' => $addon->type->value,
            'unit_price' => $addon->unit_price,
            'yearly_price' => $addon->yearly_price,
            'price_tiers' => $this->tiers($addon),
            'plans' => $this->plans,
        ];
    }

    /**
     * @return list<array{min_qty: int, unit_price: string, yearly_price: string|null}>
     */
    private function tiers(Addon $addon): array
    {
        $tiers = [];

        foreach ((array) $addon->price_tiers as $tier) {
            if (! is_array($tier) || ! isset($tier['min_qty'], $tier['unit_price'])) {
                continue;
            }

            $tiers[] = [
                'min_qty' => (int) $tier['min_qty'],
                'unit_price' => bcadd((string) $tier['unit_price'], '0', 3),
                'yearly_price' => isset($tier['yearly_price']) ? bcadd((string) $tier['yearly_price'], '0', 3) : null,
            ];
        }

        return $tiers;
    }
}
