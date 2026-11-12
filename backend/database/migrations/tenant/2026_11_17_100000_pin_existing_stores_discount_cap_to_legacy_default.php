<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ROLE-02: the default cashier discount cap drops from 10 % to 5 % (StorePosSetting::DEFAULT_MAX_DISCOUNT_PERCENT).
 * Existing shops must not change behaviour silently, so every store that exists now and has no explicit
 * cap (NULL = "use the default") is pinned to the old 10 %. Stores created afterwards get the new 5 %.
 * The owner can lower any store's cap from the branch POS settings.
 *
 * Every branch already has a store_pos_settings row (the 2026_11_13 grandfather migration inserted
 * them), so a single UPDATE is enough. Runs once per tenant DB; down() is a no-op because the
 * rows pinned here cannot be told apart from caps an owner typed in as 10.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_pos_settings') || ! Schema::hasColumn('store_pos_settings', 'max_discount_percent')) {
            return;
        }

        DB::table('store_pos_settings')
            ->whereNull('max_discount_percent')
            ->update(['max_discount_percent' => '10.000', 'updated_at' => now()]);
    }

    public function down(): void {}
};
