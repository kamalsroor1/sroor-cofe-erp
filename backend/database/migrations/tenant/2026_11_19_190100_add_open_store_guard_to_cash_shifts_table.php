<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SHOP-06 / BE-19 (tenant DB): one open shift per branch, enforced by the database.
 *
 * `open_store_guard` holds the shift's store id (0 for a store-less shift) while the shift is
 * open and NULL once it is closed or deleted. A UNIQUE index on it lets any number of closed
 * shifts (NULLs never collide) but at most one open shift per branch, so two devices opening a
 * shift at the same instant can never both succeed, whatever the application lock does.
 *
 * Backfill: the newest open, non-deleted shift of each branch takes the guard. An older duplicate
 * open shift (left by the race this closes) keeps NULL, so the index can be built; it stays
 * open and can still be closed normally.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_shifts') || Schema::hasColumn('cash_shifts', 'open_store_guard')) {
            return;
        }

        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->unsignedBigInteger('open_store_guard')->nullable()->after('status');
        });

        $newestOpenPerStore = DB::table('cash_shifts')
            ->where('status', 'open')
            ->whereNull('deleted_at')
            ->selectRaw('MAX(id) as id')
            ->groupBy('store_id')
            ->pluck('id');

        foreach ($newestOpenPerStore as $id) {
            $storeId = DB::table('cash_shifts')->where('id', $id)->value('store_id');
            DB::table('cash_shifts')->where('id', $id)->update(['open_store_guard' => (int) ($storeId ?? 0)]);
        }

        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->unique('open_store_guard', 'cash_shifts_open_store_guard_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cash_shifts') || ! Schema::hasColumn('cash_shifts', 'open_store_guard')) {
            return;
        }

        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->dropUnique('cash_shifts_open_store_guard_unique');
        });

        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->dropColumn('open_store_guard');
        });
    }
};
