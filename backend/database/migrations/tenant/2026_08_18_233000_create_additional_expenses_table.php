<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create polymorphic additional expenses table
        Schema::create('additional_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 100)->index(); // App\Models\Purchase or App\Models\Invoice
            $table->unsignedBigInteger('document_id')->index();
            $table->string('title', 150); // شحن ونقل، عتالة وتنزيل، تغليف وكراتين، جمارك/نولون، إكراميات، أخرى
            $table->decimal('amount', 12, 3)->default(0.000);
            $table->string('allocation_method', 50)->default('by_quantity'); // by_quantity, by_value, equal
            $table->string('paid_by', 50)->default('supplier_account'); // supplier_account, treasury_cash, treasury_instapay, treasury_e_wallet
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['document_type', 'document_id']);
        });

        // 2. Add columns to purchases table
        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'additional_expenses_total')) {
                $table->decimal('additional_expenses_total', 12, 3)->default(0.000)->after('discount_amount');
            }
        });

        // 3. Add columns to purchase_items table
        Schema::table('purchase_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_items', 'base_cost_price')) {
                $table->decimal('base_cost_price', 12, 3)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('purchase_items', 'allocated_expense')) {
                $table->decimal('allocated_expense', 12, 3)->default(0.000)->after('base_cost_price');
            }
        });

        // 4. Add columns to invoices table
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'shipping_cost')) {
                $table->decimal('shipping_cost', 12, 3)->default(0.000)->after('discount_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'shipping_cost')) {
                $table->dropColumn('shipping_cost');
            }
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_items', 'allocated_expense')) {
                $table->dropColumn('allocated_expense');
            }
            if (Schema::hasColumn('purchase_items', 'base_cost_price')) {
                $table->dropColumn('base_cost_price');
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'additional_expenses_total')) {
                $table->dropColumn('additional_expenses_total');
            }
        });

        Schema::dropIfExists('additional_expenses');
    }
};
