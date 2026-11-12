<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERF-01: composite indexes for the report, dashboard, statement and shift queries.
 *
 * Tenant migration (runs on every tenant database). Safe on existing tenants:
 *  - an index is created only when the table and all its columns exist AND no existing index
 *    already covers exactly the same columns in the same order (whatever its name), and its
 *    name is not taken;
 *  - down() drops only the indexes of this list, by their PERF-01 name, when present (an index
 *    that already existed under another name was never created here and is left alone).
 *    InnoDB silently drops the implicit index of a foreign key once a composite index starting
 *    with the FK column exists (e.g. invoices.customer_id). Before dropping such a composite,
 *    down() re-creates a plain index on the FK columns when no other index would cover them,
 *    otherwise MySQL refuses the drop (1553 "needed in a foreign key constraint").
 * Running up() or down() twice is a no-op.
 */
return new class extends Migration
{
    /**
     * name => [table, columns]
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const INDEXES = [
        // Store-scoped reports, dashboard, P&L: WHERE store_id = ? AND status = ? AND invoice_date range.
        'invoices_store_status_date_idx' => ['invoices', ['store_id', 'status', 'invoice_date']],
        // All-stores reports over a period: WHERE status = ? AND invoice_date range.
        'invoices_status_date_idx' => ['invoices', ['status', 'invoice_date']],
        // Customer statement / opening balance and the customers report.
        'invoices_customer_status_date_idx' => ['invoices', ['customer_id', 'status', 'invoice_date']],
        // Item card / item movement ledger of one item in one branch.
        'stock_movements_item_store_created_idx' => ['stock_movements', ['item_id', 'store_id', 'created_at']],
        // Customer statement (receipts) and the branch drawer / treasury vouchers of a period.
        'payments_customer_date_idx' => ['payments', ['customer_id', 'payment_date']],
        'payments_store_date_idx' => ['payments', ['store_id', 'payment_date']],
        // The open shift of a branch.
        'cash_shifts_store_status_idx' => ['cash_shifts', ['store_id', 'status']],
        // Returns side of the reports and the customer statement.
        'returns_type_date_idx' => ['returns', ['return_type', 'return_date']],
        'returns_customer_date_idx' => ['returns', ['customer_id', 'return_date']],
        // Branch expenses of a period (P&L, dashboard, daily journal).
        'expenses_store_date_idx' => ['expenses', ['store_id', 'expense_date']],
        // Soft-delete filters (WHERE deleted_at IS NULL) on the large document / master tables.
        'invoices_deleted_at_idx' => ['invoices', ['deleted_at']],
        'payments_deleted_at_idx' => ['payments', ['deleted_at']],
        'returns_deleted_at_idx' => ['returns', ['deleted_at']],
        'expenses_deleted_at_idx' => ['expenses', ['deleted_at']],
        'items_deleted_at_idx' => ['items', ['deleted_at']],
        'customers_deleted_at_idx' => ['customers', ['deleted_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => [$table, $columns]) {
            if (! $this->canCreate($table, $columns, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                $blueprint->index($columns, $name);
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $name => [$table]) {
            if (! Schema::hasTable($table) || ! $this->hasIndexNamed($table, $name)) {
                continue;
            }

            $this->keepForeignKeysIndexed($table, $name);

            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropIndex($name);
            });
        }
    }

    /**
     * Give every foreign key of $table that only $dropping covers its own plain index.
     */
    private function keepForeignKeysIndexed(string $table, string $dropping): void
    {
        $indexes = Schema::getIndexes($table);

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            $fkColumns = array_map('strtolower', $foreignKey['columns']);
            $coveredByOthers = false;
            $coveredByDropping = false;

            foreach ($indexes as $index) {
                $leading = array_slice(array_map('strtolower', $index['columns']), 0, count($fkColumns));
                if ($leading !== $fkColumns) {
                    continue;
                }
                if (strcasecmp((string) $index['name'], $dropping) === 0) {
                    $coveredByDropping = true;
                } else {
                    $coveredByOthers = true;
                }
            }

            if ($coveredByDropping && ! $coveredByOthers) {
                $columns = $foreignKey['columns'];
                Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                    $blueprint->index($columns);
                });
                $indexes = Schema::getIndexes($table);
            }
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function canCreate(string $table, array $columns, string $name): bool
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumns($table, $columns)) {
            return false;
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (strcasecmp((string) $index['name'], $name) === 0) {
                return false;
            }
            if (array_map('strtolower', $index['columns']) === array_map('strtolower', $columns)) {
                return false; // the same index already exists under another name
            }
        }

        return true;
    }

    private function hasIndexNamed(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (strcasecmp((string) $index['name'], $name) === 0) {
                return true;
            }
        }

        return false;
    }
};
