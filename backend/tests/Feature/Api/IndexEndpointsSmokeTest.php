<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * BUG-05 smoke test: every list (GET, no path parameter) endpoint answers an authorised
 * admin without a server error.
 *
 * Its value is the MySQL run (CI job `mysql`): sqlite reads an unknown double-quoted
 * identifier as a string literal, so a `select()` / `orderBy()` / `groupBy()` on a column
 * that does not exist passes on sqlite and is a 500 `SQLSTATE[42S22]` on MySQL. It also
 * runs on sqlite in the `php` job, where it still catches plain crashes.
 *
 * Adding a list endpoint: add its URI to TENANT_ROUTES or CENTRAL_ROUTES. A route in neither
 * list nor in NOT_COVERED fails test_every_list_route_is_listed_or_explicitly_excluded().
 *
 * PERF-04: the second tenant test runs the same list on a tenant filled by `tenant:seed-perf`
 * (invoices, lines, vouchers, returns, purchases, expenses, movements over 3 branches), with
 * a 12-month `from`/`to` range on the report endpoints and `X-Store-Id: all`, so the eager
 * loads, grouped report SQL and date filters run over real rows on MySQL too.
 */
#[Group('mysql')]
final class IndexEndpointsSmokeTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    /**
     * Tenant list endpoints, called as the tenant admin on the main store.
     * URI (under /api/v1) => query string parameters.
     *
     * @var array<string, array<string, string>>
     */
    private const TENANT_ROUTES = [
        'activity-logs' => [],
        'auth/me' => [],
        'auth/options' => [],
        'billing/invoices' => [],
        'billing/plan-change-requests' => [],
        'billing/subscription' => [],
        'branding' => [],
        'categories' => [],
        'customers' => [],
        'daily-journal' => [],
        'dashboard' => [],
        'dashboard/summary' => [],
        'expenses' => [],
        'invoices' => [],
        'items' => [],
        'items/low-stock' => [],
        'payments' => [],
        'permissions' => [],
        'pos/bootstrap' => [],
        'pos/items' => [],
        'pos/quick-keys' => [],
        'profile' => [],
        'purchases' => [],
        'purchases/smart-reorder' => [],
        'reports/comprehensive' => [],
        'reports/customers' => [],
        'reports/expenses' => [],
        'reports/inventory' => [],
        'reports/items' => [],
        'reports/stock-drift' => [],
        'reports/stores' => [],
        'reports/summary' => [],
        'reports/top-items' => [],
        'reports/treasury' => [],
        'returns' => [],
        'roles' => [],
        'settings' => [],
        'shifts' => [],
        'shifts/current' => [],
        'stores' => [],
        'stores/stocks' => [],
        'suppliers' => [],
        'system/context' => [],
        'system/setup-progress' => [],
        'transfers' => [],
        'trash' => [],
        'treasury/summary' => [],
        'users' => [],
    ];

    /**
     * Control-plane list endpoints, called as a central super admin (no tenant context).
     *
     * @var array<string, array<string, string>>
     */
    private const CENTRAL_ROUTES = [
        'super-admin/app-versions' => [],
        'super-admin/billing/payments' => [],
        'super-admin/billing/plan-change-requests' => [],
        'super-admin/dashboard' => [],
        'super-admin/plans' => [],
        'super-admin/platform-settings' => [],
        'super-admin/settings' => [],
        'super-admin/signup-requests' => [],
        'super-admin/tenants' => [],
        'super-admin/units' => [],
    ];

    /**
     * Parameterless GET routes this smoke test deliberately does not call, with the reason.
     *
     * @var array<string, string>
     */
    private const NOT_COVERED = [
        'activity-logs/export-csv' => 'streamed download, covered by ActivityLogApiTest (mysql group)',
        'app/check-update' => 'public release check, needs client version parameters',
        'app/download-apk' => 'binary download',
        'app/download-latest-apk' => 'binary download',
        'app/version' => 'public release metadata, no list query',
        'auth/quick-login/users' => 'passwordless login helper, registered in local environments only',
        'central/tenants/resolve' => 'public resolver, needs a workspace parameter',
        'invoices/export' => 'file export',
        'ping' => 'health probe, no query',
        'pos/last-price' => 'lookup, needs item and customer parameters',
        'pos/lookup' => 'lookup, needs a barcode parameter',
        'public/plans' => 'public marketing endpoint',
        'super-admin/auth/me' => 'operator profile, no list query',
        'super-admin/auth/two-factor/recovery-codes' => 'needs an enrolled second factor',
        'system/translations' => 'static translation bundle, no query',
    ];

    public function test_every_tenant_list_endpoint_answers_an_admin_without_a_server_error(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->tenantAdmin($tenant);
        $storeId = (int) $this->tenantStore($tenant)->id;

        // One row so the activity-log eager loads (user, store) run too.
        $this->inTenant($tenant, fn () => ActivityLog::create([
            'user_id' => $admin->id,
            'store_id' => $storeId,
            'module' => 'sales',
            'action' => 'invoice_created',
            'description' => 'فاتورة اختبار',
            'properties' => ['total' => '10.250'],
            'created_at' => now(),
        ]));

        $headers = $this->tenantHeaders($tenant);
        $failures = [];

        foreach (self::TENANT_ROUTES as $uri => $query) {
            $failures = [...$failures, ...$this->failureOf($uri, $this->getJson($this->urlFor($uri, $query), $headers))];
        }

        $this->assertSame([], $failures, "Tenant list endpoints that did not answer 200:\n".implode("\n", $failures));
    }

    public function test_every_tenant_list_endpoint_answers_on_a_seeded_tenant_with_a_year_range(): void
    {
        $tenant = $this->createTenant();
        $this->artisan('tenant:seed-perf', [
            'tenant' => $tenant->getTenantKey(), '--invoices' => 120, '--items' => 20, '--stores' => 3,
            '--days' => 365, '--customers' => 8, '--suppliers' => 3,
        ])->assertExitCode(0);

        $to = now()->toDateString();
        $year = ['from' => now()->subDays(364)->toDateString(), 'to' => $to, 'date_from' => now()->subDays(364)->toDateString(), 'date_to' => $to];
        $failures = [];

        foreach ([$this->tenantHeaders($tenant), array_merge($this->tenantHeaders($tenant), ['X-Store-Id' => 'all'])] as $headers) {
            foreach (self::TENANT_ROUTES as $uri => $query) {
                $params = str_starts_with($uri, 'reports/') || in_array($uri, ['daily-journal', 'invoices', 'expenses', 'purchases', 'returns', 'payments'], true)
                    ? $query + $year
                    : $query;
                $response = $this->getJson($this->urlFor($uri, $params), $headers);
                // `all` is refused (403) by endpoints that need one concrete branch; never a 5xx.
                if ($headers['X-Store-Id'] === 'all' && $response->getStatusCode() < 500) {
                    continue;
                }
                $failures = [...$failures, ...$this->failureOf($uri.' [store '.$headers['X-Store-Id'].']', $response)];
            }
        }

        $this->assertSame([], $failures, "List endpoints that did not answer 200 on a seeded tenant:\n".implode("\n", $failures));
    }

    public function test_every_central_list_endpoint_answers_a_super_admin_without_a_server_error(): void
    {
        // A tenant row, so the control-plane lists are not empty.
        $this->createTenant();

        $headers = $this->steppedUpCentralHeaders($this->centralOperator());
        $failures = [];

        foreach (self::CENTRAL_ROUTES as $uri => $query) {
            $failures = [...$failures, ...$this->failureOf($uri, $this->getJson($this->urlFor($uri, $query), $headers))];
        }

        $this->assertSame([], $failures, "Central list endpoints that did not answer 200:\n".implode("\n", $failures));
    }

    public function test_every_list_route_is_listed_or_explicitly_excluded(): void
    {
        $known = array_merge(array_keys(self::TENANT_ROUTES), array_keys(self::CENTRAL_ROUTES), array_keys(self::NOT_COVERED));
        $registered = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! in_array('GET', $route->methods(), true) || ! str_starts_with($uri, 'api/v1/') || str_contains($uri, '{')) {
                continue;
            }

            $registered[] = substr($uri, strlen('api/v1/'));
        }

        $this->assertNotEmpty($registered);
        $this->assertSame(
            [],
            array_values(array_diff($registered, $known)),
            'New list endpoints must be added to IndexEndpointsSmokeTest (TENANT_ROUTES / CENTRAL_ROUTES) or to NOT_COVERED with a reason.',
        );
        $this->assertSame(
            [],
            array_values(array_diff([...array_keys(self::TENANT_ROUTES), ...array_keys(self::CENTRAL_ROUTES)], $registered)),
            'IndexEndpointsSmokeTest calls routes that no longer exist.',
        );
    }

    /**
     * @param  array<string, string>  $query
     */
    private function urlFor(string $uri, array $query): string
    {
        return '/api/v1/'.$uri.($query === [] ? '' : '?'.http_build_query($query));
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return list<string>
     */
    private function failureOf(string $uri, TestResponse $response): array
    {
        if ($response->getStatusCode() === 200) {
            return [];
        }

        $reason = $response->exception?->getMessage() ?? (string) $response->getContent();

        return [sprintf('GET /api/v1/%s -> %d: %s', $uri, $response->getStatusCode(), mb_substr($reason, 0, 300))];
    }
}
