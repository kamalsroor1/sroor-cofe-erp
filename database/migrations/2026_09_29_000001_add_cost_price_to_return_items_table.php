<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unit cost at which a returned line re-entered (sales return) or left (purchase return) stock,
 * so profit & loss can reverse COGS at the historical cost instead of today's average.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('return_items') && ! Schema::hasColumn('return_items', 'cost_price')) {
            Schema::table('return_items', function (Blueprint $table) {
                $table->decimal('cost_price', 12, 3)->nullable()->after('unit_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('return_items') && Schema::hasColumn('return_items', 'cost_price')) {
            Schema::table('return_items', function (Blueprint $table) {
                $table->dropColumn('cost_price');
            });
        }
    }
};
