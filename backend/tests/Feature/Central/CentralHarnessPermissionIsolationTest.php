<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * W2-B3 lane 3G: the harness gives every test a working central super admin whatever
 * ran before it (order independence of the control-plane suites).
 */
final class CentralHarnessPermissionIsolationTest extends TenantTestCase
{
    public function test_central_super_admin_has_its_permissions_even_when_a_legacy_web_role_exists(): void
    {
        // The legacy Phase 0 role on the `web` guard, as real central DBs (and several tests) hold.
        Role::findOrCreate('super_admin', 'web');

        $operator = $this->centralSuperAdmin();

        $this->assertTrue(PlatformSuperAdmin::check($operator));
        foreach (CentralPermission::cases() as $permission) {
            $this->assertTrue(PlatformSuperAdmin::can($operator, $permission), $permission->value);
        }
    }

    public function test_each_test_starts_with_the_central_permission_cache_key(): void
    {
        $this->assertSame(config('permission.cache.key'), app(PermissionRegistrar::class)->cacheKey);
    }

    public function test_a_tenant_round_trip_restores_the_central_scope(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, static fn (): bool => true);

        $operator = $this->centralSuperAdmin();

        $this->assertSame(config('permission.cache.key'), app(PermissionRegistrar::class)->cacheKey);
        $this->assertTrue(PlatformSuperAdmin::can($operator, CentralPermission::TenantsManage));
    }
}
