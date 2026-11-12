<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\TenantClock;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * BE-09: race-free document numbers (invoices, purchases, returns, transfers, payments,
 * expenses, adjustments, shifts) on the TENANT connection.
 *
 * Each number scope (normally the visible prefix including the business date, e.g.
 * "INV-MAIN-20261110") has one row in `document_sequences`. next() locks that row with
 * SELECT ... FOR UPDATE, increments it and formats "{prefix}-{zero padded value}". The row
 * lock serialises concurrent issuers, so a number is never handed out twice.
 *
 * Transaction contract: call next() INSIDE the transaction that inserts the document. The
 * increment then commits or rolls back together with the document, and the lock is held
 * until commit (keep the work after the call short). If there is no open transaction,
 * next() wraps itself in one: the number is still unique (the counter is committed), but the
 * lock is released before the caller inserts, so a rolled-back document leaves a gap.
 *
 * Lock order rule: lock the party (customer/supplier) and document rows first, allocate
 * numbers after. The sequence row is held until commit, so a flow that allocated a number
 * and only then locked a customer/supplier would deadlock (MySQL 1213) with a flow that
 * locks the party first. Among sequences, flows allocate the document number before
 * payment/expense numbers. Keep the transaction short after the first call. BUG-07: flows
 * that move stock lock their items/store_stocks rows (StockService::lockStockRows(), sorted)
 * right after the party/document rows, i.e. also BEFORE their numbers.
 *
 * Daily scopes (BUG-04): a flow creates the counter rows it needs with prepareFor() /
 * prepareDated() before opening its transaction; see ensureScopeRow() for why.
 *
 * Seeding: the first use of a scope (a tenant upgrading with numbers already issued, or a
 * manually typed number) starts from the highest sequence that already exists, read once
 * through the $seed callback, so the counter never re-issues a taken number.
 */
final class DocumentNumberService
{
    private const TABLE = 'document_sequences';

    public function __construct(
        private readonly TenantClock $tenantClock,
    ) {}

    /**
     * next() for the common case: "{prefix}-{business date YYYYMMDD}-{sequence}" where the
     * seed and the exists-check read the documents of $modelClass (soft-deleted ones
     * included, so a deleted document's number is never reused).
     *
     * @param  class-string<Model>  $modelClass
     * @param  string|null  $scopePrefix  counter key prefix when it must differ from $prefix (before the date)
     */
    public function nextDated(string $prefix, int $pad, string $modelClass, string $column, ?string $scopePrefix = null): string
    {
        $date = str_replace('-', '', $this->tenantClock->businessDate());

        return $this->nextFor($prefix.'-'.$date, $pad, $modelClass, $column, $scopePrefix !== null ? $scopePrefix.'-'.$date : null);
    }

    /**
     * next() with the seed and the exists-check wired to the documents of $modelClass.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function nextFor(string $prefix, int $pad, string $modelClass, string $column, ?string $scope = null): string
    {
        $query = $this->documentsQuery($modelClass);

        return $this->next(
            $prefix,
            $pad,
            fn (string $candidate): bool => $query()->where($column, $candidate)->exists(),
            fn (): int => $this->highestSequence($query(), $column, $prefix),
            $scope,
        );
    }

    /**
     * BUG-04: create the counter row of a nextDated() scope BEFORE the document transaction.
     *
     * Call it with no transaction open, right before the DB::transaction()/TransactionRetry
     * that will call nextDated() with the same arguments. The row is then committed before the
     * transaction's first read, so the allocation inside finds it on the hot path. Without it,
     * the first document of a scope each day inserted the row on a side connection AFTER the
     * transaction's read view existed, and MariaDB 11.6.2+ (innodb_snapshot_isolation=ON)
     * refused the locking read with 1020 "Record has changed since last read".
     *
     * Allocates nothing (no number is consumed, so no gap). No-op inside an open transaction:
     * a write there would join the caller's transaction, which is what this step avoids.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function prepareDated(string $prefix, string $modelClass, string $column, ?string $scopePrefix = null): void
    {
        $date = str_replace('-', '', $this->tenantClock->businessDate());

        $this->prepareFor($prefix.'-'.$date, $modelClass, $column, $scopePrefix !== null ? $scopePrefix.'-'.$date : null);
    }

    /**
     * prepareDated() for a nextFor() scope (same $prefix / $scope as the later nextFor() call).
     *
     * @param  class-string<Model>  $modelClass
     */
    public function prepareFor(string $prefix, string $modelClass, string $column, ?string $scope = null): void
    {
        if (DB::transactionLevel() > 0) {
            return;
        }

        $query = $this->documentsQuery($modelClass);

        $this->ensureScopeRow($scope ?? $prefix, fn (): int => $this->highestSequence($query(), $column, $prefix));
    }

    /**
     * Soft-deleted rows included (what withTrashed() does) so a deleted document keeps its number.
     *
     * @param  class-string<Model>  $modelClass
     * @return Closure(): EloquentBuilder<Model>
     */
    private function documentsQuery(string $modelClass): Closure
    {
        return fn (): EloquentBuilder => in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
            ? $modelClass::query()->withoutGlobalScope(SoftDeletingScope::class)
            : $modelClass::query();
    }

