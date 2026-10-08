<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\RevertedToCentralContext;
use Stancl\Tenancy\Events\TenancyBootstrapped;
use Tests\TestCase;

/**
 * P0-X2 regression: spatie's permission cache key must be scoped per tenant
 * (set on TenancyBootstrapped) and restored on RevertedToCentralContext,
 * otherwise tenant A's role/permission map is served to tenant B.
 */
class TenantPermissionCacheTest extends TestCase
{
    use RefreshDatabase;

    private const CENTRAL_KEY = 'spatie.permission.cache';

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        tenancy()->tenant = null;
        tenancy()->initialized = false;

        parent::tearDown();
    }

    private function bootTenant(string $id): void
    {
        tenancy()->tenant = new Tenant(['id' => $id]);
        tenancy()->initialized = true;
        event(new TenancyBootstrapped(tenancy()));
    }

    private function revertToCentral(): void
    {
        tenancy()->tenant = null;
        tenancy()->initialized = false;
        event(new RevertedToCentralContext(tenancy()));
    }

    private function registrar(): PermissionRegistrar
    {
        return app(PermissionRegistrar::class);
    }

    public function test_bootstrapping_tenant_scopes_the_permission_cache_key(): void
    {
        $this->assertSame(self::CENTRAL_KEY, $this->registrar()->cacheKey);

        $this->bootTenant('tenant-a');

        $this->assertSame('spatie.permission.cache.tenant.tenant-a', $this->registrar()->cacheKey);
    }

    public function test_switching_tenant_changes_the_permission_cache_key(): void
    {
        $this->bootTenant('tenant-a');
        $this->assertSame('spatie.permission.cache.tenant.tenant-a', $this->registrar()->cacheKey);

        $this->bootTenant('tenant-b');
        $this->assertSame('spatie.permission.cache.tenant.tenant-b', $this->registrar()->cacheKey);
    }

    public function test_reverting_to_central_restores_the_default_key(): void
    {
        $this->bootTenant('tenant-a');
        $this->revertToCentral();

        $this->assertSame(self::CENTRAL_KEY, $this->registrar()->cacheKey);
    }

    public function test_permissions_loaded_under_one_tenant_are_not_cached_under_another(): void
    {
        $keyA = 'spatie.permission.cache.tenant.tenant-a';
        $keyB = 'spatie.permission.cache.tenant.tenant-b';

        $this->registrar()->forgetCachedPermissions();
        Cache::forget($keyA);
        Cache::forget($keyB);

        $this->bootTenant('tenant-a');
        $permsA = $this->registrar()->getPermissions();
        $this->assertNotEmpty($permsA);
        $this->assertTrue(Cache::has($keyA), 'Permissions were not cached under tenant A\'s key.');

        $this->bootTenant('tenant-b');
        $this->assertTrue(Cache::has($keyA));
        $this->assertFalse(Cache::has($keyB), 'Tenant B\'s permission cache was populated before B loaded it.');

        // B loading must hit its own key, not reuse A's in-memory collection silently.
        $this->registrar()->getPermissions();
        $this->assertTrue(Cache::has($keyB), 'Permissions were not cached under tenant B\'s key.');
    }

    public function test_forget_cached_permissions_in_tenant_does_not_flush_other_tenant(): void
    {
        $keyA = 'spatie.permission.cache.tenant.tenant-a';
        $keyB = 'spatie.permission.cache.tenant.tenant-b';

        $this->bootTenant('tenant-a');
        $this->registrar()->getPermissions();

        $this->bootTenant('tenant-b');
        $this->registrar()->getPermissions();
        $this->registrar()->forgetCachedPermissions();

        $this->assertFalse(Cache::has($keyB));
        $this->assertTrue(Cache::has($keyA));
    }
}
