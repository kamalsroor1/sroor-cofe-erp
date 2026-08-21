<?php

declare(strict_types=1);

namespace App\Actions\Items;

use App\Models\Item;

final class ToggleItemActiveAction
{
    /**
     * Toggle active state of item
     */
    public function execute(Item $item): Item
    {
        $item->update(['is_active' => !$item->is_active]);

        return $item->fresh(['storeStocks.store']);
    }
}
