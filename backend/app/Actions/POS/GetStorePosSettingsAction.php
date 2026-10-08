<?php

declare(strict_types=1);

namespace App\Actions\POS;

use App\Models\StorePosSetting;

/**
 * POSB-2: a store's POS settings, or the (unsaved) defaults when it never saved any.
 * Read-only: never creates a row.
 */
final class GetStorePosSettingsAction
{
    public function execute(int $storeId): StorePosSetting
    {
        return StorePosSetting::forStore($storeId);
    }
}
