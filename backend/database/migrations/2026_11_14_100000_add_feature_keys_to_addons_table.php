<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-01 (central DB): an add-on can now switch on SEVERAL features (the "control" bundle:
 * reports.advanced + audit.logs + purchases.reorder). `feature_key` (single) stays for the
 * existing add-ons; `feature_keys` is a nullable JSON list read together with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('addons') || Schema::hasColumn('addons', 'feature_keys')) {
            return;
        }

        Schema::table('addons', function (Blueprint $table): void {
            $table->json('feature_keys')->nullable()->after('feature_key');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('addons') || ! Schema::hasColumn('addons', 'feature_keys')) {
            return;
        }

        Schema::table('addons', function (Blueprint $table): void {
            $table->dropColumn('feature_keys');
        });
    }
};
