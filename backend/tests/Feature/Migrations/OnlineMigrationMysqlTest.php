<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use App\Models\Tenant;
use App\Support\Database\OnlineMigration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * MIG-01 on a real InnoDB server (CI `mysql` job): OnlineMigration shortens the session
 * lock_wait_timeout for the migration only, restores it afterwards (even on failure), fails fast
 * and retries behind a metadata lock, and adds foreign keys without a table copy.
 *
 * Runs inside a harness tenant: the tenant connection is not wrapped in the RefreshDatabase
 * transaction, so OnlineMigration sees transaction level 0 as it does under `tenants:migrate`.
 */
#[Group('mysql')]
#[Group('mysql-only')]
final class OnlineMigrationMysqlTest extends TenantTestCase
{
    private const PROBE = 'mig01_probe';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        $this->tenant = $this->createTenant();
    }

    protected function tearDown(): void
    {
        DB::purge(self::PROBE);

        parent::tearDown();
    }

    public function test_lock_wait_timeout_is_short_inside_and_restored_after(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->assertTrue(OnlineMigration::isMysqlFamily());
            DB::statement('SET SESSION lock_wait_timeout = 77');

            $inside = OnlineMigration::withShortLockTimeout(fn (): int => $this->sessionLockWait());

            $this->assertSame(OnlineMigration::LOCK_WAIT_SECONDS, $inside);
            $this->assertSame(77, $this->sessionLockWait());
        });
    }

    public function test_lock_wait_timeout_is_restored_when_the_callback_throws(): void
    {
        $this->inTenant($this->tenant, function (): void {
            DB::statement('SET SESSION lock_wait_timeout = 88');

            try {
                OnlineMigration::withShortLockTimeout(function (): void {
                    throw new RuntimeException('migration failed');
                }, 3);
                $this->fail('The exception must propagate.');
            } catch (RuntimeException) {
                // expected
            }

            $this->assertSame(88, $this->sessionLockWait());
        });
    }

    public function test_a_lock_timeout_is_retried_until_the_callback_succeeds(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $calls = 0;

            $result = OnlineMigration::withShortLockTimeout(function () use (&$calls): string {
                if (++$calls < 3) {
                    throw $this->queryException(1205);
                }

                return 'migrated';
            });

            $this->assertSame('migrated', $result);
            $this->assertSame(3, $calls);
            Sleep::assertSleptTimes(2);
        });
    }

    public function test_a_lock_timeout_is_not_retried_inside_an_open_transaction(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $calls = 0;

            try {
                DB::transaction(function () use (&$calls): void {
                    OnlineMigration::withShortLockTimeout(function () use (&$calls): void {
                        $calls++;
                        throw $this->queryException(1205);
                    });
                });
                $this->fail('A lock timeout inside a transaction must propagate.');
            } catch (QueryException) {
                // expected: the transaction is already rolled back by MySQL, retrying alone is unsafe
            }

            $this->assertSame(1, $calls);
        });
    }

    public function test_an_alter_behind_a_metadata_lock_fails_fast_and_is_retried_a_bounded_number_of_times(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Schema::create('mig01_locked', fn (Blueprint $table) => $table->id());
            DB::table('mig01_locked')->insert(['id' => 1]);

            // A long report holds a shared metadata lock on the table (open transaction).
            config(['database.connections.'.self::PROBE => config('database.connections.tenant')]);
            $probe = DB::connection(self::PROBE);
            $probe->beginTransaction();
            $probe->table('mig01_locked')->count();

            $attempts = 0;
            $started = microtime(true);
            try {
                OnlineMigration::withShortLockTimeout(function () use (&$attempts): void {
                    $attempts++;
                    DB::statement('ALTER TABLE `mig01_locked` ADD COLUMN `probe` INT NULL');
                }, 1);
                $this->fail('The ALTER must time out behind the metadata lock.');
            } catch (QueryException $e) {
                $this->assertSame(1205, (int) ($e->errorInfo[1] ?? 0));
            } finally {
                $probe->rollBack();
            }

            $this->assertSame(OnlineMigration::ATTEMPTS, $attempts);
            // Each attempt waited ~1 s, never the server default (one year / 50 s).
            $this->assertLessThan(OnlineMigration::ATTEMPTS * 1 + 10, microtime(true) - $started);
            $this->assertFalse(Schema::hasColumn('mig01_locked', 'probe'));
        });
    }

    public function test_add_nullable_foreign_key_adds_the_column_and_key_once_and_restores_fk_checks(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Schema::create('mig01_parents', fn (Blueprint $table) => $table->id());
            Schema::create('mig01_children', fn (Blueprint $table) => $table->id());
            DB::table('mig01_children')->insert([['id' => 1], ['id' => 2]]);

            OnlineMigration::addNullableForeignKey('mig01_children', 'parent_id', 'mig01_parents', 'cascade');
            OnlineMigration::addNullableForeignKey('mig01_children', 'parent_id', 'mig01_parents', 'cascade');

            $keys = array_values(array_filter(
                Schema::getForeignKeys('mig01_children'),
                fn (array $fk): bool => $fk['columns'] === ['parent_id'],
            ));
            $this->assertCount(1, $keys);
            $this->assertSame('mig01_children_parent_id_foreign', $keys[0]['name']);
            $this->assertSame('cascade', strtolower((string) $keys[0]['on_delete']));
            $this->assertSame(2, DB::table('mig01_children')->whereNull('parent_id')->count());
            $this->assertSame(1, (int) DB::selectOne('select @@session.foreign_key_checks as v')->v);
        });
    }

    public function test_add_nullable_foreign_key_heals_a_run_that_died_after_the_column(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Schema::create('mig01_parents', fn (Blueprint $table) => $table->id());
            Schema::create('mig01_children', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('parent_id')->nullable();
            });

            OnlineMigration::addNullableForeignKey('mig01_children', 'parent_id', 'mig01_parents');

            $this->assertCount(1, array_filter(
                Schema::getForeignKeys('mig01_children'),
                fn (array $fk): bool => $fk['columns'] === ['parent_id'],
            ));
        });
    }

    public function test_for_each_id_range_runs_every_chunk_with_the_short_timeout_in_its_own_transaction(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Schema::create('mig01_rows', fn (Blueprint $table) => $table->id());
            DB::table('mig01_rows')->insert(array_map(fn (int $id): array => ['id' => $id], range(1, 5)));

            $seen = [];
            OnlineMigration::forEachIdRange('mig01_rows', function (int $from) use (&$seen): void {
                $seen[$from] = [DB::transactionLevel(), $this->sessionLockWait()];
            }, 2);

            $this->assertSame([
                1 => [1, OnlineMigration::LOCK_WAIT_SECONDS],
                3 => [1, OnlineMigration::LOCK_WAIT_SECONDS],
                5 => [1, OnlineMigration::LOCK_WAIT_SECONDS],
            ], $seen);
        });
    }

    private function sessionLockWait(): int
    {
        return (int) DB::selectOne('select @@session.lock_wait_timeout as v')->v;
    }

    private function queryException(int $code): QueryException
    {
        $pdo = new PDOException('Lock wait timeout exceeded');
        $pdo->errorInfo = ['HY000', $code, 'Lock wait timeout exceeded'];

        return new QueryException('tenant', 'alter table x', [], $pdo);
    }
}
