<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERF-02: indexes for the open shift's live totals, read on every dashboard request (a
 * created_at window on invoices and payments, which no index served: 50,000 invoices meant a
 * scan of all of them per request).
 *
 * Same guards as the PERF-01 index migration (2026_11_14_100000): an index is created only when
 * the table and its columns exist and no index already covers exactly the same columns (any
 * name); down() drops only these names, keeping any foreign key indexed. Running up() or
 * down() twice is a no-op.
 */
return new class extends Migration
{
    /**
     * name => [table, columns]
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const INDEXES = [
        // The open shift's live totals (ShiftService::calculateShiftTotals, dashboard, shift
        // screen): invoices of one branch created since the shift opened.
        'invoices_store_created_idx' => ['invoices', ['store_id', 'created_at']],
        // ... and the cash vouchers created since then. The shift totals still filter the
        // branch with Payment::forStore() (an OR of store_id and legacy NULL-store rows) and no
        // store-led index serves that OR, so the created_at window itself must be indexed.
        // (DailyCashFlowService filters payment_date, served by the payment_date index; the
        // treasury balances read the two forStore() halves separately and use store_id.)
        'payments_created_at_idx' => ['payments', ['created_at']],
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
