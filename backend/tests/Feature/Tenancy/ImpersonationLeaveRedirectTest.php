<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use Tests\TenantTestCase;

/**
 * POST /impersonate/leave (tenant host, routes/tenant.php) logs the impersonated session out
 * and sends the operator back to the CURRENT platform console (/super-admin/tenants) on its
 * own host (central.admin_domains), with the scheme of config('app.url'). It used to point
 * at the removed /admin/super/tenants path on the tenant base domain.
 */
final class ImpersonationLeaveRedirectTest extends TenantTestCase
{
    public function test_leaving_impersonation_redirects_to_the_console_on_the_admin_host(): void
    {
        config(['app.url' => 'http://sroor.test', 'central.admin_domains' => ['admin.sroor.test']]);
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, 'admin');

        $this->actingAs($user, 'web')
            ->withSession(['is_impersonating' => true, 'impersonated_by_super' => true])
            ->post($this->tenantUrl($tenant, '/impersonate/leave'))
            ->assertRedirect('http://admin.sroor.test/super-admin/tenants')
            ->assertSessionMissing('is_impersonating');

        $this->assertGuest('web');
    }

    public function test_without_an_admin_host_it_falls_back_to_the_central_domain(): void
    {
        config([
            'app.url' => 'https://platform.example.test',
            'central.admin_domains' => [],
            'tenancy.central_domain' => 'platform.example.test',
        ]);
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, 'admin');

        $this->actingAs($user, 'web')
            ->post($this->tenantUrl($tenant, '/impersonate/leave'))
            ->assertRedirect('https://platform.example.test/super-admin/tenants');
    }

    public function test_a_guest_cannot_use_the_leave_route(): void
    {
        $tenant = $this->createTenant();

        config(['central.admin_domains' => ['admin.sroor.test']]);

        $this->post($this->tenantUrl($tenant, '/impersonate/leave'))
            ->assertRedirect($this->tenantUrl($tenant, '/login'));
    }
}
