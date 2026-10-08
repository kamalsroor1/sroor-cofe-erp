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
        // 1. Stores & Wholesale Vans Table
        if (! Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code', 50)->unique()->index();
                $table->string('type', 50)->default('retail_shop')->index(); // retail_shop, wholesale_van, main_warehouse
                $table->string('phone', 50)->nullable();
                $table->text('address')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_main')->default(false)->index();
                $table->timestamps();
            });
        }

        // 2. Store Stocks Table (Per Store/Van inventory and custom selling price)
        if (! Schema::hasTable('store_stocks')) {
            Schema::create('store_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
                $table->decimal('quantity', 12, 3)->default(0.000);
                $table->decimal('min_stock', 12, 3)->default(0.000);
                $table->decimal('custom_selling_price', 12, 3)->nullable();
                $table->timestamps();

                $table->unique(['store_id', 'item_id']);
            });
        }

        // 3. Store User Assignment (Pivot)
        if (! Schema::hasTable('store_user')) {
            Schema::create('store_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['store_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_user');
        Schema::dropIfExists('store_stocks');
        Schema::dropIfExists('stores');
    }
};
