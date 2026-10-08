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
        $tables = ['invoices', 'purchases', 'expenses', 'returns', 'cash_shifts', 'stock_movements'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'store_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('store_id')->nullable()->index();
                });
            }
        }

        // Users default_store_id
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'default_store_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->unsignedBigInteger('default_store_id')->nullable()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['invoices', 'purchases', 'expenses', 'returns', 'cash_shifts', 'stock_movements'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'store_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('store_id');
                });
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'default_store_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropColumn('default_store_id');
            });
        }
    }
};
