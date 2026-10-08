<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Stock Transfers (Between Warehouses, Branches, and Vans)
        if (! Schema::hasTable('stock_transfers')) {
            Schema::create('stock_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('transfer_number', 50)->unique()->index();
                $table->unsignedBigInteger('from_store_id')->index();
                $table->unsignedBigInteger('to_store_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->date('transfer_date')->index();
                $table->string('status', 50)->default('pending')->index(); // pending, confirmed, cancelled
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('from_store_id', 'fk_transfers_from_store')->references('id')->on('stores')->restrictOnDelete();
                $table->foreign('to_store_id', 'fk_transfers_to_store')->references('id')->on('stores')->restrictOnDelete();
                $table->foreign('user_id', 'fk_transfers_user')->references('id')->on('users')->restrictOnDelete();
            });
        }

        // 2. Stock Transfer Items
        if (! Schema::hasTable('stock_transfer_items')) {
            Schema::create('stock_transfer_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_transfer_id')->index();
                $table->unsignedBigInteger('item_id')->index();
                $table->decimal('quantity', 12, 3);
                $table->timestamps();

                $table->foreign('stock_transfer_id', 'fk_transfer_items_transfer')->references('id')->on('stock_transfers')->cascadeOnDelete();
                $table->foreign('item_id', 'fk_transfer_items_item')->references('id')->on('items')->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
