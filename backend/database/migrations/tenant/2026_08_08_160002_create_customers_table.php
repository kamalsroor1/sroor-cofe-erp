<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('phone', 50)->nullable()->index();
            $table->string('address')->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->string('price_tier', 20)->default('retail')->index();
            $table->decimal('current_balance', 12, 3)->default(0.000);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
