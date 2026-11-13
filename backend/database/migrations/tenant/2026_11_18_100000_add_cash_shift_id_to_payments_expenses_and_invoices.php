<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATE-01 (tenant DB): the cash shift a payment voucher, an expense and an invoice belong to,
 * stamped when the row is created (App\Services\Shifts\ShiftAttribution). NULL on every existing
 * row on purpose: history keeps being attributed by the old time window, nothing is backfilled.
 *
 * Built to be cheap on a large shop:
 *  - a plain nullable column, no default, no position (MySQL 8.0 / MariaDB 10.3+ add it in place
 *    with ALGORITHM=INSTANT, no table copy), in its own ALTER;
 *  - one secondary index (cash_shift_id, created_at), built online (writes are not blocked). It
 *    serves both halves of the shift filter: cash_shift_id = S, and cash_shift_id IS NULL with a
 *    created_at window (unstamped history);
 *  - no foreign key: adding one with foreign_key_checks on forces a table copy (writes blocked for
 *    the whole rebuild) and validates every row; shifts are only soft-deleted and the column is
 *    only ever written server-side.
 * Guarded (hasTable / hasColumn / index name), so running up() or down() twice is a no-op.
 */
return new class extends Migration
{
    private const COLUMN = 'cash_shift_id';

    /** table => index name */
    private const TABLES = [
        'payments' => 'payments_cash_shift_created_idx',
        'expenses' => 'expenses_cash_shift_created_idx',
        'invoices' => 'invoices_cash_shift_created_idx',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $index) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, self::COLUMN)) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->unsignedBigInteger(self::COLUMN)->nullable();
                });
            }

            if (Schema::hasColumn($table, 'created_at') && ! $this->hasIndexNamed($table, $index)) {
                Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                    $blueprint->index([self::COLUMN, 'created_at'], $index);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $index) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if ($this->hasIndexNamed($table, $index)) {
                Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                    $blueprint->dropIndex($index);
                });
            }

            if (Schema::hasColumn($table, self::COLUMN)) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn(self::COLUMN);
                });
            }
        }
    }

    private function hasIndexNamed(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $existing) {
            if (strcasecmp((string) $existing['name'], $name) === 0) {
                return true;
            }
        }

        return false;
    }
};
