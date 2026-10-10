<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralPermission;
use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\AuthenticateCentral;
use App\Http\Middleware\EnsureCentralContext;
use App\Http\Middleware\RequireRecentTwoFactor;
use App\Http\Middleware\ResolveApiTenancy;
use App\Http\Middleware\ThrottleTenantMisses;
use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use App\Models\User;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * IDEN-1.4 route-security gate for the control plane (/api/v1/super-admin/*).
 *
 *  - every super-admin route lives in routes/central.php: EnsureCentralContext first, never
 *    ResolveApiTenancy / ApiTokenAuth / ThrottleTenantMisses; routes/api.php has none left;
 *  - every authenticated route runs AuthenticateCentral and a granular `can:` on a real
 *    CentralPermission value; super_admin gets everything, support reads only;
 *  - the sensitive routes demand a recent 2FA proof (RequireRecentTwoFactor);
 *  - the legacy bypass is gone: a Phase 0 App\Models\User holding `super_admin` (central
 *    `users`) and a tenant Sanctum token are both 401 on the control plane.
 */
final class CentralRouteSecurityGateTest extends TenantTestCase
{
    private const PREFIX = 'api/v1/super-admin';

    /** Public, unauthenticated central endpoints (their own throttles). */
    private const PUBLIC_ROUTES = [
        'central.auth.login',
        'central.auth.two-factor-challenge',
        'central.auth.forgot-password',
        'central.auth.reset-password',
    ];

    /** Central auth endpoints authenticated without a `can:` (any operator acts on itself). */
    private const SELF_SERVICE_ROUTES = [
        'central.auth.me',
        'central.auth.logout',
        'central.auth.step-up',
        'central.auth.two-factor.enable',
        'central.auth.two-factor.confirm',
        'central.auth.two-factor.recovery-codes',
    ];

    /** Routes that must demand a fresh second factor. */
    private const STEP_UP_ROUTES = [
        'api.super_admin.tenants.destroy',
        'api.super_admin.tenants.update_db_config',
        'api.super_admin.tenants.run_migrations',
        'api.super_admin.tenants.rate_limits.store',
        // Security audit (W2 lane 3I): every other dangerous write of the control plane.
        'api.super_admin.tenants.toggle_status',
        'api.super_admin.tenants.override_feature',
        'api.super_admin.tenants.update_units',
        'api.super_admin.plans.update',
        'api.super_admin.settings.update',
        'api.super_admin.app_versions.store',
        'api.super_admin.app_versions.toggle_active',
        'api.super_admin.app_versions.destroy',
        // W2 batch 4: platform branding writes and provisioning retry.
        'api.super_admin.platform_settings.update',
        'api.super_admin.platform_settings.assets.store',
        'api.super_admin.platform_settings.assets.destroy',
        'api.super_admin.tenants.retry_provisioning',
        // W2 batch 4 review: the legacy platform units write.
        'api.super_admin.units.update',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Order independence: never seed inside a tenant left initialized, and never let a
        // permission map cached by an earlier test decide this test's `can:` checks.
        $this->endTenancy();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(CentralPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<Route> */
    private function controlPlaneRoutes(): array
    {
        $routes = array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            static fn (Route $route): bool => str_starts_with($route->uri(), self::PREFIX),
        ));

        $this->assertNotEmpty($routes);

        return $routes;
    }

    public function test_every_control_plane_route_runs_ensure_central_context_and_no_tenant_middleware(): void
    {
        foreach ($this->controlPlaneRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            $label = implode('|', $route->methods()).' '.$route->uri();

            $this->assertContains(EnsureCentralContext::class, $middleware, $label);
            $this->assertNotContains(ResolveApiTenancy::class, $middleware, $label);
            $this->assertNotContains(ApiTokenAuth::class, $middleware, $label);
            $this->assertNotContains(ThrottleTenantMisses::class, $middleware, $label);
            $this->assertNotContains('auth:sanctum', $middleware, $label);
        }
    }

    public function test_every_authenticated_route_uses_central_auth_and_a_granular_central_permission(): void
    {
        foreach ($this->controlPlaneRoutes() as $route) {
            $name = (string) $route->getName();
            $label = $name.' '.$route->uri();
            $middleware = $route->gatherMiddleware();

            if (in_array($name, self::PUBLIC_ROUTES, true)) {
                $this->assertEmpty(array_filter($middleware, static fn ($m): bool => is_string($m) && str_starts_with($m, AuthenticateCentral::class)), $label);

                continue;
            }

            $central = array_filter($middleware, static fn ($m): bool => is_string($m) && str_starts_with($m, AuthenticateCentral::class));
            $this->assertNotEmpty($central, $label.' must run AuthenticateCentral');

            if (in_array($name, self::SELF_SERVICE_ROUTES, true)) {
                continue;
            }

            $abilities = array_values(array_map(
                static fn (string $m): string => substr($m, 4),
                array_filter($middleware, static fn ($m): bool => is_string($m) && str_starts_with($m, 'can:')),
            ));

            $this->assertCount(1, $abilities, $label.' needs exactly one can: middleware');
            $this->assertNotNull(CentralPermission::tryFrom($abilities[0]), $label.' uses an unknown ability '.$abilities[0]);
        }
    }

    public function test_route_names_and_paths_of_the_legacy_group_are_kept(): void
    {
        $expected = [
            'api.super_admin.dashboard' => ['GET', 'dashboard'],
            'api.super_admin.tenants' => ['GET', 'tenants'],
            'api.super_admin.tenants.store' => ['POST', 'tenants'],
            'api.super_admin.tenants.show' => ['GET', 'tenants/{id}'],
            'api.super_admin.tenants.destroy' => ['DELETE', 'tenants/{id}'],
            'api.super_admin.tenants.update_db_config' => ['POST', 'tenants/{id}/update-db-config'],
            'api.super_admin.tenants.toggle_status' => ['POST', 'tenants/{id}/toggle-status'],
            'api.super_admin.tenants.override_feature' => ['POST', 'tenants/{id}/override-feature'],
            'api.super_admin.tenants.update_units' => ['POST', 'tenants/{id}/update-units'],
            'api.super_admin.tenants.run_migrations' => ['POST', 'tenants/{id}/run-migrations'],
            'api.super_admin.plans' => ['GET', 'plans'],
            'api.super_admin.plans.update' => ['PUT', 'plans/{id}'],
            'api.super_admin.telescope_link' => ['POST', 'telescope-link'],
            'api.super_admin.settings.get' => ['GET', 'settings'],
            'api.super_admin.settings.update' => ['POST', 'settings'],
            'api.super_admin.units.get' => ['GET', 'units'],
            'api.super_admin.units.update' => ['POST', 'units'],
            'api.super_admin.app_versions.index' => ['GET', 'app-versions'],
            'api.super_admin.app_versions.store' => ['POST', 'app-versions'],
            'api.super_admin.app_versions.toggle_active' => ['PATCH', 'app-versions/{appVersion}/toggle-active'],
            'api.super_admin.app_versions.destroy' => ['DELETE', 'app-versions/{appVersion}'],
        ];

        foreach ($expected as $name => [$method, $path]) {
            $route = RouteFacade::getRoutes()->getByName($name);

            $this->assertInstanceOf(Route::class, $route, $name);
            $this->assertSame(self::PREFIX.'/'.$path, $route->uri(), $name);
            $this->assertContains($method, $route->methods(), $name);
        }
    }

    public function test_sensitive_routes_require_a_recent_second_factor(): void
    {
        foreach (self::STEP_UP_ROUTES as $name) {
            $route = RouteFacade::getRoutes()->getByName($name);

            $this->assertInstanceOf(Route::class, $route, $name);
            $this->assertContains(RequireRecentTwoFactor::class, $route->gatherMiddleware(), $name);
        }
    }

    public function test_routes_api_php_holds_no_super_admin_route(): void
    {
        $source = (string) file_get_contents(base_path('routes/api.php'));

        $this->assertStringNotContainsString('super-admin', $source);
        $this->assertStringNotContainsString('SuperAdmin', $source);
        $this->assertStringNotContainsString('super_admin', $source);
    }

    public function test_super_admin_reads_and_writes(): void
    {
        // toggle-status demands a recent second factor (security audit, W2 lane 3I).
        $headers = $this->steppedUpHeaders($this->centralSuperAdmin());

        $this->getJson('/api/v1/super-admin/dashboard', $headers)->assertOk();
        $this->getJson('/api/v1/super-admin/tenants', $headers)->assertOk();
        $tenant = $this->createTenant(['status' => 'active']);
        $this->postJson('/api/v1/super-admin/tenants/'.$tenant->getTenantKey().'/toggle-status', [
            'status' => 'suspended',
            'reason' => 'other',
        ], $headers)->assertOk()->assertJsonPath('data.status', 'suspended');
    }

    public function test_support_reads_but_never_writes(): void
    {
        $headers = $this->centralHeaders($this->operatorWithRole(CentralPermission::ROLE_SUPPORT));
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/super-admin/dashboard', $headers)->assertOk();
        $this->getJson('/api/v1/super-admin/tenants', $headers)->assertOk();
        $this->getJson('/api/v1/super-admin/tenants/'.$tenant->getTenantKey(), $headers)->assertOk();
        $this->getJson('/api/v1/super-admin/units', $headers)->assertOk();

        $this->postJson('/api/v1/super-admin/units', ['units' => ['kg']], $headers)->assertForbidden();
        $this->postJson('/api/v1/super-admin/tenants/'.$tenant->getTenantKey().'/toggle-status', [
            'status' => 'suspended',
            'reason' => 'other',
        ], $headers)->assertForbidden();
        $this->postJson('/api/v1/super-admin/settings', ['platform_name' => 'X'], $headers)->assertForbidden();
        // Monitoring is not a support ability (cross-tenant raw data).
        $this->postJson('/api/v1/super-admin/telescope-link', [], $headers)->assertForbidden();
    }

    public function test_guest_is_401_on_the_admin_routes(): void
    {
        $this->getJson('/api/v1/super-admin/dashboard')->assertUnauthorized();
        $this->postJson('/api/v1/super-admin/tenants', [])->assertUnauthorized();
    }

    public function test_a_legacy_central_users_super_admin_token_is_refused(): void
    {
        $this->endTenancy();

        Role::findOrCreate('super_admin', 'web');
        $legacy = new User;
        $legacy->forceFill([
            'name' => 'legacy operator',
            'email' => 'legacy-'.Str::lower(Str::random(6)).'@central.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ])->save();
        $legacy->assignRole('super_admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $token = $legacy->createToken('legacy')->plainTextToken;

        $this->getJson('/api/v1/super-admin/dashboard', ['Authorization' => 'Bearer '.$token])
            ->assertUnauthorized();
        $this->getJson('/api/v1/super-admin/tenants', ['Authorization' => 'Bearer '.$token])
            ->assertUnauthorized();
    }

    public function test_a_tenant_admin_token_is_401_on_the_control_plane(): void
    {
        $tenant = $this->createTenant();
        $token = $this->tenantToken($tenant);

        $this->getJson('/api/v1/super-admin/dashboard', ['Authorization' => 'Bearer '.$token])
            ->assertUnauthorized();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function stepUpRequests(): array
    {
        return [
            'update db config' => ['POST', '/update-db-config'],
            'run migrations' => ['POST', '/run-migrations'],
            'destroy' => ['DELETE', ''],
            'raise rate limits' => ['POST', '/rate-limits'],
            'toggle status' => ['POST', '/toggle-status'],
            'override feature' => ['POST', '/override-feature'],
            'update tenant units' => ['POST', '/update-units'],
        ];
    }

    #[DataProvider('stepUpRequests')]
    public function test_sensitive_operations_without_a_recent_second_factor_are_403_step_up_required(string $method, string $suffix): void
    {
        $tenant = $this->createTenant();
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->json($method, '/api/v1/super-admin/tenants/'.$tenant->getTenantKey().$suffix, [], $headers)
            ->assertForbidden()
            ->assertJsonPath('error_code', 'central_auth.step_up_required');
    }

    public function test_a_recent_second_factor_passes_the_step_up_gate(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->steppedUpHeaders($this->centralSuperAdmin());

        // Deletion stays disabled (OPS-11): past the step-up gate the controller answers its own 403.
        $this->deleteJson('/api/v1/super-admin/tenants/'.$tenant->getTenantKey(), [], $headers)
            ->assertForbidden()
            ->assertJsonMissingPath('error_code')
            ->assertJsonPath('message', __('super.tenant_delete_disabled'));
    }

    public function test_unknown_tenant_is_a_translated_404_without_internals(): void
    {
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $response = $this->getJson('/api/v1/super-admin/tenants/no-such-tenant', $headers)
            ->assertNotFound()
            ->assertJsonPath('message', __('super.tenant_not_found'));

        $this->assertStringNotContainsString('App\\Models', (string) $response->getContent());
    }

    public function test_tenant_api_user_payload_no_longer_exposes_is_super_admin(): void
    {
        $tenant = $this->createTenant();

        $response = $this->getJson('/api/v1/auth/me', $this->tenantHeaders($tenant))->assertOk();

        $this->assertStringNotContainsString('is_super_admin', (string) $response->getContent());
    }

    public function test_the_public_tenant_resolver_still_answers_without_initialising_tenancy(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/central/tenants/resolve?code='.$tenant->getTenantKey())
            ->assertOk()
            ->assertJsonPath('data.tenant_id', (string) $tenant->getTenantKey());

        // X-Tenant on the resolver selects nothing (explicit exception in ResolveApiTenancy).
        $this->getJson('/api/v1/central/tenants/resolve?code='.$tenant->getTenantKey(), ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertOk();
    }

    public function test_other_api_v1_central_paths_no_longer_bypass_tenant_resolution(): void
    {
        // Before IDEN-1.4 ResolveApiTenancy skipped every api/v1/central/* path. Only the named
        // public resolver is exempt now: any other such path resolves (and refuses) X-Tenant.
        $request = Request::create('/api/v1/central/anything', 'GET', server: ['HTTP_X_TENANT' => 'no-such-tenant']);

        $response = (new ResolveApiTenancy)->handle($request, static fn (): Response => new Response('passed'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse(tenancy()->initialized);
    }

    private function operatorWithRole(string $role): CentralUser
    {
        $this->endTenancy();

        $user = CentralUser::factory()->create([
            'email' => $role.'-'.Str::lower(Str::random(6)).'@central.test',
            'is_active' => true,
        ]);
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->refresh();
    }

    /** @return array<string, string> */
    private function steppedUpHeaders(CentralUser $user): array
    {
        $headers = $this->centralHeaders($user);

        CentralPersonalAccessToken::query()
            ->where('tokenable_id', $user->getKey())
            ->update(['two_factor_verified_at' => now()]);

        return $headers;
    }
}
