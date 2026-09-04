<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number', 50)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('store_id')->constrained('stores');
            $table->date('quotation_date');
            $table->date('valid_until')->nullable();
            $table->string('pricing_tier', 20)->default('wholesale'); // wholesale, retail
            $table->string('status', 20)->default('draft'); // draft, sent, converted, expired, cancelled
            $table->foreignId('converted_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('subtotal', 12, 3)->default(0);
            $table->string('discount_type', 20)->default('fixed'); // fixed, percentage
            $table->decimal('discount_value', 12, 3)->default(0);
            $table->decimal('discount_amount', 12, 3)->default(0);
            $table->decimal('shipping_cost', 12, 3)->default(0);
            $table->decimal('net_total', 12, 3)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 12, 3)->default(0);
            $table->string('price_tier', 20)->default('wholesale'); // wholesale, retail, custom
            $table->decimal('discount_amount', 12, 3)->default(0);
            $table->decimal('total_price', 12, 3)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
