<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS-04/05 (tenant DB): indexes behind GET /pos/items: the per-category counters and pages
 * (is_active + category_id) and the top-seller sort (pos_sales_count).
 */
return new class extends Migration
{
    private const CATEGORY_INDEX = 'items_is_active_category_id_index';

    private const SALES_INDEX = 'items_pos_sales_count_index';

    public function up(): void
    {
        if (! Schema::hasTable('items')) {
            return;
        }

        if (Schema::hasColumn('items', 'category_id') && ! Schema::hasIndex('items', self::CATEGORY_INDEX)) {
            Schema::table('items', function (Blueprint $table): void {
                $table->index(['is_active', 'category_id'], self::CATEGORY_INDEX);
            });
        }

        if (Schema::hasColumn('items', 'pos_sales_count') && ! Schema::hasIndex('items', self::SALES_INDEX)) {
            Schema::table('items', function (Blueprint $table): void {
                $table->index('pos_sales_count', self::SALES_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('items')) {
            return;
        }

        foreach ([self::CATEGORY_INDEX, self::SALES_INDEX] as $index) {
            if (Schema::hasIndex('items', $index)) {
                Schema::table('items', function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index);
                });
            }
        }
    }
};
