<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('items') && !Schema::hasColumn('items', 'min_selling_price')) {
            Schema::table('items', function (Blueprint $table) {
                $table->decimal('min_selling_price', 12, 3)->default(0.000)->after('cost_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('items') && Schema::hasColumn('items', 'min_selling_price')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('min_selling_price');
            });
        }
    }
};
