<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('image', 500)->nullable()->after('name');
            $table->unsignedInteger('pos_sort_order')->default(0)->after('is_active');
            $table->boolean('is_pos_pinned')->default(false)->after('pos_sort_order');
            $table->unsignedInteger('pos_sales_count')->default(0)->after('is_pos_pinned');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['image', 'pos_sort_order', 'is_pos_pinned', 'pos_sales_count']);
        });
    }
};
