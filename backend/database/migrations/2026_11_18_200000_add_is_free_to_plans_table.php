<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-10 (central): `plans.is_free` marks a PERMANENT free plan. A tenant on it is always
 * `active`, has no trial or paid period, and is never billed. Schema only: the catalog `free`
 * plan is switched on by 2026_11_18_200200.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plans') || Schema::hasColumn('plans', 'is_free')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table): void {
            $table->boolean('is_free')->default(false)->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('plans') || ! Schema::hasColumn('plans', 'is_free')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropIndex(['is_free']);
            $table->dropColumn('is_free');
        });
    }
};
