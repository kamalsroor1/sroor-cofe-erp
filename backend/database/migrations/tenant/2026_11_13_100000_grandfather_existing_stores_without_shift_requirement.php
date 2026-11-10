<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * POS-10 follow-up (owner decision): the "open shift required to sell" rule applies to NEW
 * branches only. Every branch that exists when this runs keeps selling without a shift, so its
 * store_pos_settings.require_shift_for_sales is set to false (a row with the column defaults is
 * inserted for branches that never saved POS settings).
 *
 * Branches created afterwards have no row, so StorePosSetting::forStore() falls back to
 * config('pos.require_shift_by_default') (POS_REQUIRE_SHIFT_BY_DEFAULT, default ON).
 *
 * Runs once per tenant database (the migrator records it). A second manual run would only
 * switch off rows that were explicitly switched on in the meantime, so it must not be re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_pos_settings') || ! Schema::hasTable('stores')
            || ! Schema::hasColumn('store_pos_settings', 'require_shift_for_sales')) {
            return;
        }

        $now = now();

        DB::table('store_pos_settings')
            ->where('require_shift_for_sales', true)
            ->update(['require_shift_for_sales' => false, 'updated_at' => $now]);

        $withSettings = DB::table('store_pos_settings')->pluck('store_id')->all();

        DB::table('stores')
            ->whereNotIn('id', $withSettings)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int|string $storeId) use ($now): void {
                DB::table('store_pos_settings')->insert([
                    'store_id' => (int) $storeId,
                    'require_shift_for_sales' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    /**
     * Data decision, not schema: there is no way to know which rows were switched, so down()
     * is intentionally a no-op (the setting stays editable per branch from the POS settings screen).
     */
    public function down(): void {}
};
