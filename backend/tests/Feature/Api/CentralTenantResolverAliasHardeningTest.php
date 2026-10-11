<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\ResolveApiTenancy;
use App\Http\Middleware\ThrottleTenantMisses;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * Security audit (W2 lane 3I), LOW: the un-versioned resolver alias
 * (GET /api/central/tenants/resolve) runs the same stack as the v1 resolver:
 * ThrottleTenantMisses + ResolveApiTenancy (admin host => 404, never initialises tenancy)
 * + throttle:tenant-resolve.
 */
final class CentralTenantResolverAliasHardeningTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    /** @return array<string, array{0: string, 1: string}> */
    public static function resolvers(): array
    {
        return [
            'v1' => ['api.central.tenants.resolve', '/api/v1/central/tenants/resolve'],
            'alias' => ['api.central.tenants.resolve.alias', '/api/central/tenants/resolve'],
        ];
    }

    #[DataProvider('resolvers')]
    public function test_both_resolvers_share_the_same_middleware(string $name, string $path): void
    {
        $route = RouteFacade::getRoutes()->getByName($name);
        $this->assertInstanceOf(Route::class, $route);

        $middleware = $route->gatherMiddleware();
        $this->assertContains(ThrottleTenantMisses::class, $middleware, $name);
        $this->assertContains(ResolveApiTenancy::class, $middleware, $name);
        $this->assertContains('throttle:tenant-resolve', $middleware, $name);
        $this->assertContains($name, ResolveApiTenancy::PUBLIC_CENTRAL_ROUTES);
    }

    #[DataProvider('resolvers')]
    public function test_the_platform_console_host_refuses_the_resolver(string $name, string $path): void
    {
        $tenant = $this->createTenant();
        $this->useCentralAdminHost();

        $this->getJson($this->centralAdminUrl($path.'?code='.$tenant->getTenantKey()))
            ->assertNotFound()
            ->assertJsonPath('success', false);
        $this->assertFalse(tenancy()->initialized);
    }

    #[DataProvider('resolvers')]
    public function test_a_tenant_header_never_initialises_tenancy_on_the_resolver(string $name, string $path): void
    {
        $asked = $this->createTenant();
        $other = $this->createTenant();

        $this->getJson($path.'?code='.$asked->getTenantKey(), ['X-Tenant' => (string) $other->getTenantKey()])
            ->assertOk()
            ->assertJsonPath('data.tenant_id', (string) $asked->getTenantKey());

        $this->assertFalse(tenancy()->initialized);
    }
}
