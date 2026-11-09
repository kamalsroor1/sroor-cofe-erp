<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;

/**
 * Fixtures that create an Item with `current_stock` directly bypass CreateItemAction, so the
 * per-store rows do not exist. Since BE-04 a missing row counts as zero stock, so a fixture
 * that wants to sell must give every store the item's stock.
 */
trait SeedsStoreStock
{
    protected function stockInAllStores(Item $item): Item
    {
        foreach (Store::query()->get() as $store) {
            StoreStock::updateOrCreate(
                ['store_id' => $store->id, 'item_id' => $item->id],
                ['quantity' => (string) $item->current_stock],
            );
        }

        return $item;
    }
}
