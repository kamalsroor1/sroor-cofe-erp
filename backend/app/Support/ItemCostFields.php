<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Item;
use App\Services\Reports\CarryingCost;
use App\Support\Money\Decimal;

/**
 * BUG-02: the cost figures every item payload (item screen, POS bootstrap / lookup / grid, branch
 * stock, low stock) shares, so they are named and hidden the same way everywhere.
 *
 *  - average_cost: the cost the item is carried and sold at (CarryingCost: weighted average, the
 *    last purchase cost while no average exists). Stock value and profit are computed on it.
 *  - last_purchase_price: the last purchase's landed unit cost (items.cost_price).
 *  - cost_price: unchanged legacy key, same value as last_purchase_price (the item form edits it).
 *
 * All three only for users with `items.view_cost` (CostVisibility); everyone else gets null.
 * The item must have `weighted_avg_cost` and `cost_price` loaded.
 */
final class ItemCostFields
{
    /**
     * @return array{cost_price: string|null, average_cost: string|null, last_purchase_price: string|null}
     */
    public static function forItem(?Item $item, mixed $user): array
    {
        return self::when($item, CostVisibility::allows($user));
    }

    /**
     * Same keys, for callers that already resolved CostVisibility (e.g. an Action given a flag).
     *
     * @return array{cost_price: string|null, average_cost: string|null, last_purchase_price: string|null}
     */
    public static function when(?Item $item, bool $showCost): array
    {
        if ($item === null || ! $showCost) {
            return ['cost_price' => null, 'average_cost' => null, 'last_purchase_price' => null];
        }

        $lastPurchasePrice = Decimal::normalize((string) ($item->getAttribute('cost_price') ?? '0'));

        return [
            'cost_price' => $lastPurchasePrice,
            'average_cost' => CarryingCost::forItem($item),
            'last_purchase_price' => $lastPurchasePrice,
        ];
    }
}
