<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Item;

/**
 * BE-08 / POS-18: the price fields every POS item payload (bootstrap, lookup, grid) shares, so the
 * floor is hidden the same way everywhere.
 *
 *  - price_wholesale: the dedicated wholesale price, retail when not set (never the floor).
 *  - min_selling_price: the floor value, only for users with `items.view_cost` (CostVisibility);
 *    everyone else gets null. 0 = no floor.
 *  - has_min_price: whether a floor exists, for every user, so the UI can warn without the value.
 */
final class PosItemPrices
{
    /**
     * @return array{price_wholesale: float, min_selling_price: float|null, has_min_price: bool}
     */
    public static function forItem(Item $item, mixed $user): array
    {
        $min = (string) ($item->min_selling_price ?? '0.000');

        return [
            'price_wholesale' => (float) $item->price_wholesale,
            'min_selling_price' => CostVisibility::allows($user) ? (float) $min : null,
            'has_min_price' => bccomp($min, '0', 3) > 0,
        ];
    }
}
