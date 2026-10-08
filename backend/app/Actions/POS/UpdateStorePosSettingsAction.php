<?php

declare(strict_types=1);

namespace App\Actions\POS;

use App\DTOs\Pos\StorePosSettingsDTO;
use App\Models\Store;
use App\Models\StorePosSetting;
use App\Services\Pos\ScaleBarcodeConfig;
use Illuminate\Support\Facades\DB;

/**
 * POSB-2: create-or-update the POS settings row of one store (partial update).
 * The store row is locked first so two concurrent first-time PUTs cannot race on the
 * unique store_id (the second waits, then updates the row the first created).
 */
final class UpdateStorePosSettingsAction
{
    public function execute(Store $store, StorePosSettingsDTO $dto): StorePosSetting
    {
        return DB::transaction(function () use ($store, $dto): StorePosSetting {
            Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail();

            $setting = StorePosSetting::query()
                ->where('store_id', $store->getKey())
                ->lockForUpdate()
                ->first();

            if ($setting === null) {
                $setting = StorePosSetting::defaultsFor((int) $store->getKey());
            }

            $setting->fill($dto->toArray());

            if ($setting->scale_prefixes === null) {
                $setting->scale_prefixes = ScaleBarcodeConfig::DEFAULT_PREFIXES;
            }

            $setting->save();

            return $setting->refresh();
        });
    }
}
