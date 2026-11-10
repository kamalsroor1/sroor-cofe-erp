<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-08 / POS-18 (tenant DB): a real wholesale price, separate from the minimum selling price (the
 * floor). NULL or 0 = not set: wholesale customers then pay the retail price. Deliberately no
 * backfill: copying `min_selling_price` into it would make the floor the wholesale price again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('items') && ! Schema::hasColumn('items', 'wholesale_price')) {
            Schema::table('items', function (Blueprint $table) {
                $table->decimal('wholesale_price', 12, 3)->nullable()->after('selling_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('items') && Schema::hasColumn('items', 'wholesale_price')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('wholesale_price');
            });
        }
    }
};
