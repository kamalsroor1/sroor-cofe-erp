<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store/date composite indexes for invoice listings and stock movement filters by branch and date.
 *
 * Online-safe on existing tenants (index creation only, no FK mutations or full-table updates):
 * each index is created only when the table and columns exist and no index already covers the
 * exact column list; down() drops only these named indexes while keeping foreign keys indexed.
 */
return new class extends Migration
{
    /**
     * name => [table, columns]
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const INDEXES = [
        'invoices_store_date_idx' => ['invoices', ['store_id', 'invoice_date']],
        'stock_movements_store_created_idx' => ['stock_movements', ['store_id', 'created_at']],
        'stock_movements_store_type_created_idx' => ['stock_movements', ['store_id', 'movement_type', 'created_at']],
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
                return false;
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
