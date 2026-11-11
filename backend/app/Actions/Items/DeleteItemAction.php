<?php

declare(strict_types=1);

namespace App\Actions\Items;

use App\Models\Item;
use Illuminate\Validation\ValidationException;

final class DeleteItemAction
{
    /**
     * Delete item or throw if stock movements block deletion
     */
    public function execute(Item $item): bool
    {
        if (! $item->canBeDeleted()) {
            throw ValidationException::withMessages([
                'item' => [__('inventory.cannot_delete_has_movements')],
            ]);
        }

        return (bool) $item->delete();
    }
}
