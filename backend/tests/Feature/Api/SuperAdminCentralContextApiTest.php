<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\TenantStatus;
use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\AuthenticateCentral;
use App\Http\Middleware\EnsureCentralContext;
use App\Http\Middleware\ResolveApiTenancy;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * P0-AUTH-5 / IDEN-1.4 / IDEN-1.11: the /api/v1/super-admin/* control plane is reachable ONLY in
 * central context, on a control-plane host. A request arriving on a tenant host, naming a tenant
 * (X-Tenant / ?tenant=) or, with admin hosts configured, on any other host gets 404 before
 * authentication, regardless of who is holding the token.
 *
 * IDEN-1.8: operators are App\Models\CentralUser. Tenant users (cashier / store admin) on the
 * central host used to get 403; they now get 401 because their Sanctum token is not a central
 * identity (AuthenticateCentral). That is the intended contract, not a weaker one.
 */
class SuperAdminCentralContextApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
    }

    /** @return array<string, array{string}> */
    public static function superAdminGetEndpoints(): array
    {
        return [
            'dashboard' => ['/api/v1/super-admin/dashboard'],
            'tenants' => ['/api/v1/super-admin/tenants'],
            'plans' => ['/api/v1/super-admin/plans'],
            'settings' => ['/api/v1/super-admin/settings'],
        ];
    }

    public function test_guest_on_central_host_gets_401(): void
    {
        $this->getJson('/api/v1/super-admin/dashboard')->assertStatus(401);
    }

    public function test_guest_on_tenant_host_gets_404(): void
    {
        $tenant = $this->createTenant();

        $this->getJson($this->tenantUrl($tenant, '/api/v1/super-admin/dashboard'))->assertStatus(404);
    }

    #[DataProvider('superAdminGetEndpoints')]
    public function test_super_admin_token_on_tenant_host_gets_404(string $uri): void
    {
        $tenant = $this->createTenant();
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        // Sanity: the same token and path answer on the central host.
        $this->getJson($uri, $headers)->assertStatus(200);

        $this->getJson($this->tenantUrl($tenant, $uri), $headers)->assertStatus(404);
    }

    public function test_super_admin_token_on_tenant_host_cannot_mutate_tenants(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->postJson($this->tenantUrl($tenant, "/api/v1/super-admin/tenants/{$id}/toggle-status"), [
            'status' => TenantStatus::Suspended->value,
            'reason' => 'other',
        ], $headers)->assertStatus(404);

        $this->postJson($this->tenantUrl($tenant, "/api/v1/super-admin/tenants/{$id}/override-feature"), [
            'feature_key' => 'custom_branding',
        ], $headers)->assertStatus(404);

        $this->postJson($this->tenantUrl($tenant, "/api/v1/super-admin/tenants/{$id}/update-db-config"), [
            'tenancy_db_name' => 'hijack',
        ], $headers)->assertStatus(404);

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantStatus::Active->value, $fresh->status);
        $this->assertNotContains('custom_branding', (array) $fresh->enabled_features);
        $this->assertNull($fresh->tenancy_db_name);
    }

    public function test_super_admin_with_unknown_x_tenant_header_gets_404(): void
    {
        $this->getJson('/api/v1/super-admin/dashboard', $this->centralHeaders($this->centralSuperAdmin()) + ['X-Tenant' => 'no-such-tenant'])
            ->assertStatus(404);
    }

    public function test_super_admin_with_a_real_tenant_selected_gets_404(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->getJson('/api/v1/super-admin/dashboard', $headers + ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertStatus(404);
        $this->getJson('/api/v1/super-admin/dashboard?tenant='.$tenant->getTenantKey(), $headers)
            ->assertStatus(404);
    }

    public function test_super_admin_on_central_host_gets_200(): void
    {
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->getJson('/api/v1/super-admin/dashboard', $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->getJson('/api/v1/super-admin/tenants', $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** Formerly test_cashier_and_tenant_admin_on_central_host_get_403 (see class docblock). */
    public function test_cashier_and_tenant_admin_on_central_host_get_401(): void
    {
        $tenant = $this->createTenant();
        $users = [
            'cashier' => $this->createTenantUser($tenant, 'cashier'),
            'admin' => $this->tenantAdmin($tenant),
        ];

        foreach ($users as $role => $user) {
            $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant, $user)];

            // Sanity: the token is valid in its own tenant.
            $this->getJson('/api/v1/auth/me', $this->tenantHeaders($tenant, $user))->assertStatus(200);

            $this->assertSame(401, $this->getJson('/api/v1/super-admin/dashboard', $headers)->getStatusCode(), $role);
            $this->assertSame(401, $this->getJson('/api/v1/super-admin/tenants', $headers)->getStatusCode(), $role);
        }
    }

    public function test_admin_host_enforcement_hides_the_control_plane_on_every_other_host(): void
    {
        $this->useCentralAdminHost();
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->getJson($this->centralAdminUrl('/api/v1/super-admin/dashboard'), $headers)->assertStatus(200);
        $this->getJson($this->centralAdminUrl('/api/v1/super-admin/dashboard'))->assertStatus(401);

        // The central (non-admin) host now answers 404, before authentication. Absolute URL on
        // purpose: a relative one would reuse the host of the previous request.
        $this->getJson('http://localhost/api/v1/super-admin/dashboard', $headers)->assertStatus(404);
        $this->getJson('http://localhost/api/v1/super-admin/dashboard')->assertStatus(404);
    }

    public function test_route_list_has_no_resolve_api_tenancy(): void
    {
        $names = [
            'api.super_admin.dashboard',
            'api.super_admin.tenants',
            'api.super_admin.tenants.store',
            'api.super_admin.tenants.toggle_status',
            'api.super_admin.tenants.update_db_config',
            'api.super_admin.tenants.run_migrations',
            'api.super_admin.plans.update',
            'api.super_admin.settings.update',
            'api.super_admin.app_versions.store',
        ];

        foreach ($names as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "route {$name} must exist");

            $middleware = $route->gatherMiddleware();
            $this->assertContains(EnsureCentralContext::class, $middleware, "{$name} must run EnsureCentralContext");
            $this->assertContains(AuthenticateCentral::class, $middleware, "{$name} must run AuthenticateCentral");
            $this->assertNotContains(ResolveApiTenancy::class, $middleware, "{$name} must not run ResolveApiTenancy");
            $this->assertNotContains(ApiTokenAuth::class, $middleware, "{$name} must not run ApiTokenAuth");
            $this->assertLessThan(
                array_search(AuthenticateCentral::class, $middleware, true),
                array_search(EnsureCentralContext::class, $middleware, true),
                "{$name}: the context check must run before authentication",
            );
        }
    }
}
