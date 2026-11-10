<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-08 / POS-18 (tenant DB): a manager's 4-6 digit approval PIN for below-minimum-price sales.
 * Stored hashed only (never fillable, never serialized).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'pos_pin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('pos_pin')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'pos_pin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('pos_pin');
            });
        }
    }
};
