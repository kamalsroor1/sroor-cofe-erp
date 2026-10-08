<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add payment_method to invoices table
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'payment_method')) {
                $table->string('payment_method', 50)->default('cash')->after('payment_type')->index();
            }
        });

        // 2. Modify payment_method in payments table to support all new methods seamlessly
        Schema::table('payments', function (Blueprint $table) {
            // If SQLite or MySQL, change payment_method to string 50
            $table->string('payment_method', 50)->default('cash')->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
