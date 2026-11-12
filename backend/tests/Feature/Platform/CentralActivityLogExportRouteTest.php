<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TenantTestCase;

/**
 * SEC-21 (small item): routes/web.php declared a second GET /activity-logs/export-csv
 * (`activity-logs.export.csv`, no tenancy middleware) next to the tenant one in routes/tenant.php.
 * Same method + URI: at runtime the tenant definition took over that entry, so the web copy was
 * dead code whose only effect was to keep the tenant route AHEAD of the SPA catch-all. Not
 * exploitable today (the central domain got the tenant route and its PreventAccessFromCentralDomains
 * 404), but one load-order change away from an export without tenancy. The copy is removed and the
 * catch-all excludes the path, like the other tenant print pages.
 */
final class CentralActivityLogExportRouteTest extends TenantTestCase
{
    public function test_the_central_duplicate_route_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('activity-logs.export.csv'));
        $this->assertTrue(Route::has('tenant.activity-logs.export.csv'));
        $this->assertTrue(Route::has('api.activity_logs.export_csv'));

        $route = Route::getRoutes()->getByName('tenant.activity-logs.export.csv');
        $this->assertNotNull($route);
        $this->assertContains(PreventAccessFromCentralDomains::class, $route->gatherMiddleware());
    }

    public function test_the_export_is_not_served_on_the_central_domain(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->tenantAdmin($tenant);

        $response = $this->actingAs($admin, 'web')->get('http://localhost/activity-logs/export-csv');

        $this->assertNotSame(200, $response->status());
        $this->assertStringNotContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        // The tenant host keeps its export.
        $this->actingAs($admin, 'web')->get($this->tenantUrl($tenant, '/activity-logs/export-csv'))->assertOk();
    }
}
