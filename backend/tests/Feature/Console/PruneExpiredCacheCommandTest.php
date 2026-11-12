<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * PERF-01: cache:prune-expired deletes the expired rows of the database cache store (keys
 * that are never read again, e.g. yesterday's dashboard keys) and keeps the live ones.
 */
final class PruneExpiredCacheCommandTest extends TenantTestCase
{
    public function test_expired_database_cache_rows_are_deleted_and_live_ones_kept(): void
    {
        config(['cache.default' => 'database']);
        Cache::forgetDriver('database');
        $connection = Cache::store('database')->getStore()->getConnection();
        $prefix = (string) config('cache.prefix');

        $connection->table('cache')->insert([
            ['key' => $prefix.'t:a:dashboard:old', 'value' => 's:1:"x";', 'expiration' => time() - 3600],
            ['key' => $prefix.'t:b:dashboard:old', 'value' => 's:1:"x";', 'expiration' => time() - 1],
        ]);
        Cache::store('database')->put('t:a:dashboard:live', 'figures', 600);

        $this->artisan('cache:prune-expired', ['--chunk' => 1])->assertSuccessful();

        $this->assertSame(0, $connection->table('cache')->where('key', 'like', '%dashboard:old')->count());
        $this->assertSame('figures', Cache::store('database')->get('t:a:dashboard:live'));
    }

    public function test_it_is_a_no_op_for_a_non_database_store(): void
    {
        config(['cache.default' => 'array']);

        $this->artisan('cache:prune-expired')->assertSuccessful();
        $this->assertSame(0, DB::table('cache')->count());
    }
}
