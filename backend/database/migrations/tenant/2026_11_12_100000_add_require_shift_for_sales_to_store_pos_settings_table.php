<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS-10 (tenant DB): per-branch switch "an open cash shift is required to sell".
 * Default ON: existing rows (and branches that never saved POS settings, which fall back to the
 * same default) start requiring a shift as soon as this runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_pos_settings') && ! Schema::hasColumn('store_pos_settings', 'require_shift_for_sales')) {
            Schema::table('store_pos_settings', function (Blueprint $table) {
                $table->boolean('require_shift_for_sales')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('store_pos_settings') && Schema::hasColumn('store_pos_settings', 'require_shift_for_sales')) {
            Schema::table('store_pos_settings', function (Blueprint $table) {
                $table->dropColumn('require_shift_for_sales');
            });
        }
    }
};
