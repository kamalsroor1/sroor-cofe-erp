<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-11 (central): two more plan limits, `plans.max_customers` and `plans.max_suppliers`
 * (App\Enums\LimitResource::Customers / Suppliers). NULL = unlimited, so every existing plan
 * stays unlimited; the catalog `free` plan gets its values from 2026_11_18_210100.
 */
return new class extends Migration
{
    private const COLUMNS = ['max_customers', 'max_suppliers'];

    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        foreach (self::COLUMNS as $column) {
            if (Schema::hasColumn('plans', $column)) {
                continue;
            }

            Schema::table('plans', function (Blueprint $table) use ($column): void {
                $table->unsignedInteger($column)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        foreach (self::COLUMNS as $column) {
            if (! Schema::hasColumn('plans', $column)) {
                continue;
            }

            Schema::table('plans', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }
};
