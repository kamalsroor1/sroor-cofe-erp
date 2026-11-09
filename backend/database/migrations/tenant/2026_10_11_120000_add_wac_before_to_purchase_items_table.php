<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-03 follow-up (tenant DB): snapshot of the item's costs right before a purchase line
 * was applied (on create and on restore).
 *
 * - wac_before: the effective weighted average cost. weighted_avg_cost is stored at scale 3,
 *   so reversing a purchase with the moving-average formula can drift by +-0.001 when the
 *   average is not exact. When the purchase is still the item's last cost-affecting
 *   movement, the cancel restores this value exactly instead.
 * - cost_price_before: the item's cost_price at that moment. The formula path can only fall
 *   back to the previous confirmed purchase's landed cost (or the new average), so a cost
 *   entered by hand / an opening cost with no earlier purchase was lost on cancel.
 *
 * Nullable, no backfill: old rows keep NULL and keep using the formula.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_items')) {
            return;
        }

        if (! Schema::hasColumn('purchase_items', 'wac_before')) {
            Schema::table('purchase_items', function (Blueprint $table): void {
                $table->decimal('wac_before', 12, 3)->nullable();
            });
        }

        if (! Schema::hasColumn('purchase_items', 'cost_price_before')) {
            Schema::table('purchase_items', function (Blueprint $table): void {
                $table->decimal('cost_price_before', 12, 3)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_items')) {
            return;
        }

        if (Schema::hasColumn('purchase_items', 'cost_price_before')) {
            Schema::table('purchase_items', function (Blueprint $table): void {
                $table->dropColumn('cost_price_before');
            });
        }

        if (Schema::hasColumn('purchase_items', 'wac_before')) {
            Schema::table('purchase_items', function (Blueprint $table): void {
                $table->dropColumn('wac_before');
            });
        }
    }
};
