<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * P0-X2 regression: spatie's permission cache key must be scoped per tenant
 * (set on TenancyBootstrapped) and restored on RevertedToCentralContext,
 * otherwise tenant A's role/permission map is served to tenant B.
 *
 * QA-4: runs on real harness tenants (own database each), so tenancy()->initialize()
 * fires the real bootstrap / revert events instead of hand-dispatched ones.
 */
class TenantPermissionCacheTest extends TenantTestCase
{
    private const CENTRAL_KEY = 'spatie.permission.cache';

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = $this->createTenant();
        $this->tenantB = $this->createTenant();
    }

    private function bootTenant(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);
    }

    private function revertToCentral(): void
    {
        tenancy()->end();
    }

    private function keyFor(Tenant $tenant): string
    {
        return self::CENTRAL_KEY.'.tenant.'.$tenant->getTenantKey();
    }

    private function registrar(): PermissionRegistrar
    {
        return app(PermissionRegistrar::class);
    }

    public function test_bootstrapping_tenant_scopes_the_permission_cache_key(): void
    {
        $this->assertSame(self::CENTRAL_KEY, $this->registrar()->cacheKey);

        $this->bootTenant($this->tenantA);

        $this->assertSame($this->keyFor($this->tenantA), $this->registrar()->cacheKey);
    }

    public function test_switching_tenant_changes_the_permission_cache_key(): void
    {
        $this->bootTenant($this->tenantA);
        $this->assertSame($this->keyFor($this->tenantA), $this->registrar()->cacheKey);

        $this->bootTenant($this->tenantB);
        $this->assertSame($this->keyFor($this->tenantB), $this->registrar()->cacheKey);
    }

    public function test_reverting_to_central_restores_the_default_key(): void
    {
        $this->bootTenant($this->tenantA);
        $this->revertToCentral();

        $this->assertSame(self::CENTRAL_KEY, $this->registrar()->cacheKey);
    }

    public function test_permissions_loaded_under_one_tenant_are_not_cached_under_another(): void
    {
        $keyA = $this->keyFor($this->tenantA);
        $keyB = $this->keyFor($this->tenantB);

        $this->registrar()->forgetCachedPermissions();
        Cache::forget($keyA);
        Cache::forget($keyB);

        $this->bootTenant($this->tenantA);
        $permsA = $this->registrar()->getPermissions();
        $this->assertNotEmpty($permsA);
        $this->assertTrue(Cache::has($keyA), 'Permissions were not cached under tenant A\'s key.');

        $this->bootTenant($this->tenantB);
        $this->assertTrue(Cache::has($keyA));
        $this->assertFalse(Cache::has($keyB), 'Tenant B\'s permission cache was populated before B loaded it.');

        // B loading must hit its own key, not reuse A's in-memory collection silently.
        $this->registrar()->getPermissions();
        $this->assertTrue(Cache::has($keyB), 'Permissions were not cached under tenant B\'s key.');
    }

    public function test_forget_cached_permissions_in_tenant_does_not_flush_other_tenant(): void
    {
        $keyA = $this->keyFor($this->tenantA);
        $keyB = $this->keyFor($this->tenantB);

        $this->bootTenant($this->tenantA);
        $this->registrar()->getPermissions();

        $this->bootTenant($this->tenantB);
        $this->registrar()->getPermissions();
        $this->registrar()->forgetCachedPermissions();

        $this->assertFalse(Cache::has($keyB));
        $this->assertTrue(Cache::has($keyA));
    }

    public function test_a_permission_that_exists_only_in_one_tenant_is_never_served_to_the_other(): void
    {
        $this->inTenant($this->tenantA, fn () => Permission::findOrCreate('qa.only_in_tenant_a', 'web'));

        // A loads (and caches) its matrix first, the worst case for a shared cache entry.
        $this->bootTenant($this->tenantA);
        $this->assertTrue($this->registrar()->getPermissions()->contains('name', 'qa.only_in_tenant_a'));

        $this->bootTenant($this->tenantB);
        $this->assertFalse(
            $this->registrar()->getPermissions()->contains('name', 'qa.only_in_tenant_a'),
            'Tenant B was served tenant A\'s cached permission matrix.'
        );
        $this->assertFalse(Permission::query()->where('name', 'qa.only_in_tenant_a')->exists());
    }
}
