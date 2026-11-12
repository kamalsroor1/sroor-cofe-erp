<?php

declare(strict_types=1);

namespace App\Support\Database;

use App\Exceptions\DatabaseBusyException;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use PDOException;
use Throwable;

/**
 * BUG-07: run one money/stock unit of work in a transaction and restart it on a lock conflict.
 *
 * Lock conflicts are recognised by the database driver error code (errorInfo[1]) anywhere in
 * the exception chain, never by the message text:
 *  - 1213 deadlock (InnoDB rolled the whole transaction back)            -> RETRIED;
 *  - 1020 "Record has changed since last read" (MariaDB 11.6.2+ with
 *    innodb_snapshot_isolation=ON under REPEATABLE READ)                  -> RETRIED;
 *  - 1205 lock wait timeout                                                -> NOT retried, 409 at once.
 *    It means another transaction kept the row for the whole innodb_lock_wait_timeout (50 s by
 *    default): a holder that slow is not a race a retry can win, and three attempts would keep
 *    the cashier waiting up to 150 s for the same 409. Shortening the session timeout instead
 *    would need a SET SESSION before and a restore after every transaction (state that leaks on
 *    persistent queue/Octane connections when the restore is skipped) and would also cut short
 *    legitimate long waits of the same session.
 * The chain walk matters: a conflict raised inside a NESTED DB::transaction (e.g. a balance
 * recompute that opens its own transaction) reaches us as Illuminate\Database\DeadlockException,
 * a PDOException without errorInfo whose previous exception is the QueryException with the code.
 * Validation errors, domain exceptions (insufficient stock, BusinessRuleException), duplicate keys
 * (1062) and every other failure are rethrown untouched on the first attempt.
 *
 * Every attempt runs $callback from scratch in a NEW transaction, so it must be a closed unit
 * of work: read and lock everything inside, capture only plain input (arrays, ids, DTOs) from
 * outside, and push side effects (notifications, jobs, HTTP) to after commit (afterCommit jobs
 * and DB::afterCommit() callbacks of a rolled-back attempt are discarded by Laravel). Numbers
 * taken from DocumentNumberService inside the callback roll back with the failed attempt and are
 * issued again by the next one, so a retry never duplicates or skips a number.
 *
 * Lock order inside the callback: see StockService::lockStockRows() (the one global order).
 *
 * Isolation: on MySQL/MariaDB each attempt runs at the configured level (default READ
 * COMMITTED, `database.transaction_retry.isolation`). Every balance and stock row these flows
 * change is read with SELECT ... FOR UPDATE (a current read at any level), so the level only
 * changes what the plain reads see: the latest committed data instead of a snapshot taken at
 * the first read. That removes the 1020 class on MariaDB altogether (the snapshot check only
 * exists under REPEATABLE READ) and drops most gap locks behind 1213 on both engines. It needs
 * binlog_format ROW or MIXED when the binary log is on (`php artisan db:check-write-isolation`).
 *
 * Nested call (a transaction is already open): no retry and no isolation change here, because
 * the work done before this call cannot be restarted from inside it. The error propagates to
 * the outermost owner (another run() retries it; anything else ends as DatabaseBusyException,
 * 409, through the exception handler).
 *
 * After the last attempt a lock conflict becomes DatabaseBusyException (HTTP 409, translated
 * "busy, try again"), never a 500.
 */
final class TransactionRetry
{
    public const DEADLOCK = 1213;

    public const LOCK_WAIT_TIMEOUT = 1205;

    public const RECORD_CHANGED = 1020;

    public const DEFAULT_ATTEMPTS = 3;

    /** Restarting the transaction can win these. */
    private const RETRYABLE_ERROR_CODES = [self::DEADLOCK, self::RECORD_CHANGED];

    /** Answered 409 "busy, try again" instead of 500. */
    private const LOCK_CONFLICT_ERROR_CODES = [self::DEADLOCK, self::LOCK_WAIT_TIMEOUT, self::RECORD_CHANGED];

    /** SQLSTATE of a serialization failure (InnoDB deadlock), used when no driver code is attached. */
    private const SERIALIZATION_FAILURE_SQLSTATE = '40001';

