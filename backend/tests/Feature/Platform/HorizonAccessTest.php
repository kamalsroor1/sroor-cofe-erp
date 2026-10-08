<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Http\Middleware\EnsureCentralContext;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Horizon\Horizon;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TestCase;

/**
 * W1 hardening note 4: the Horizon dashboard is a platform tool (it lists every tenant's
 * queued jobs). Only a central (admin) host, and only the platform super admin through the
 * viewHorizon gate. No tenant host, no store admin, no `local` bypass.
 */
final class HorizonAccessTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCentralPlatformRoles;

    private const CENTRAL_URL = 'http://localhost';

    private const TENANT_HOST_URL = 'http://acme.tenant-host.test';

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);
        $this->seedCentralPlatformRoles();

        $this->store = Store::create([
            'name' => 'الفرع الرئيسي',
            'code' => 'MAIN',
            'is_main' => true,
            'is_active' => true,
        ]);
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
        $this->actingAs($this->makeUser('01000000711', 'admin'), 'web')
            ->get(self::CENTRAL_URL.'/horizon')
            ->assertForbidden();
    }

    public function test_super_admin_can_open_the_dashboard_on_the_central_host(): void
    {
        $this->actingAs($this->makeUser('01000000712', 'super_admin'), 'web')
            ->get(self::CENTRAL_URL.'/horizon')
            ->assertOk();
    }

    public function test_tenant_host_never_reaches_horizon_even_for_a_super_admin(): void
    {
        $superAdmin = $this->makeUser('01000000713', 'super_admin');

        $this->actingAs($superAdmin, 'web')->get(self::TENANT_HOST_URL.'/horizon')->assertNotFound();
        $this->actingAs($superAdmin, 'web')->get(self::TENANT_HOST_URL.'/horizon/api/stats')->assertNotFound();
        $this->get(self::TENANT_HOST_URL.'/horizon')->assertNotFound();
    }

    public function test_auth_callback_requires_both_the_central_host_and_the_super_admin(): void
    {
        $superAdmin = $this->makeUser('01000000714', 'super_admin');
        $storeAdmin = $this->makeUser('01000000715', 'admin');

        $this->assertTrue(Horizon::check($this->requestAs(self::CENTRAL_URL, $superAdmin)));
        $this->assertFalse(Horizon::check($this->requestAs(self::CENTRAL_URL, $storeAdmin)));
        $this->assertFalse(Horizon::check($this->requestAs(self::CENTRAL_URL, null)));
        $this->assertFalse(Horizon::check($this->requestAs(self::TENANT_HOST_URL, $superAdmin)));
    }

    public function test_view_horizon_gate_is_platform_only(): void
    {
        $this->assertTrue(Gate::forUser($this->makeUser('01000000716', 'super_admin'))->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser($this->makeUser('01000000717', 'admin'))->allows('viewHorizon'));
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

    private function requestAs(string $baseUrl, ?User $user): Request
    {
        $request = Request::create($baseUrl.'/horizon');
        $request->setUserResolver(static fn (): ?User => $user);

        return $request;
    }

    private function makeUser(string $phone, string $role): User
    {
        $user = User::factory()->create([
            'phone' => $phone,
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
