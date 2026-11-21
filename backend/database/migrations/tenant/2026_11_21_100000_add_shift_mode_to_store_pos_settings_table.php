<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SHIFT-01 (tenant DB, owner decision 2026-11-21): per-branch shift mode.
 *
 * `shift_mode` = 'per_cashier' (default: each cashier opens their own shift) or 'shared' (one
 * shift per branch for every cashier, the SHOP-06 behaviour). Branches without a settings row
 * fall back to the same default (StorePosSetting::defaultsFor).
 *
 * Transition: a branch that has an OPEN shift when this runs is running a shared drawer right now,
 * so it keeps 'shared' (a row is inserted for a branch that never saved POS settings, with the
 * shift requirement it already had through the config default). Switching the mode is refused
 * while the branch has an open shift (UpdateStorePosSettingsAction), so the owner switches it after
 * that shift is closed. Every other branch starts per_cashier.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_pos_settings') || Schema::hasColumn('store_pos_settings', 'shift_mode')) {
            return;
        }

        Schema::table('store_pos_settings', function (Blueprint $table): void {
            $table->string('shift_mode', 16)->default('per_cashier');
        });

        if (! Schema::hasTable('cash_shifts') || ! Schema::hasTable('stores')) {
            return;
        }

        $storesWithOpenShift = DB::table('cash_shifts')
            ->where('status', 'open')
            ->whereNull('deleted_at')
            ->whereNotNull('store_id')
            ->whereIn('store_id', DB::table('stores')->select('id'))
            ->distinct()
            ->orderBy('store_id')
            ->pluck('store_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        if ($storesWithOpenShift === []) {
            return;
        }

        $now = now();

        DB::table('store_pos_settings')
            ->whereIn('store_id', $storesWithOpenShift)
            ->update(['shift_mode' => 'shared', 'updated_at' => $now]);

        $withSettings = DB::table('store_pos_settings')->pluck('store_id')->map(static fn (int|string $id): int => (int) $id)->all();

        foreach (array_diff($storesWithOpenShift, $withSettings) as $storeId) {
            DB::table('store_pos_settings')->insert([
                'store_id' => $storeId,
                'require_shift_for_sales' => (bool) config('pos.require_shift_by_default', true),
                'shift_mode' => 'shared',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Drops the column; rows inserted by up() stay (they carry the values the branch had anyway).
     */
    public function down(): void
    {
        if (! Schema::hasTable('store_pos_settings') || ! Schema::hasColumn('store_pos_settings', 'shift_mode')) {
            return;
        }

        Schema::table('store_pos_settings', function (Blueprint $table): void {
            $table->dropColumn('shift_mode');
        });
    }
};
