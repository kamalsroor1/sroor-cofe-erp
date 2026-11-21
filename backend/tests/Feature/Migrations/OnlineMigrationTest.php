<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use App\Support\Database\OnlineMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * MIG-01: driver-agnostic behaviour of OnlineMigration (the sqlite suite). The MySQL-only parts
 * (session lock_wait_timeout, retry on 1205/1213, online foreign keys) are covered by
 * OnlineMigrationMysqlTest in the `mysql` CI job.
 */
final class OnlineMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('mig01_rows', function (Blueprint $table): void {
            $table->id();
            $table->string('tag')->nullable();
        });
    }

    public function test_for_each_id_range_walks_the_key_space_in_fixed_chunks_including_gaps(): void
    {
        foreach ([1, 2, 3, 4, 5, 10] as $id) {
            DB::table('mig01_rows')->insert(['id' => $id]);
        }

        $ranges = [];
        OnlineMigration::forEachIdRange('mig01_rows', function (int $from, int $to) use (&$ranges): void {
            $ranges[] = [$from, $to];
        }, 3);

        $this->assertSame([[1, 3], [4, 6], [7, 9], [10, 12]], $ranges);
    }

    public function test_for_each_id_range_does_nothing_on_an_empty_table(): void
    {
        $called = false;
        OnlineMigration::forEachIdRange('mig01_rows', function () use (&$called): void {
            $called = true;
        });

        $this->assertFalse($called);
    }

    public function test_each_range_runs_in_its_own_transaction_and_earlier_chunks_stay_applied(): void
    {
        foreach (range(1, 6) as $id) {
            DB::table('mig01_rows')->insert(['id' => $id]);
        }
        $outer = DB::transactionLevel();
        $levels = [];

        try {
            OnlineMigration::forEachIdRange('mig01_rows', function (int $from, int $to) use (&$levels): void {
                $levels[] = DB::transactionLevel();
                DB::table('mig01_rows')->whereBetween('id', [$from, $to])->update(['tag' => 'done']);
                if ($from === 4) {
                    throw new RuntimeException('second chunk fails');
                }
            }, 3);
            $this->fail('The failure of the second chunk must propagate.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame([$outer + 1, $outer + 1], $levels);
        $this->assertSame([1, 2, 3], DB::table('mig01_rows')->where('tag', 'done')->orderBy('id')->pluck('id')->all());
        $this->assertSame(3, DB::table('mig01_rows')->whereNull('tag')->count(), 'The failed chunk was rolled back.');
    }

    public function test_default_chunk_size_stays_small(): void
    {
        $this->assertGreaterThanOrEqual(1000, OnlineMigration::CHUNK);
        $this->assertLessThanOrEqual(5000, OnlineMigration::CHUNK);
    }

    public function test_with_short_lock_timeout_is_a_plain_call_off_mysql(): void
    {
        $this->assertFalse(OnlineMigration::isMysqlFamily());

        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        $this->assertSame('ok', OnlineMigration::withShortLockTimeout(fn (): string => 'ok'));
        $this->assertSame([], array_filter($statements, fn (string $sql): bool => str_contains($sql, 'lock_wait_timeout')));
    }

    public function test_add_nullable_foreign_key_is_idempotent_off_mysql(): void
    {
        Schema::create('mig01_parents', fn (Blueprint $table) => $table->id());
        DB::table('mig01_rows')->insert(['id' => 1]);

        OnlineMigration::addNullableForeignKey('mig01_rows', 'parent_id', 'mig01_parents');
        OnlineMigration::addNullableForeignKey('mig01_rows', 'parent_id', 'mig01_parents');

        $this->assertTrue(Schema::hasColumn('mig01_rows', 'parent_id'));
        $this->assertNull(DB::table('mig01_rows')->value('parent_id'));
        $keys = array_filter(Schema::getForeignKeys('mig01_rows'), fn (array $fk): bool => $fk['columns'] === ['parent_id']);
        $this->assertCount(1, $keys);
    }

    public function test_add_nullable_foreign_key_skips_a_missing_table(): void
    {
        OnlineMigration::addNullableForeignKey('mig01_missing', 'parent_id', 'mig01_rows');

        $this->assertFalse(Schema::hasTable('mig01_missing'));
    }
}
