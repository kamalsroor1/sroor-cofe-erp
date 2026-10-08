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
        $tables = [
            'items',
            'customers',
            'suppliers',
            'stores',
            'store_stocks',
            'users',
            'expenses',
            'invoices',
            'invoice_items',
            'purchases',
            'purchase_items',
            'payments',
            'returns',
            'return_items',
            'stock_transfers',
            'stock_transfer_items',
            'stock_deposits',
            'cash_shifts',
            'settings',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'items',
            'customers',
            'suppliers',
            'stores',
            'store_stocks',
            'users',
            'expenses',
            'invoices',
            'invoice_items',
            'purchases',
            'purchase_items',
            'payments',
            'returns',
            'return_items',
            'stock_transfers',
            'stock_transfer_items',
            'stock_deposits',
            'cash_shifts',
            'settings',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropSoftDeletes();
                });
            }
        }
    }
};