    /** Whitelist: the level is spliced into SQL, so only these literal values are ever used. */
    private const ISOLATION_LEVELS = ['READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE'];

    /** Backoff before attempt N+1: BASE * 2^(N-1) plus 0..JITTER, in microseconds. */
    private const BACKOFF_BASE_US = 25_000;

    private const BACKOFF_JITTER_US = 25_000;

    /** How deep the previous-exception chain is searched for the driver code. */
    private const MAX_CHAIN_DEPTH = 8;

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     *
     * @throws DatabaseBusyException when the attempts ran out on lock conflicts, or at once on 1205
     * @throws Throwable anything else the callback throws, rethrown untouched (validation, domain rules...)
     */
    public static function run(Closure $callback, ?int $attempts = null): mixed
    {
        $connection = DB::connection();

        if ($connection->transactionLevel() > 0) {
            return $connection->transaction($callback);
        }

        $attempts = max(1, $attempts ?? self::configuredAttempts());

        for ($attempt = 1; ; $attempt++) {
            try {
                self::applyIsolation($connection);

                return $connection->transaction($callback);
            } catch (Throwable $e) {
                if (! self::isLockConflict($e)) {
                    throw $e;
                }

                if (! self::isRetryable($e) || $attempt >= $attempts) {
                    throw DatabaseBusyException::fromThrowable($e, $attempt);
                }

                Sleep::usleep(self::BACKOFF_BASE_US * (2 ** ($attempt - 1)) + random_int(0, self::BACKOFF_JITTER_US));
            }
        }
    }

    /** A lock conflict that restarting the whole transaction can win: 1213 / 1020. */
    public static function isRetryable(Throwable $e): bool
    {
        $code = self::driverCode($e);

        return $code !== null && in_array($code, self::RETRYABLE_ERROR_CODES, true);
    }

    /** Any lock conflict (1213 / 1205 / 1020): answered 409 "busy, try again", never 500. */
    public static function isLockConflict(Throwable $e): bool
    {
        $code = self::driverCode($e);

        return $code !== null && in_array($code, self::LOCK_CONFLICT_ERROR_CODES, true);
    }

    /**
     * The isolation level run() applies on MySQL/MariaDB, or null when it keeps the server's.
     */
    public static function isolationLevel(): ?string
    {
        $level = strtoupper(trim((string) config('database.transaction_retry.isolation', 'READ COMMITTED')));

        return in_array($level, self::ISOLATION_LEVELS, true) ? $level : null;
    }

    /**
     * The MySQL/MariaDB error code of the first PDOException in the chain that carries one
     * (QueryException extends PDOException; Laravel's nested-transaction DeadlockException has
     * none of its own and wraps the QueryException). A chain with no code but SQLSTATE 40001
     * (serialization failure) counts as a deadlock.
     */
    private static function driverCode(Throwable $e): ?int
    {
        $sawSerializationFailure = false;

        for ($current = $e, $depth = 0; $current !== null && $depth < self::MAX_CHAIN_DEPTH; $current = $current->getPrevious(), $depth++) {
            if (! $current instanceof PDOException) {
                continue;
            }

            $code = $current->errorInfo[1] ?? null;
            if (is_numeric($code)) {
                return (int) $code;
            }

            $sqlState = $current->errorInfo[0] ?? $current->getCode();
            if ((string) $sqlState === self::SERIALIZATION_FAILURE_SQLSTATE) {
                $sawSerializationFailure = true;
            }
        }

        return $sawSerializationFailure ? self::DEADLOCK : null;
    }

    private static function configuredAttempts(): int
    {
        $configured = config('database.transaction_retry.attempts');

        return is_numeric($configured) ? (int) $configured : self::DEFAULT_ATTEMPTS;
    }

    /**
     * SET TRANSACTION (no SESSION keyword) applies to the next transaction only, which is the
     * one $connection->transaction() opens right after it. MySQL/MariaDB only; sqlite has a
     * single writer and no such statement.
     */
    private static function applyIsolation(Connection $connection): void
    {
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $level = self::isolationLevel();
        if ($level === null) {
            return; // empty / unknown: keep the server's level
        }

        $connection->statement('SET TRANSACTION ISOLATION LEVEL '.$level);
    }
}