    /**
     * Allocate the next number of a scope.
     *
     * @param  string  $prefix  visible prefix without the trailing dash, e.g. "INV-MAIN-20261110"
     * @param  int  $pad  minimum digits of the sequence (wider numbers just grow)
     * @param  (callable(string): bool)|null  $existsCheck  true when the candidate number is already taken (safety net for numbers typed by hand)
     * @param  (callable(): int)|null  $seed  highest sequence already issued for the prefix; read only when the scope row does not exist yet
     * @param  string|null  $scope  counter key when it must differ from the prefix (two tables sharing a visible prefix); defaults to the prefix
     */
    public function next(string $prefix, int $pad, ?callable $existsCheck = null, ?callable $seed = null, ?string $scope = null): string
    {
        if (DB::transactionLevel() === 0) {
            // BUG-04: create the row before the transaction exists (see prepareFor()).
            $this->ensureScopeRow($scope ?? $prefix, $seed);

            return DB::transaction(fn (): string => $this->allocate($prefix, $pad, $existsCheck, $seed, $scope ?? $prefix));
        }

        return $this->allocate($prefix, $pad, $existsCheck, $seed, $scope ?? $prefix);
    }

    /**
     * Highest trailing numeric sequence among the documents whose $column starts with
     * "{prefix}-". Non-numeric suffixes (legacy random numbers) are ignored. Pass the query
     * with soft-deleted rows included so a deleted document's number is never reused.
     *
     * @param  EloquentBuilder<*>  $query
     */
    public function highestSequence(EloquentBuilder $query, string $column, string $prefix): int
    {
        $max = 0;
        $prefixLength = strlen($prefix) + 1;

        foreach ($query->where($column, 'like', $prefix.'-%')->pluck($column) as $number) {
            $suffix = substr((string) $number, $prefixLength);
            if ($suffix !== '' && ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        return $max;
    }

    private function allocate(string $prefix, int $pad, ?callable $existsCheck, ?callable $seed, string $scope): string
    {
        // Seed only on first use, and only read the existing documents once.
        $this->ensureScopeRow($scope, $seed);

        $row = DB::table(self::TABLE)->where('scope', $scope)->lockForUpdate()->first();
        $value = (int) ($row->last_value ?? 0);

        do {
            $value++;
            $candidate = $prefix.'-'.str_pad((string) $value, $pad, '0', STR_PAD_LEFT);
            $taken = $existsCheck !== null && $existsCheck($candidate);
        } while ($taken);

        DB::table(self::TABLE)->where('scope', $scope)->update(['last_value' => $value, 'updated_at' => Carbon::now()]);

        return $candidate;
    }

    /**
     * Make sure the counter row of a scope exists (seeded on first use).
     *
     * Hot path: a plain existence check on the caller's connection. Scope rows are never
     * deleted, so "present" is reliable even from an old snapshot; only "missing" can be stale.
     *
     * Slow path, only when the row looks missing, on MySQL/MariaDB: the check is repeated and
     * the row inserted on a short-lived OWN autocommit connection. Inside the caller's
     * transaction, N issuers hitting a new scope together all queue for a shared lock behind
     * the first (uncommitted) insert, are all granted it when it commits, and then all try to
     * upgrade it to exclusive for the lock/update in allocate(): InnoDB kills one with error
     * 1213, which rolls back the whole document. Committing the row at once means the losers
     * only ever wait for one statement and hold no lock afterwards. The repeated check must be
     * on that connection too: the caller's snapshot may predate the winner's commit, and a stale
     * "missing" would make the insert wait on a row lock held by another document transaction,
     * which InnoDB cannot see across connections (lock wait timeout).
     *
     * The seed row therefore commits independently of the document transaction. That is
     * harmless: last_value equals the seed, i.e. the highest number already issued, so a
     * rolled-back document leaves the counter exactly as it would have been found.
     * sqlite has a single writer: plain statements.
     *
     * BUG-04 (MariaDB 11.6.2+, innodb_snapshot_isolation=ON, REPEATABLE READ): when the slow
     * path runs INSIDE a transaction whose read view already exists, the row committed here is
     * newer than that view and the lockForUpdate() in allocate() fails with 1020. The money
     * flows avoid it twice over: they call prepareFor()/prepareDated() before their transaction
     * (the row is then older than the view), and they run under TransactionRetry (READ
     * COMMITTED, no snapshot check; and a 1020 that still happens is retried, the row then
     * exists). Any other caller gets a 409 "try again" from the exception handler at worst.
     *
     * @param  (callable(): int)|null  $seed
     */
    private function ensureScopeRow(string $scope, ?callable $seed): void
    {
        $connection = DB::connection();

        if (DB::table(self::TABLE)->where('scope', $scope)->exists()) {
            return;
        }

        // No transaction open (prepareFor(), or next() before its own transaction): a plain
        // autocommit insert on the caller's connection commits at once. sqlite: single writer.
        if ($connection->transactionLevel() === 0 || ! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->insertScopeRow($connection, $scope, $seed);

            return;
        }

        // Unique per call so nested or interleaved allocations never share (or purge) each other's session.
        $name = 'document_sequences_seed_'.uniqid('', true);
        $seedConnection = DB::connectUsing($name, $connection->getConfig(), force: true);

        try {
            if (! $seedConnection->table(self::TABLE)->where('scope', $scope)->exists()) {
                $this->insertScopeRow($seedConnection, $scope, $seed);
            }
        } finally {
            DB::purge($name);
        }
    }

    /**
     * @param  (callable(): int)|null  $seed
     */
    private function insertScopeRow(ConnectionInterface $connection, string $scope, ?callable $seed): void
    {
        $now = Carbon::now();

        $connection->table(self::TABLE)->insertOrIgnore([
            ['scope' => $scope, 'last_value' => $seed !== null ? $seed() : 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
