<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENTI-1.10 (central, CTO 2026-10-09 Q12): credits add-ons.
 *
 * `addons.type` is string(20) + the App\Enums\Billing\AddonType cast, so the new `credits`
 * value needs no schema change. This adds the per-cycle allowance a credits add-on grants:
 * `included_credits` DECIMAL(12,3), NULL for every other add-on type. Nothing is seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('addons') || Schema::hasColumn('addons', 'included_credits')) {
            return;
        }

        Schema::table('addons', function (Blueprint $table): void {
            $table->decimal('included_credits', 12, 3)->nullable()->after('price_tiers');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('addons') || ! Schema::hasColumn('addons', 'included_credits')) {
            return;
        }

        Schema::table('addons', function (Blueprint $table): void {
            $table->dropColumn('included_credits');
        });
    }
};
