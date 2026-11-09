<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-01 (tenant DB): a return line points at the invoice line (sales) or purchase line
 * (purchase) it returns, so the returnable quantity and the refund price come from the
 * original line. Nullable, no backfill: legacy returns and returns booked with the
 * `returns.without_invoice` permission have no link.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('return_items')) {
            return;
        }

        foreach (['invoice_item_id', 'purchase_item_id'] as $column) {
            if (! Schema::hasColumn('return_items', $column)) {
                Schema::table('return_items', function (Blueprint $table) use ($column): void {
                    $table->unsignedBigInteger($column)->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('return_items')) {
            return;
        }

        foreach (['invoice_item_id', 'purchase_item_id'] as $column) {
            if (Schema::hasColumn('return_items', $column)) {
                Schema::table('return_items', function (Blueprint $table) use ($column): void {
                    $table->dropIndex([$column]);
                    $table->dropColumn($column);
                });
            }
        }
    }
};
