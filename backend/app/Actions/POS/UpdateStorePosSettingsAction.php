<?php

declare(strict_types=1);

namespace App\Actions\POS;

use App\DTOs\Pos\StorePosSettingsDTO;
use App\Exceptions\BusinessRuleException;
use App\Models\CashShift;
use App\Models\Store;
use App\Models\StorePosSetting;
use App\Services\ActivityLogService;
use App\Services\Pos\ScaleBarcodeConfig;
use Illuminate\Support\Facades\DB;

/**
 * POSB-2: create-or-update the POS settings row of one store (partial update).
 * The store row is locked first so two concurrent first-time PUTs cannot race on the
 * unique store_id (the second waits, then updates the row the first created).
 *
 * SET-02: turning OFF "require an open shift to sell" is refused (422 settings.shift_rule_open_shift)
 * while THIS store has an open shift (a shift of another store never blocks), and every change of
 * that rule is written to the activity log. Both endpoints (general PUT and /shift-rule) go through here.
 */
final class UpdateStorePosSettingsAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(Store $store, StorePosSettingsDTO $dto): StorePosSetting
    {
        return DB::transaction(function () use ($store, $dto): StorePosSetting {
            $lockedStore = Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail();

            $setting = StorePosSetting::query()
                ->where('store_id', $store->getKey())
                ->lockForUpdate()
                ->first();

            if ($setting === null) {
                $setting = StorePosSetting::defaultsFor((int) $store->getKey());
            }

            $wasRequired = (bool) $setting->require_shift_for_sales;
            $changes = $dto->toArray();
            $willRequire = array_key_exists('require_shift_for_sales', $changes)
                ? (bool) $changes['require_shift_for_sales']
                : $wasRequired;

            if ($wasRequired && ! $willRequire && $this->hasOpenShift((int) $store->getKey())) {
                throw BusinessRuleException::make('settings.shift_rule_open_shift');
            }

            $setting->fill($changes);

            if ($setting->scale_prefixes === null) {
                $setting->scale_prefixes = ScaleBarcodeConfig::DEFAULT_PREFIXES;
            }

            $setting->save();

            if ($wasRequired !== $willRequire) {
                $this->activityLogService->log(
                    module: 'settings',
                    action: 'update',
                    description: __($willRequire ? 'settings.activity_shift_rule_enabled' : 'settings.activity_shift_rule_disabled', ['store' => $lockedStore->name]),
                    subject: $setting,
                    properties: ['setting' => 'require_shift_for_sales', 'store_id' => (int) $store->getKey(), 'old' => $wasRequired, 'new' => $willRequire],
                    storeId: (int) $store->getKey(),
                );
            }

            return $setting->refresh();
        });
    }

    private function hasOpenShift(int $storeId): bool
    {
        return CashShift::query()->where('store_id', $storeId)->where('status', 'open')->exists();
    }
}
