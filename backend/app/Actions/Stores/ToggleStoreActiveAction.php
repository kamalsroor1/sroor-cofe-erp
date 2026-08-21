<?php

declare(strict_types=1);

namespace App\Actions\Stores;

use App\Models\Store;
use Illuminate\Validation\ValidationException;

final class ToggleStoreActiveAction
{
    /**
     * Toggle store active status with main store protection
     */
    public function execute(Store $store): Store
    {
        if ($store->is_main && $store->is_active) {
            throw ValidationException::withMessages([
                'store' => [__('inventory.cannot_disable_main_store') ?: 'لا يمكن تعطيل الفرع الرئيسي للمنشأة'],
            ]);
        }

        $store->update(['is_active' => !$store->is_active]);

        return $store->fresh();
    }
}
