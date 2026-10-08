<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POSB-2 (tenant DB): one POS settings row per store (branch). Holds the scale-label
 * barcode parser settings and the max discount percent used by POSB-1. A store with no
 * row uses App\Models\StorePosSetting defaults; rows are created on the first PUT only.
 * `scale_prefixes` has no DB default (JSON defaults are not portable to MySQL < 8.0.13);
 * the model supplies ["20".."29"].
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_pos_settings')) {
            return;
        }

        Schema::create('store_pos_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->boolean('scale_barcode_enabled')->default(false);
            $table->json('scale_prefixes')->nullable();
            $table->unsignedTinyInteger('scale_plu_length')->default(5);
            $table->string('scale_value_type', 10)->default('weight');
            $table->unsignedTinyInteger('scale_value_length')->default(5);
            $table->unsignedInteger('scale_weight_divisor')->default(1000);
            $table->unsignedInteger('scale_price_divisor')->default(100);
            $table->boolean('scale_check_digit')->default(true);
            $table->decimal('max_discount_percent', 6, 3)->nullable();
            $table->timestamps();

            $table->unique('store_id', 'store_pos_settings_store_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_pos_settings');
    }
};
