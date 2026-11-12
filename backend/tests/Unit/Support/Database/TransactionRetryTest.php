<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Database;

use App\Exceptions\DatabaseBusyException;
use App\Exceptions\Stock\InsufficientStockException;
use App\Support\Database\TransactionRetry;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * BUG-07: the shared lock-conflict retry helper. Runs on the default (sqlite) test connection
 * with no surrounding transaction, so run() owns the outermost transaction like in a request.
 */
final class TransactionRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        config(['database.transaction_retry.attempts' => 3]);
        $this->assertSame(0, DB::transactionLevel(), 'The helper must own the outermost transaction here.');
    }

    /** @return array<string, array{int, string}> */
    public static function retryableErrors(): array
    {
        return [
            'deadlock 1213' => [1213, '40001'],
            'MariaDB record changed 1020' => [1020, 'HY000'],
        ];
    }

    public function test_a_lock_wait_timeout_is_answered_busy_at_once_without_a_retry(): void
    {
        // 1205 means a holder kept the row for innodb_lock_wait_timeout (50 s): retrying would
        // only make the cashier wait up to 150 s for the same 409.
        $calls = 0;

        try {
            TransactionRetry::run(function () use (&$calls): void {
                $calls++;
                throw $this->queryException(1205, 'HY000');
            });
            $this->fail('DatabaseBusyException expected.');
        } catch (DatabaseBusyException $e) {
            $this->assertSame(1, $e->attempts);
            $this->assertSame(1205, $e->getPrevious()?->errorInfo[1] ?? null);
        }

        $this->assertSame(1, $calls);
        Sleep::assertNeverSlept();
    }

    public function test_a_deadlock_raised_inside_a_nested_transaction_is_retried(): void
    {
        // Laravel rethrows a concurrency error raised at transaction level > 1 as
        // Illuminate\Database\DeadlockException (a PDOException with no errorInfo of its own).
        $calls = 0;
        $nestedErrors = [];

        $result = TransactionRetry::run(function () use (&$calls, &$nestedErrors): string {
            $calls++;
            try {
                DB::transaction(function () use ($calls): void {
                    if ($calls === 1) {
                        throw $this->queryException(1213, '40001', 'Deadlock found when trying to get lock; try restarting transaction', 40001);
                    }
                });
            } catch (DeadlockException $e) {
                $nestedErrors[] = $e::class;
                throw $e;
            }

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame(2, $calls);
        $this->assertSame([DeadlockException::class], $nestedErrors, 'The nested level must really produce a DeadlockException.');
        $this->assertSame(0, DB::transactionLevel());
        Sleep::assertSleptTimes(1);
    }

    public function test_the_driver_code_is_found_anywhere_in_the_exception_chain(): void
    {
        $nested = new DeadlockException('Deadlock found', 40001, $this->queryException(1213, '40001'));
        $this->assertTrue(TransactionRetry::isRetryable($nested));
        $this->assertTrue(TransactionRetry::isLockConflict($nested));

        $nestedTimeout = new DeadlockException('Lock wait timeout exceeded', 0, $this->queryException(1205, 'HY000'));
        $this->assertFalse(TransactionRetry::isRetryable($nestedTimeout));
        $this->assertTrue(TransactionRetry::isLockConflict($nestedTimeout));

        // No driver code anywhere, but SQLSTATE 40001 (serialization failure): a deadlock.
        $this->assertTrue(TransactionRetry::isRetryable(new DeadlockException('Deadlock found', 40001)));

        $this->assertFalse(TransactionRetry::isLockConflict(new DeadlockException('database is locked')));
        $this->assertFalse(TransactionRetry::isLockConflict(new RuntimeException('x', 0, $this->queryException(1062, '23000'))));
    }

    public function test_after_commit_callbacks_of_a_failed_attempt_are_discarded(): void
    {
        // ShiftService::closeShift() dispatches its Telegram job with afterCommit(): a retried
        // close must queue it once, not once per attempt.
        $calls = 0;
        $fired = [];

        TransactionRetry::run(function () use (&$calls, &$fired): void {
            $calls++;
            $attempt = $calls;
            DB::afterCommit(function () use (&$fired, $attempt): void {
                $fired[] = $attempt;
            });
            if ($calls === 1) {
                throw $this->queryException(1213, '40001');
            }
        });

        $this->assertSame([2], $fired);
    }

    #[DataProvider('retryableErrors')]
    public function test_a_lock_conflict_restarts_the_whole_unit_of_work(int $code, string $sqlState): void
    {
        $calls = 0;
        $levels = [];

        $result = TransactionRetry::run(function () use (&$calls, &$levels, $code, $sqlState): string {
            $calls++;
            $levels[] = DB::transactionLevel();
            if ($calls < 3) {
                throw $this->queryException($code, $sqlState);
            }

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame(3, $calls);
        $this->assertSame([1, 1, 1], $levels, 'Every attempt runs in its own fresh outermost transaction.');
        $this->assertSame(0, DB::transactionLevel());
        Sleep::assertSleptTimes(2);
    }

    public function test_a_retry_rolls_back_the_writes_of_the_failed_attempt(): void
    {
        $calls = 0;

        TransactionRetry::run(function () use (&$calls): void {
            $calls++;
            DB::table('document_sequences')->insert(['scope' => 'QA-RETRY-'.$calls, 'last_value' => 1, 'created_at' => now(), 'updated_at' => now()]);
            if ($calls === 1) {
                throw $this->queryException(1213, '40001');
            }
        });

        $this->assertSame(['QA-RETRY-2'], DB::table('document_sequences')->where('scope', 'like', 'QA-RETRY-%')->pluck('scope')->all());
    }

    public function test_it_stops_after_the_configured_attempts_and_surfaces_the_busy_error(): void
    {
        $calls = 0;

        try {
            TransactionRetry::run(function () use (&$calls): void {
                $calls++;
                throw $this->queryException(1213, '40001');
            });
            $this->fail('DatabaseBusyException expected.');
        } catch (DatabaseBusyException $e) {
            $this->assertSame(3, $e->attempts);
            $this->assertInstanceOf(QueryException::class, $e->getPrevious());
            $this->assertSame(1213, $e->getPrevious()->errorInfo[1]);
        }

        $this->assertSame(3, $calls);
        $this->assertSame(0, DB::transactionLevel());
        Sleep::assertSleptTimes(2);
    }

    public function test_the_attempt_count_can_be_passed_explicitly(): void
    {
        $calls = 0;

        $this->expectException(DatabaseBusyException::class);

        try {
            TransactionRetry::run(function () use (&$calls): void {
                $calls++;
                throw $this->queryException(1205, 'HY000');
            }, 1);
        } finally {
            $this->assertSame(1, $calls);
            Sleep::assertNeverSlept();
        }
    }

    public function test_a_validation_error_is_not_retried(): void
    {
        $this->assertNotRetried(ValidationException::withMessages(['amount' => 'invalid']));
    }

    public function test_an_insufficient_stock_domain_error_is_not_retried(): void
    {
        $this->assertNotRetried(InsufficientStockException::forItem('بن', '0.000'));
    }

    public function test_a_duplicate_key_query_error_is_not_retried(): void
    {
        $this->assertNotRetried($this->queryException(1062, '23000'));
    }

    public function test_a_generic_error_is_not_retried(): void
    {
        $this->assertNotRetried(new RuntimeException('boom'));
    }

    public function test_the_message_text_alone_never_makes_an_error_retryable(): void
    {
        // Laravel's own detector matches on message text; this helper only trusts the driver code.
        $this->assertNotRetried($this->queryException(1064, '42000', 'Deadlock found when trying to get lock'));
    }

    public function test_a_nested_call_does_not_retry_and_leaves_the_decision_to_the_outer_owner(): void
    {
        $inner = 0;

        DB::beginTransaction();
        try {
            TransactionRetry::run(function () use (&$inner): void {
                $inner++;
                throw $this->queryException(1213, '40001');
            });
            $this->fail('The raw lock error must propagate from a nested call.');
        } catch (QueryException $e) {
            $this->assertSame(1213, $e->errorInfo[1]);
        } finally {
            DB::rollBack();
        }

        $this->assertSame(1, $inner);
        Sleep::assertNeverSlept();
    }

    public function test_is_retryable_matches_only_the_lock_conflict_driver_codes(): void
    {
        $this->assertTrue(TransactionRetry::isRetryable($this->queryException(1213, '40001')));
        $this->assertFalse(TransactionRetry::isRetryable($this->queryException(1205, 'HY000')), '1205 is a conflict but never retried.');
        $this->assertTrue(TransactionRetry::isLockConflict($this->queryException(1205, 'HY000')));
        $this->assertTrue(TransactionRetry::isRetryable($this->queryException(1020, 'HY000')));
        $this->assertFalse(TransactionRetry::isRetryable($this->queryException(1062, '23000')));
        $this->assertFalse(TransactionRetry::isRetryable(new RuntimeException('Deadlock found when trying to get lock')));
    }

    private function assertNotRetried(\Throwable $error): void
    {
        $calls = 0;

        try {
            TransactionRetry::run(function () use (&$calls, $error): void {
                $calls++;
                throw $error;
            });
            $this->fail('The error must be rethrown.');
        } catch (\Throwable $e) {
            $this->assertSame($error, $e, 'Rethrown untouched, not wrapped.');
        }

        $this->assertSame(1, $calls);
        $this->assertSame(0, DB::transactionLevel());
        Sleep::assertNeverSlept();
    }

    private function queryException(int $driverCode, string $sqlState, ?string $message = null, int $pdoCode = 0): QueryException
    {
        $pdo = new PDOException($message ?? "SQLSTATE[{$sqlState}]: {$driverCode}", $pdoCode);
        $pdo->errorInfo = [$sqlState, $driverCode, $message ?? 'lock conflict'];

        return new QueryException('mysql', 'select 1', [], $pdo);
    }
}
