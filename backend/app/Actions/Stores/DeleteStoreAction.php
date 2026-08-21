<?php

declare(strict_types=1);

namespace App\Actions\Stores;

use App\Models\Store;
use Illuminate\Validation\ValidationException;

final class DeleteStoreAction
{
    /**
     * Delete store if no operational blockers exist
     */
    public function execute(Store $store): bool
    {
        if (!$store->canBeDeleted()) {
            $blockers = implode(', ', $store->getDeletionBlockers());
            throw ValidationException::withMessages([
                'store' => ["لا يمكن حذف الفرع ({$store->name}) لوجود ارتباطات: {$blockers}"],
            ]);
        }

        return (bool)$store->delete();
    }
}
