<?php

declare(strict_types=1);

namespace App\Support\Database;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * MIG-01: helpers that keep tenant migrations online-safe on a large live shop (MySQL 8.4 and
 * MariaDB 11.x). The shop keeps selling while a deploy migrates it, so a migration must never
 * hold a table for more than a moment:
 *
 *  - lock_wait_timeout is shortened for the migration only (default is one year): a migration
 *    that cannot get its metadata lock within a few seconds fails fast, is retried, and never
 *    queues every other connection behind itself;
 *  - foreign keys on a big table are added with foreign_key_checks=0 + ALGORITHM=INPLACE,
 *    LOCK=NONE (no table copy); this is only valid because the new column is entirely NULL;
 *  - backfills run in short id-range transactions instead of one giant UPDATE.
 *
 * Non-MySQL drivers (sqlite in tests) fall back to the ordinary schema builder.
 */
final class OnlineMigration
{
    public const LOCK_WAIT_SECONDS = 5;

    public const ATTEMPTS = 6;

    public const CHUNK = 2000;

    /** @var list<int> MySQL/MariaDB codes worth retrying: lock wait timeout, deadlock. */
    private const RETRYABLE = [1205, 1213];

    public static function isMysqlFamily(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * Run $callback with a short session lock_wait_timeout and retry on lock timeout / deadlock.
     * The previous session value is restored even when the callback throws.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function withShortLockTimeout(Closure $callback, int $seconds = self::LOCK_WAIT_SECONDS): mixed
    {
        if (! self::isMysqlFamily()) {
            return $callback();
        }

        $previous = DB::selectOne('select @@session.lock_wait_timeout as v');
        $previousValue = $previous !== null ? (int) $previous->v : 31536000;

        DB::statement('SET SESSION lock_wait_timeout = '.max(1, $seconds));

        try {
            for ($attempt = 1; ; $attempt++) {
                try {
                    return $callback();
                } catch (Throwable $e) {
                    if ($attempt >= self::ATTEMPTS || ! self::isRetryable($e) || DB::transactionLevel() > 0) {
                        throw $e;
                    }
                    Sleep::for(min(5, $attempt) * 500)->milliseconds();
                }
            }
        } finally {
            DB::statement('SET SESSION lock_wait_timeout = '.$previousValue);
        }
    }

    /**
     * Add a nullable unsigned bigint column plus its foreign key without copying the table.
     * Idempotent: a missing column is added, then a missing key is added (so a run that died
     * between the two steps heals itself).
     *
     * @param  'set null'|'cascade'|'restrict'  $onDelete
     */
    public static function addNullableForeignKey(string $table, string $column, string $refTable, string $onDelete = 'set null'): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $constraint = "{$table}_{$column}_foreign";
        $onDeleteSql = strtoupper($onDelete);

        if (! self::isMysqlFamily()) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, function ($blueprint) use ($column, $refTable, $onDelete): void {
                    $fk = $blueprint->foreignId($column)->nullable()->constrained($refTable);
                    $onDelete === 'set null' ? $fk->nullOnDelete() : ($onDelete === 'cascade' ? $fk->cascadeOnDelete() : $fk->restrictOnDelete());
                });
            }

            return;
        }

        self::withShortLockTimeout(function () use ($table, $column, $refTable, $constraint, $onDeleteSql): void {
            if (! Schema::hasColumn($table, $column)) {
                self::alter("ALTER TABLE `{$table}` ADD COLUMN `{$column}` BIGINT UNSIGNED NULL", 'ALGORITHM=INSTANT');
            }

            if (self::hasForeignKeyOn($table, $column)) {
                return;
            }

            // Safe only because the column is brand new (all NULL): nothing needs validating.
            DB::statement('SET SESSION foreign_key_checks = 0');
            try {
                self::alter(
                    "ALTER TABLE `{$table}` ADD INDEX `{$constraint}` (`{$column}`), "
                    ."ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`id`) ON DELETE {$onDeleteSql}",
                    'ALGORITHM=INPLACE, LOCK=NONE'
                );
            } finally {
                DB::statement('SET SESSION foreign_key_checks = 1');
            }
        });
    }

    /**
     * Walk $table by primary-key ranges and call $apply(from, to) for each, every range in its
     * own short transaction (row locks only for that range). $apply must be idempotent and
     * must restrict itself to whereBetween('id', [from, to]).
     *
     * @param  Closure(int, int): void  $apply
     */
    public static function forEachIdRange(string $table, Closure $apply, int $chunk = self::CHUNK): void
    {
        $min = DB::table($table)->min('id');
        if ($min === null) {
            return;
        }
        $max = (int) DB::table($table)->max('id');

        for ($from = (int) $min; $from <= $max; $from += $chunk) {
            $to = $from + $chunk - 1;
            self::withShortLockTimeout(function () use ($apply, $from, $to): void {
                DB::transaction(static function () use ($apply, $from, $to): void {
                    $apply($from, $to);
                });
            });
        }
    }

    private static function hasForeignKeyOn(string $table, string $column): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (in_array($column, $foreignKey['columns'], true)) {
                return true;
            }
        }

        return false;
    }

    /** Run an ALTER with online hints; if the engine rejects the hint, run it plain (still idempotent-guarded by callers). */
    private static function alter(string $sql, string $hints): void
    {
        try {
            DB::statement($sql.', '.$hints);
        } catch (QueryException $e) {
            if (! self::isHintRejected($e)) {
                throw $e;
            }
            DB::statement($sql);
        }
    }

    private static function isHintRejected(QueryException $e): bool
    {
        $code = (int) ($e->errorInfo[1] ?? 0);

        // 1845/1846: ALGORITHM/LOCK not supported for this operation; 1801/1064: hint unknown to this engine version.
        return in_array($code, [1845, 1846, 1801, 1064], true);
    }

    private static function isRetryable(Throwable $e): bool
    {
        for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
            if ($cur instanceof QueryException && in_array((int) ($cur->errorInfo[1] ?? 0), self::RETRYABLE, true)) {
                return true;
            }
            if ($cur instanceof \PDOException && in_array((int) ($cur->errorInfo[1] ?? 0), self::RETRYABLE, true)) {
                return true;
            }
        }

        return false;
    }
}
