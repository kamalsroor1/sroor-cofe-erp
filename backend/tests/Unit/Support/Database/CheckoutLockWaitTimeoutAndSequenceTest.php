<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Database;

use App\Services\DocumentNumberService;
use App\Support\Database\TransactionRetry;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;
use Mockery;
use PDO;
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

    public function test_document_number_service_uses_atomic_last_insert_id_on_mysql(): void
    {
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('lastInsertId')->once()->andReturn('42');

        $connection = Mockery::mock(Connection::class);
        $grammar = new MySqlGrammar($connection);

        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('where')->once()->with('scope', 'INV-MAIN-20261127')->andReturnSelf();
        $builder->shouldReceive('update')->once()->andReturnUsing(function (array $values) use ($grammar): int {
            $this->assertArrayHasKey('last_value', $values);
            $this->assertInstanceOf(Expression::class, $values['last_value']);
            $this->assertSame('LAST_INSERT_ID(last_value + 1)', $values['last_value']->getValue($grammar));

            return 1;
        });

        $connection->shouldReceive('getDriverName')->andReturn('mysql');
        $connection->shouldReceive('raw')->andReturnUsing(static fn (string $value): Expression => new Expression($value));
        $connection->shouldReceive('table')->with('document_sequences')->andReturn($builder);
        $connection->shouldReceive('getPdo')->once()->andReturn($pdo);

        $db = DB::partialMock();
        $db->shouldReceive('transactionLevel')->once()->andReturn(1);
        $db->shouldReceive('connection')
            ->withNoArgs()
            ->andReturn($connection);

        $service = app(DocumentNumberService::class);
        $number = $service->next('INV-MAIN-20261127', 4);

        $this->assertSame('INV-MAIN-20261127-0042', $number);
    }
}
