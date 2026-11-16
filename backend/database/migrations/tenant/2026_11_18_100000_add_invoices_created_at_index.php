<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-ENF: the monthly invoice cap (MonthlyInvoiceCounter) counts every invoice of the tenant
 * created in a business month: a created_at range across all branches. The existing
 * invoices_store_created_idx leads with store_id and cannot serve it, so the count would scan the
 * table on every sale of a capped plan.
 *
 * Same guards as the PERF-02 index migration (2026_11_16_100000): created only when the table and
 * column exist and no index already covers exactly created_at (any name); down() drops only this
 * name (created_at is no foreign key). Running up() or down() twice is a no-op.
 */
return new class extends Migration
{
    private const TABLE = 'invoices';

    private const NAME = 'invoices_created_at_idx';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'created_at')) {
            return;
        }

        foreach (Schema::getIndexes(self::TABLE) as $index) {
            if (strcasecmp((string) $index['name'], self::NAME) === 0
                || array_map('strtolower', $index['columns']) === ['created_at']) {
                return;
            }
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(['created_at'], self::NAME);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasIndex(self::TABLE, self::NAME)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::NAME);
        });
    }
};
