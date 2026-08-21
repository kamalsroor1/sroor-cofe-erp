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
        if (!$item->canBeDeleted()) {
            $blockers = implode(', ', $item->getDeletionBlockers());
            throw ValidationException::withMessages([
                'item' => [__('inventory.cannot_delete_has_movements') ?: "لا يمكن حذف الصنف ({$item->name}) لوجود موانع: {$blockers}"],
            ]);
        }

        return (bool)$item->delete();
    }
}
