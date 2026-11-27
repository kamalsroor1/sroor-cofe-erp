<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * ADM-436: Even when TELESCOPE_ENABLED=false (production default), GET /telescope and
 * GET /telescope/* must never fall through to the SPA catch-all (200). They must return
 * 403 on the admin host without an authorized central_web session, and 404 on tenant hosts.
 */
final class TelescopeDisabledGuardTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->useCentralAdminHost();
    }

    public function test_guest_on_admin_host_gets_403_not_spa_when_telescope_is_disabled(): void
    {
        $this->assertFalse((bool) config('telescope.enabled'));

        $this->get($this->centralAdminUrl('/telescope'))->assertForbidden();
        $this->get($this->centralAdminUrl('/telescope/requests'))->assertForbidden();
    }

    public function test_tenant_user_and_unprivileged_operator_get_403_when_telescope_is_disabled(): void
    {
        $tenant = $this->createTenant();
        $tenantAdmin = $this->tenantAdmin($tenant);
        $this->endTenancy();

        $this->actingAs($tenantAdmin, 'web')
            ->get($this->centralAdminUrl('/telescope'))
            ->assertForbidden();

        $this->actingAs($this->centralSupport(), 'central_web')
            ->get($this->centralAdminUrl('/telescope'))
            ->assertForbidden();
    }

    public function test_super_admin_gets_404_not_spa_when_telescope_is_disabled(): void
    {
        $this->actingAs($this->centralSuperAdmin(), 'central_web')
            ->get($this->centralAdminUrl('/telescope'))
            ->assertNotFound();

        $this->actingAs($this->centralSuperAdmin(), 'central_web')
            ->get($this->centralAdminUrl('/telescope/requests'))
            ->assertNotFound();
    }

    public function test_tenant_host_gets_404_when_telescope_is_disabled(): void
    {
        $tenant = $this->createTenant();
        $this->endTenancy();

        $this->get($this->tenantUrl($tenant, '/telescope'))->assertNotFound();
        $this->get($this->tenantUrl($tenant, '/telescope/requests'))->assertNotFound();

        $this->actingAs($this->centralSuperAdmin(), 'central_web')
            ->get($this->tenantUrl($tenant, '/telescope'))
            ->assertNotFound();
    }

    public function test_telescope_access_bridge_route_is_not_shadowed(): void
    {
        $this->get($this->centralAdminUrl('/telescope-access'))->assertForbidden();
    }
}
