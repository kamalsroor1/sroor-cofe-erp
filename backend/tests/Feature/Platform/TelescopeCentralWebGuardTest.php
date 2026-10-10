<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\CentralUser;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Laravel\Sentinel\Http\Middleware\SentinelMiddleware;
use Laravel\Telescope\Telescope;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * W2-B3 lane 3L: /telescope-access signs the operator into the `central_web` session guard,
 * but the Telescope gate used to read the default (tenant-side `web`) guard, so the signed
 * link landed on a 403. Now Telescope::auth resolves the CentralUser from `central_web` and
 * requires super_admin.monitoring.view on the `central` guard (mirrors Horizon).
 *
 * phpunit.xml sets TELESCOPE_ENABLED=false, so the package never registers its routes in
 * tests; setUp() registers them the way Laravel\Telescope\TelescopeServiceProvider::boot does.
 */
final class TelescopeCentralWebGuardTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->useCentralAdminHost();
        $this->registerTelescopeRoutes();
    }

    private function registerTelescopeRoutes(): void
    {
        config(['telescope.enabled' => true]);

        Route::middlewareGroup('telescope', [
            SentinelMiddleware::class.':telescope',
            ...config('telescope.middleware', ['web']),
        ]);

        Route::group([
            'namespace' => 'Laravel\Telescope\Http\Controllers',
            'prefix' => config('telescope.path'),
            'middleware' => 'telescope',
        ], function (): void {
            require base_path('vendor/laravel/telescope/routes/web.php');
        });

        View::addNamespace('telescope', base_path('vendor/laravel/telescope/resources/views'));

        // In the app the package registers its routes before routes/web.php; here they come
        // after it, so move the SPA catch-all (`/{any?}`) back to the end of the collection.
        $router = $this->app['router'];
        $ordered = new RouteCollection;
        $catchAll = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if ($route->uri() === '{any?}') {
                $catchAll[] = $route;

                continue;
            }

            $ordered->add($route);
        }

        foreach ($catchAll as $route) {
            $ordered->add($route);
        }

        $router->setRoutes($ordered);
    }

    public function test_super_admin_on_central_web_opens_telescope_on_the_admin_host(): void
    {
        $this->actingAs($this->centralSuperAdmin(), 'central_web')
            ->get($this->centralAdminUrl('/telescope'))
            ->assertOk();
    }

    public function test_guest_is_denied(): void
    {
        $this->get($this->centralAdminUrl('/telescope'))->assertForbidden();
    }

    public function test_operator_without_monitoring_permission_is_denied(): void
    {
        foreach ([$this->centralSupport(), $this->centralOperator(null)] as $operator) {
            $this->actingAs($operator, 'central_web')
                ->get($this->centralAdminUrl('/telescope'))
                ->assertForbidden();
        }
    }

    public function test_tenant_user_on_the_web_guard_is_denied_even_with_a_colliding_id(): void
    {
        $superAdmin = $this->centralSuperAdmin();
        $legacy = $this->legacyUsersTableSuperAdmin(['id' => $superAdmin->getKey()]);

        $this->actingAs($legacy, 'web')->get($this->centralAdminUrl('/telescope'))->assertForbidden();
    }

    public function test_tenant_admin_on_the_web_guard_is_denied(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->tenantAdmin($tenant);
        $this->endTenancy();

        $this->actingAs($admin, 'web')->get($this->centralAdminUrl('/telescope'))->assertForbidden();
    }

    public function test_tenant_host_is_404_even_for_the_super_admin(): void
    {
        $tenant = $this->createTenant();
        $this->endTenancy();

        $this->actingAs($this->centralSuperAdmin(), 'central_web')
            ->get($this->tenantUrl($tenant, '/telescope'))
            ->assertNotFound();
    }

    public function test_auth_callback_reads_only_the_central_web_guard(): void
    {
        $superAdmin = $this->centralSuperAdmin();

        $request = Request::create($this->centralAdminUrl('/telescope'));
        $request->setUserResolver(static fn (?string $guard = null): ?CentralUser => $guard === 'central_web' ? $superAdmin : null);
        $this->assertTrue(Telescope::check($request));

        $wrongGuard = Request::create($this->centralAdminUrl('/telescope'));
        $wrongGuard->setUserResolver(static fn (?string $guard = null): ?CentralUser => $guard === 'central_web' ? null : $superAdmin);
        $this->assertFalse(Telescope::check($wrongGuard));
    }
}
