<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Database;

use App\Support\Database\TransactionRetry;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

final class CheckoutLockWaitTimeoutAndSequenceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_checkout_lock_wait_timeout_is_guarded_on_sqlite_and_configurable(): void
    {
        config(['database.transaction_retry.checkout_lock_wait_timeout' => 5]);
        $this->assertSame(5, TransactionRetry::checkoutLockWaitTimeout());

        config(['database.transaction_retry.checkout_lock_wait_timeout' => 0]);
        $this->assertNull(TransactionRetry::checkoutLockWaitTimeout());

        // On SQLite, specifying a positive lockWaitTimeoutSeconds is a safe no-op.
        $result = TransactionRetry::run(
            fn (): string => 'ok',
            lockWaitTimeoutSeconds: 5,
        );
        $this->assertSame('ok', $result);
    }

    public function test_mysql_session_lock_wait_timeout_is_set_and_restored_around_transaction(): void
    {
        config(['database.transaction_retry.isolation' => '']);

        $executedStatements = [];

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('transactionLevel')->andReturn(0);
        $connection->shouldReceive('getDriverName')->andReturn('mysql');
        $connection->shouldReceive('scalar')
            ->once()
            ->with('SELECT @@SESSION.innodb_lock_wait_timeout')
            ->andReturn(50);
        $connection->shouldReceive('statement')
            ->twice()
            ->andReturnUsing(function (string $sql) use (&$executedStatements): bool {
                $executedStatements[] = $sql;

                return true;
            });
        $connection->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(static fn (callable $callback): mixed => $callback());

        DB::partialMock()
            ->shouldReceive('connection')
            ->with('mysql_mock')
            ->andReturn($connection);

        $result = TransactionRetry::run(
            fn (): string => 'committed',
            connectionName: 'mysql_mock',
            lockWaitTimeoutSeconds: 5,
        );

        $this->assertSame('committed', $result);
        $this->assertSame([
            'SET SESSION innodb_lock_wait_timeout = 5',
            'SET SESSION innodb_lock_wait_timeout = 50',
        ], $executedStatements);
    }
}
