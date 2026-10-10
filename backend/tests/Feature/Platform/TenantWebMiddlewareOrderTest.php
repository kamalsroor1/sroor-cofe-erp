<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Http\Middleware\StoreScope;
use Illuminate\Routing\Route;
use Illuminate\Session\Middleware\StartSession;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\TenantTestCase;

/**
 * W2-B3 lane 3L: StoreScope is appended to the `web` group, and routes/tenant.php lists
 * InitializeTenancyByDomain AFTER `web`. Read statically, StoreScope would run before the
 * tenant is known (a no-op on every tenant web route). The effective order is fixed by the
 * middleware priority list: TenancyServiceProvider::makeTenancyMiddlewareHighestPriority()
 * puts the tenancy initializers at its very top. This test pins the resolved order so a
 * change to that provider or to bootstrap/app.php cannot silently regress it.
 */
final class TenantWebMiddlewareOrderTest extends TenantTestCase
{
    public function test_tenancy_is_initialized_before_store_scope_on_every_tenant_web_route(): void
    {
        $router = $this->app['router'];
        $checked = 0;

        foreach ($router->getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            $declared = $route->gatherMiddleware();

            if (! in_array('web', $declared, true) || ! in_array(InitializeTenancyByDomain::class, $declared, true)) {
                continue;
            }

            $resolved = array_values(array_filter(
                $router->gatherRouteMiddleware($route),
                static fn (mixed $middleware): bool => is_string($middleware),
            ));

            $tenancy = array_search(InitializeTenancyByDomain::class, $resolved, true);
            $storeScope = array_search(StoreScope::class, $resolved, true);
            $session = array_search(StartSession::class, $resolved, true);

            $this->assertIsInt($tenancy, $route->uri());
            $this->assertIsInt($storeScope, $route->uri());
            $this->assertIsInt($session, $route->uri());
            $this->assertLessThan($storeScope, $tenancy, $route->uri().': tenancy must be initialized before StoreScope.');
            $this->assertLessThan($storeScope, $session, $route->uri().': StoreScope needs the session.');
            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'routes/tenant.php must register tenant web routes.');
    }
}
