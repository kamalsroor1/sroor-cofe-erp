<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Http\Middleware\EnsureCentralContext;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Horizon\Horizon;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * W1 hardening note 4: the Horizon dashboard is a platform tool (it lists every tenant's
 * queued jobs). Only a central (admin) host, and only the platform super admin through the
 * viewHorizon gate. No tenant host, no store admin, no `local` bypass.
 *
 * IDEN-1.8: the platform super admin is an App\Models\CentralUser signed in on its own session
 * guard `central_web` (IDEN-1.1 / IDEN-1.7). A `users`-table row on the `web` guard, even one
 * holding the legacy `super_admin` role, no longer opens Horizon (IDEN-1.4).
 */
final class HorizonAccessTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const CENTRAL_URL = 'http://localhost';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
    }

    public function test_every_horizon_route_runs_the_central_context_guard(): void
    {
        $horizonRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn (RoutingRoute $route): bool => str_starts_with((string) $route->getName(), 'horizon.'));

        $this->assertNotEmpty($horizonRoutes->all());

        foreach ($horizonRoutes as $route) {
            $middleware = app('router')->gatherRouteMiddleware($route);
            $this->assertContains(EnsureCentralContext::class, $middleware, "route [{$route->getName()}] must be central-only");
        }
    }

    public function test_guest_is_forbidden_on_the_central_host(): void
    {
        $this->get(self::CENTRAL_URL.'/horizon')->assertForbidden();
        $this->get(self::CENTRAL_URL.'/horizon/api/stats')->assertForbidden();
    }

    public function test_store_admin_is_forbidden_on_the_central_host(): void
    {
        $this->actingAs($this->usersTableRow('admin'), 'web')
            ->get(self::CENTRAL_URL.'/horizon')
            ->assertForbidden();
    }

    public function test_legacy_users_table_super_admin_is_forbidden_on_the_central_host(): void
    {
        // Was the allowed identity before IDEN-1.4.
        $this->actingAs($this->legacyUsersTableSuperAdmin(), 'web')
            ->get(self::CENTRAL_URL.'/horizon')
            ->assertForbidden();
    }

    public function test_super_admin_can_open_the_dashboard_on_the_central_host(): void
    {
        $this->actingAs($this->centralSuperAdmin(), 'central_web')
            ->get(self::CENTRAL_URL.'/horizon')
            ->assertOk();
    }

    public function test_support_operator_is_forbidden(): void
    {
        $this->actingAs($this->centralSupport(), 'central_web')
            ->get(self::CENTRAL_URL.'/horizon')
            ->assertForbidden();
    }

    public function test_tenant_host_never_reaches_horizon_even_for_a_super_admin(): void
    {
        $tenant = $this->createTenant();
        $superAdmin = $this->centralSuperAdmin();

        $this->actingAs($superAdmin, 'central_web')->get($this->tenantUrl($tenant, '/horizon'))->assertNotFound();
        $this->actingAs($superAdmin, 'central_web')->get($this->tenantUrl($tenant, '/horizon/api/stats'))->assertNotFound();
        $this->get($this->tenantUrl($tenant, '/horizon'))->assertNotFound();
    }

    public function test_auth_callback_requires_both_the_central_host_and_the_super_admin(): void
    {
        $tenant = $this->createTenant();
        $superAdmin = $this->centralSuperAdmin();

        $this->assertTrue(Horizon::check($this->requestAs(self::CENTRAL_URL, $superAdmin)));
        $this->assertFalse(Horizon::check($this->requestAs(self::CENTRAL_URL, $this->centralSupport())));
        $this->assertFalse(Horizon::check($this->requestAs(self::CENTRAL_URL, $this->usersTableRow('admin'))));
        $this->assertFalse(Horizon::check($this->requestAs(self::CENTRAL_URL, null)));
        $this->assertFalse(Horizon::check($this->requestAs('http://'.$this->tenantDomain($tenant), $superAdmin)));
    }

    public function test_view_horizon_gate_is_platform_only(): void
    {
        $this->assertTrue(Gate::forUser($this->centralSuperAdmin())->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser($this->centralSupport())->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser($this->usersTableRow('admin'))->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser($this->legacyUsersTableSuperAdmin())->allows('viewHorizon'));
    }

    public function test_local_environment_does_not_open_horizon(): void
    {
        $this->app['env'] = 'local';

        try {
            $this->assertFalse(Horizon::check($this->requestAs(self::CENTRAL_URL, null)));
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    private function requestAs(string $baseUrl, ?Authenticatable $user): Request
    {
        $request = Request::create($baseUrl.'/horizon');
        $request->setUserResolver(static fn (): ?Authenticatable => $user);

        return $request;
    }

    /** A central `users` row (web guard) holding a web-guard role. */
    private function usersTableRow(string $role): User
    {
        $this->endTenancy();

        $user = new User;
        $user->forceFill([
            'name' => 'مستخدم '.$role,
            'email' => $role.'-'.Str::lower(Str::random(8)).'@central.harness.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ])->save();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }
}
