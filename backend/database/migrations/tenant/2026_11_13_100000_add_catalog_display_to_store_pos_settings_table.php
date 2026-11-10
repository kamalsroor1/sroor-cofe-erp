<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS catalog display (tenant DB), per branch: how many product cards per row (2, 3 or auto)
 * and whether the cards show item images. Defaults: 3 per row, images off.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_pos_settings')) {
            return;
        }

        Schema::table('store_pos_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('store_pos_settings', 'catalog_cards_per_row')) {
                $table->string('catalog_cards_per_row', 4)->default('3');
            }
            if (! Schema::hasColumn('store_pos_settings', 'show_item_images')) {
                $table->boolean('show_item_images')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('store_pos_settings')) {
            return;
        }

        Schema::table('store_pos_settings', function (Blueprint $table): void {
            foreach (['catalog_cards_per_row', 'show_item_images'] as $column) {
                if (Schema::hasColumn('store_pos_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
