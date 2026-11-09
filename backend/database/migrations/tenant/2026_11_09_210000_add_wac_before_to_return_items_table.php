<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-02 (tenant DB): snapshot of the item's effective weighted average cost right before a
 * purchase-return line was applied, so deleting the return can put the average back exactly
 * (mirrors purchase_items.wac_before). Nullable, no backfill: old rows use the formula.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('return_items') && ! Schema::hasColumn('return_items', 'wac_before')) {
            Schema::table('return_items', function (Blueprint $table): void {
                $table->decimal('wac_before', 12, 3)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('return_items') && Schema::hasColumn('return_items', 'wac_before')) {
            Schema::table('return_items', function (Blueprint $table): void {
                $table->dropColumn('wac_before');
            });
        }
    }
};
