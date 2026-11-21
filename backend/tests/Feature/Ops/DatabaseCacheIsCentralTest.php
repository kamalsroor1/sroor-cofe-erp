<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * OPS-26: the database cache store (and its locks) is pinned to the CENTRAL connection. Unpinned,
 * the store bound to whatever the default connection was when it was first resolved: in a queue
 * worker that is a tenant database, which then kept serving every other tenant the same process
 * handled, and cache writes inside a tenant transaction were rolled back with it.
 */
final class DatabaseCacheIsCentralTest extends TenantTestCase
{
    private string $central;

    protected function setUp(): void
    {
        parent::setUp();

        $this->central = (string) config('tenancy.database.central_connection');
        // A queue worker builds the store lazily, on the first cache call of the process.
        Cache::forgetDriver('database');
    }

    protected function tearDown(): void
    {
        $this->endTenancy();
        Cache::forgetDriver('database');

        parent::tearDown();
    }

    public function test_database_cache_and_lock_connections_default_to_central(): void
    {
        $this->assertSame($this->central, config('cache.stores.database.connection'));
        $this->assertSame($this->central, config('cache.stores.database.lock_connection'));
        $this->assertNotSame('tenant', config('cache.stores.database.connection'));
    }

    public function test_queue_worker_style_reads_and_writes_go_to_the_central_database(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        // Job 1 runs for tenant A: the store is resolved for the first time while A is initialised.
        tenancy()->initialize($tenantA);
        Cache::store('database')->put('ops26:shared-key', 'written-in-a', 600);
        $this->assertSame(0, DB::connection('tenant')->table('cache')->count(), 'Nothing may land in the tenant cache table.');
        tenancy()->end();

        // Job 2 runs for tenant B in the same process and reads through the same store instance.
        tenancy()->initialize($tenantB);
        $this->assertSame('written-in-a', Cache::store('database')->get('ops26:shared-key'));
        $this->assertSame(0, DB::connection('tenant')->table('cache')->count());
        tenancy()->end();

        $this->assertSame(1, DB::connection($this->central)->table('cache')->where('key', 'like', '%ops26:shared-key')->count());
        $this->inTenant($tenantA, function (): void {
            $this->assertSame(0, DB::connection('tenant')->table('cache')->count());
        });
    }

    public function test_cache_write_survives_a_rolled_back_tenant_transaction(): void
    {
        $tenant = $this->createTenant();

        tenancy()->initialize($tenant);
        Cache::store('database')->put('ops26:attempts', 0, 600);
        try {
            DB::connection('tenant')->transaction(function (): void {
                Cache::store('database')->increment('ops26:attempts');
                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(1, (int) Cache::store('database')->get('ops26:attempts'));
        tenancy()->end();
    }

    public function test_cache_locks_live_in_the_central_database(): void
    {
        $tenant = $this->createTenant();

        tenancy()->initialize($tenant);
        $lock = Cache::store('database')->lock('ops26:lock', 30);
        $this->assertTrue($lock->get());
        $this->assertSame(0, DB::connection('tenant')->table('cache_locks')->count());
        tenancy()->end();

        $this->assertSame(1, DB::connection($this->central)->table('cache_locks')->where('key', 'like', '%ops26:lock')->count());
        $lock->release();
    }
}
