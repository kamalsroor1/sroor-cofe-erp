<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SHOP-02 (tenant DB): stocktake sessions per store.
 *
 *  - stock_counts: one session per store (draft -> counting -> posted, or cancelled).
 *    `open_store_id` equals `store_id` while the session is open and is NULL once it is posted or
 *    cancelled; its UNIQUE index is the portable "one open count per store" rule (MySQL and
 *    sqlite both allow many NULLs in a unique index).
 *  - stock_count_lines: the snapshot (`system_qty`) taken at start, the counted quantity, and at
 *    posting the live balance (`system_qty_at_post`) the difference was computed against.
 *
 * Quantities and money are DECIMAL(12,3).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_counts')) {
            Schema::create('stock_counts', function (Blueprint $table) {
                $table->id();
                $table->string('count_number', 50)->unique();
                $table->unsignedBigInteger('store_id')->index();
                $table->unsignedBigInteger('open_store_id')->nullable()->unique();
                $table->unsignedBigInteger('category_id')->nullable()->index();
                $table->string('status', 20)->default('draft')->index();
                $table->boolean('is_blind')->default(false);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->foreign('store_id', 'fk_stock_counts_store')->references('id')->on('stores')->restrictOnDelete();
                $table->foreign('category_id', 'fk_stock_counts_category')->references('id')->on('categories')->nullOnDelete();
                $table->foreign('created_by', 'fk_stock_counts_creator')->references('id')->on('users')->nullOnDelete();
                $table->foreign('posted_by', 'fk_stock_counts_poster')->references('id')->on('users')->nullOnDelete();
                $table->foreign('cancelled_by', 'fk_stock_counts_canceller')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('stock_count_lines')) {
            Schema::create('stock_count_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_count_id');
                $table->unsignedBigInteger('item_id')->index();
                $table->decimal('system_qty', 12, 3)->default(0);
                $table->decimal('counted_qty', 12, 3)->nullable();
                $table->decimal('system_qty_at_post', 12, 3)->nullable();
                $table->decimal('difference', 12, 3)->nullable();
                $table->decimal('unit_cost', 12, 3)->default(0);
                $table->unsignedBigInteger('stock_movement_id')->nullable();
                $table->unsignedBigInteger('counted_by')->nullable();
                $table->timestamp('counted_at')->nullable();
                $table->timestamps();

                $table->unique(['stock_count_id', 'item_id'], 'uq_stock_count_lines_count_item');
                $table->foreign('stock_count_id', 'fk_stock_count_lines_count')->references('id')->on('stock_counts')->cascadeOnDelete();
                $table->foreign('item_id', 'fk_stock_count_lines_item')->references('id')->on('items')->restrictOnDelete();
                $table->foreign('stock_movement_id', 'fk_stock_count_lines_movement')->references('id')->on('stock_movements')->nullOnDelete();
                $table->foreign('counted_by', 'fk_stock_count_lines_counter')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
    }
};
