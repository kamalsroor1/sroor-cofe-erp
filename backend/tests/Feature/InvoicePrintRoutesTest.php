<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TenantTestCase;

/**
 * P0-X4 / P0-X5 regression: Blade print routes (invoice thermal/A4, daily
 * journal, item movements, reports) must never be reachable by guests and
 * must enforce permission + store access.
 *
 * QA-4: every request goes to the harness tenant's own host, so domain tenancy
 * identifies the tenant exactly as in production. The controller-level checks
 * still re-mount the *real* route action with its *full* middleware stack
 * (including InitializeTenancyByDomain) on a probe URI placed ahead of the SPA
 * catch-all, so the production middleware + handler run end to end.
 */
class InvoicePrintRoutesTest extends TenantTestCase
{
    protected Tenant $tenant;

    private Store $storeA;

    private Store $storeB;

    private User $admin;

    private Customer $customer;

    private Invoice $invoiceA;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixtures and DB assertions run inside the tenant; every request selects it with X-Tenant.
        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);
        // Blade routes identify the tenant by host: send every request to the tenant's domain.
        URL::forceRootUrl('http://'.$this->tenantDomain($this->tenant));

        $this->storeA = $this->adoptMainStore([
            'name' => 'المخزن الرئيسي',
            'code' => 'MAIN-A',
            'type' => 'warehouse',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->storeB = Store::create([
            'name' => 'فرع التجمع',
            'code' => 'BRANCH-B',
            'type' => 'retail',
            'is_main' => false,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'كمال سرور',
            'phone' => self::ADMIN_PHONE,
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->storeA->id,
        ]);
        $this->admin->assignRole(Role::findByName('admin'));

        $this->customer = Customer::create([
            'name' => 'عميل سري جدا',
            'phone' => '01000007047',
            'current_balance' => '0.000',
            'is_active' => true,
        ]);

        $item = Item::create([
            'name' => 'بن برازيلي',
            'code' => 'BN-PRINT-01',
            'category' => 'coffee_beans',
            'cost_price' => '300.000',
            'selling_price' => '500.000',
            'current_stock' => '50.000',
            'min_stock_level' => '1.000',
            'is_active' => true,
        ]);

        $this->invoiceA = Invoice::create([
            'invoice_number' => 'INV-PRINT-QA-7781',
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'invoice_date' => now()->toDateString(),
            'subtotal' => '1000.000',
            'discount_amount' => '0.000',
            'net_total' => '1000.000',
            'paid_amount' => '1000.000',
            'remaining_amount' => '0.000',
            'total_cost' => '600.000',
            'status' => 'confirmed',
            'payment_method' => 'cash',
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoiceA->id,
            'item_id' => $item->id,
            'quantity' => '2.000',
            'unit_price' => '500.000',
            'cost_price' => '300.000',
            'discount_amount' => '0.000',
            'total_price' => '1000.000',
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function namedRoute(string $name): RoutingRoute
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route, "Route [{$name}] is not registered.");

        return $route;
    }

    /**
     * @return list<RoutingRoute>
     */
    private function routesNamed(string $name): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RoutingRoute $r) => $r->getName() === $name
        ));
    }

    /**
     * @return list<RoutingRoute>
     */
    private function routesForUri(string $uri): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RoutingRoute $r) => $r->uri() === $uri && in_array('GET', $r->methods(), true)
        ));
    }

    /**
     * Re-mount a real named route's action + its full middleware (domain tenancy
     * included) on a probe URI ahead of the SPA catch-all.
     */
    private function mountProbe(string $name, string $probeUri): void
    {
        $route = $this->namedRoute($name);

        $middleware = $route->gatherMiddleware();
        $this->assertContains(InitializeTenancyByDomain::class, $middleware, "{$name} must identify the tenant by domain.");
        $this->assertContains(PreventAccessFromCentralDomains::class, $middleware, "{$name} must be unreachable from central domains.");

        $action = $route->getAction();
        unset($action['as'], $action['prefix'], $action['domain'], $action['where'], $action['middleware'], $action['excluded_middleware']);
        $action['middleware'] = $middleware;

        $probe = (new RoutingRoute(['GET', 'HEAD'], ltrim($probeUri, '/'), $action))
            ->name('qa.probe.'.str_replace('.', '_', $name))
            ->setRouter(app('router'))
            ->setContainer(app());

        // The SPA catch-all `/{any?}` in routes/web.php would match the probe
        // first, so rebuild the collection with the probe at the front.
        $existing = Route::getRoutes();
        $rebuilt = new RouteCollection;
        $rebuilt->add($probe);
        foreach ($existing->getRoutes() as $r) {
            $rebuilt->add($r);
        }
        Route::setRoutes($rebuilt);
    }

    private function userWithPermissions(array $permissions, ?Store $assignedStore): User
    {
        $user = User::factory()->create([
            'name' => 'كاشير',
            'phone' => '0101'.random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $assignedStore?->id,
        ]);

        foreach ($permissions as $perm) {
            $user->givePermissionTo(Permission::findByName($perm));
        }

        if ($assignedStore) {
            $user->stores()->attach($assignedStore->id);
        }

        return $user;
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invoicePrintRoutes(): array
    {
        return [
            'thermal' => ['invoices.print.thermal', '/__qa/invoices/{id}/print/thermal'],
            'a4' => ['invoices.print.a4', '/__qa/invoices/{id}/print/a4'],
        ];
    }

    // ------------------------------------------------------------------
    // P0-X4 route-level assertions
    // ------------------------------------------------------------------

    public function test_unauthenticated_default_invoice_print_route_is_removed(): void
    {
        $this->assertFalse(
            Route::has('invoices.print.default'),
            'invoices.print.default (unauthenticated /invoices/{id}/print) must be removed.'
        );
    }

    #[DataProvider('invoicePrintRoutes')]
    public function test_invoice_print_route_requires_auth_and_invoices_view(string $name, string $probe): void
    {
        $middleware = $this->namedRoute($name)->gatherMiddleware();

        $this->assertContains('auth', $middleware, "{$name} is missing auth middleware.");
        $this->assertContains('can:invoices.view', $middleware, "{$name} is missing can:invoices.view.");
    }

    public function test_daily_journal_print_route_requires_auth_permission_and_store_access(): void
    {
        $middleware = $this->namedRoute('daily.journal.print')->gatherMiddleware();

        $this->assertContains('auth', $middleware);
        $this->assertContains('can:daily_journal.view', $middleware);
        $this->assertContains('store.access', $middleware);
    }

    // ------------------------------------------------------------------
    // P0-X4 controller-level assertions (real action + middleware)
    // ------------------------------------------------------------------

    #[DataProvider('invoicePrintRoutes')]
    public function test_guest_cannot_print_invoice(string $name, string $probe): void
    {
        $this->mountProbe($name, $probe);

        $response = $this->get(str_replace('{id}', (string) $this->invoiceA->id, $probe));

        $this->assertContains($response->getStatusCode(), [302, 401, 403], 'Guest was able to print an invoice.');
        $content = (string) $response->getContent();
        $this->assertStringNotContainsString($this->customer->name, $content);
        $this->assertStringNotContainsString($this->customer->phone, $content);
        $this->assertStringNotContainsString($this->invoiceA->invoice_number, $content);
    }

    #[DataProvider('invoicePrintRoutes')]
    public function test_user_without_invoices_view_gets_403(string $name, string $probe): void
    {
        $this->mountProbe($name, $probe);
        $user = $this->userWithPermissions(['pos.access'], $this->storeA);

        $response = $this->actingAs($user)->get(str_replace('{id}', (string) $this->invoiceA->id, $probe));

        $response->assertStatus(403);
        $this->assertStringNotContainsString($this->customer->name, (string) $response->getContent());
    }

    #[DataProvider('invoicePrintRoutes')]
    public function test_user_assigned_to_other_store_cannot_print_invoice(string $name, string $probe): void
    {
        $this->mountProbe($name, $probe);
        $user = $this->userWithPermissions(['invoices.view'], $this->storeB);

        $response = $this->actingAs($user)->get(str_replace('{id}', (string) $this->invoiceA->id, $probe));

        $response->assertStatus(403);
        $this->assertStringNotContainsString($this->customer->name, (string) $response->getContent());
    }

    #[DataProvider('invoicePrintRoutes')]
    public function test_user_assigned_to_invoice_store_can_print(string $name, string $probe): void
    {
        $this->mountProbe($name, $probe);
        $user = $this->userWithPermissions(['invoices.view'], $this->storeA);

        $response = $this->actingAs($user)->get(str_replace('{id}', (string) $this->invoiceA->id, $probe));

        $response->assertStatus(200);
        $response->assertSee($this->invoiceA->invoice_number);
    }

    #[DataProvider('invoicePrintRoutes')]
    public function test_admin_can_print_invoice(string $name, string $probe): void
    {
        $this->mountProbe($name, $probe);

        $response = $this->actingAs($this->admin)->get(str_replace('{id}', (string) $this->invoiceA->id, $probe));

        $response->assertStatus(200);
        $response->assertSee($this->invoiceA->invoice_number);
    }

    #[DataProvider('invoicePrintRoutes')]
    public function test_missing_invoice_returns_404_for_admin(string $name, string $probe): void
    {
        $this->mountProbe($name, $probe);

        $missingId = (int) Invoice::max('id') + 1000;
        $response = $this->actingAs($this->admin)->get(str_replace('{id}', (string) $missingId, $probe));

        $response->assertStatus(404);
    }

    public function test_daily_journal_print_rejects_user_without_permission(): void
    {
        $this->mountProbe('daily.journal.print', '/__qa/daily-journal/print');
        $user = $this->userWithPermissions(['pos.access'], $this->storeA);

        $this->actingAs($user)->get('/__qa/daily-journal/print')->assertStatus(403);
    }

    public function test_daily_journal_print_rejects_foreign_store_filter(): void
    {
        $this->mountProbe('daily.journal.print', '/__qa/daily-journal/print');
        $user = $this->userWithPermissions(['daily_journal.view'], $this->storeB);

        $this->actingAs($user)
            ->get('/__qa/daily-journal/print?store_id='.$this->storeA->id)
            ->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // P0-X5: routes/web.php print routes (central host + fallthrough)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function webPrintUris(): array
    {
        return [
            'item movements' => ['items/{id}/movements/print'],
            'reports' => ['reports/print'],
        ];
    }

    #[DataProvider('webPrintUris')]
    public function test_web_print_routes_require_auth_and_a_permission(string $uri): void
    {
        $routes = $this->routesForUri($uri);
        $this->assertNotEmpty($routes, "No GET route registered for {$uri} (the SPA catch-all would serve it).");

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware, "{$uri} is missing auth middleware.");
            $this->assertNotEmpty(
                array_filter($middleware, fn ($m) => is_string($m) && str_starts_with($m, 'can:')),
                "{$uri} is missing a can: permission middleware."
            );
        }
    }

    public function test_reports_print_route_requires_reports_view(): void
    {
        foreach ($this->routesForUri('reports/print') as $route) {
            $this->assertContains('can:reports.view', $route->gatherMiddleware());
        }
    }

    public function test_guest_cannot_open_reports_print(): void
    {
        $response = $this->get('/reports/print?tab=sales');

        $this->assertNotSame(200, $response->getStatusCode(), 'Guest received the reports print page.');
        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
    }

    public function test_guest_cannot_open_item_movements_print(): void
    {
        $itemId = (int) Item::query()->value('id');

        $response = $this->get("/items/{$itemId}/movements/print");

        $this->assertNotSame(200, $response->getStatusCode(), 'Guest received the item movements print page.');
        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
        $this->assertStringNotContainsString('بن برازيلي', (string) $response->getContent());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function uniquePrintRouteNames(): array
    {
        return [
            'thermal' => ['invoices.print.thermal'],
            'a4' => ['invoices.print.a4'],
            'daily journal' => ['daily.journal.print'],
        ];
    }

    #[DataProvider('uniquePrintRouteNames')]
    public function test_print_route_name_is_registered_once_and_authenticated(string $name): void
    {
        $routes = $this->routesNamed($name);

        $this->assertCount(1, $routes, "Expected exactly one route named {$name}.");
        $this->assertContains('auth', $routes[0]->gatherMiddleware());
    }

    #[DataProvider('webPrintUris')]
    public function test_print_uri_is_not_shadowed_by_an_unauthenticated_duplicate(string $uri): void
    {
        foreach ($this->routesForUri($uri) as $route) {
            $this->assertContains('auth', $route->gatherMiddleware());
        }
    }

    public function test_every_print_route_is_authenticated(): void
    {
        $unguarded = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_contains($route->uri(), 'print') || str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            if (! in_array('auth', $route->gatherMiddleware(), true)) {
                $unguarded[] = $route->uri().' ['.($route->getName() ?? '-').']';
            }
        }

        $this->assertSame([], $unguarded, 'Unauthenticated print routes: '.implode(', ', $unguarded));
    }

    // ------------------------------------------------------------------
    // Store filter bypass (store_id omitted / "1abc" / array / "all")
    // ------------------------------------------------------------------

    private function invoiceInStoreB(): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV-FOREIGN-B-9931',
            'store_id' => $this->storeB->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'invoice_date' => now()->toDateString(),
            'subtotal' => '500.000',
            'discount_amount' => '0.000',
            'net_total' => '500.000',
            'paid_amount' => '500.000',
            'remaining_amount' => '0.000',
            'total_cost' => '300.000',
            'status' => 'confirmed',
            'payment_method' => 'cash',
        ]);
    }

    public function test_daily_journal_print_without_store_id_is_scoped_to_users_store(): void
    {
        $foreign = $this->invoiceInStoreB();
        $this->mountProbe('daily.journal.print', '/__qa/daily-journal/print');
        $user = $this->userWithPermissions(['daily_journal.view'], $this->storeA);

        $response = $this->actingAs($user)->get('/__qa/daily-journal/print?date='.now()->toDateString());

        $response->assertStatus(200);
        $response->assertSee($this->invoiceA->invoice_number);
        $response->assertDontSee($foreign->invoice_number);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedStoreIds(): array
    {
        return [
            'numeric prefix' => ['{A}abc'],
            'array' => ['[]={A}'],
            'zero' => ['0'],
            'negative' => ['-{A}'],
        ];
    }

    private function storeQuery(string $pattern): string
    {
        $value = str_replace('{A}', (string) $this->storeA->id, $pattern);

        return str_starts_with($value, '[]') ? 'store_id'.$value : 'store_id='.urlencode($value);
    }

    #[DataProvider('malformedStoreIds')]
    public function test_daily_journal_print_rejects_malformed_store_id(string $pattern): void
    {
        $foreign = $this->invoiceInStoreB();
        $this->mountProbe('daily.journal.print', '/__qa/daily-journal/print');
        $user = $this->userWithPermissions(['daily_journal.view'], $this->storeA);

        $response = $this->actingAs($user)
            ->get('/__qa/daily-journal/print?date='.now()->toDateString().'&'.$this->storeQuery($pattern), ['Accept' => 'application/json']);

        $this->assertContains($response->getStatusCode(), [403, 422]);
        $this->assertStringNotContainsString($foreign->invoice_number, (string) $response->getContent());
    }

    public function test_daily_journal_print_rejects_all_stores_for_non_admin(): void
    {
        $this->invoiceInStoreB();
        $this->mountProbe('daily.journal.print', '/__qa/daily-journal/print');
        $user = $this->userWithPermissions(['daily_journal.view'], $this->storeA);

        $this->actingAs($user)->get('/__qa/daily-journal/print?store_id=all')->assertStatus(403);
    }

    public function test_daily_journal_print_admin_without_store_id_sees_all_stores(): void
    {
        $foreign = $this->invoiceInStoreB();
        $this->mountProbe('daily.journal.print', '/__qa/daily-journal/print');

        $response = $this->actingAs($this->admin)->get('/__qa/daily-journal/print?date='.now()->toDateString());

        $response->assertStatus(200);
        $response->assertSee($this->invoiceA->invoice_number);
        $response->assertSee($foreign->invoice_number);
    }

    private function movementsFixture(): Item
    {
        $item = Item::query()->firstOrFail();
        foreach ([[$this->storeA, 'DOC-STORE-A-111'], [$this->storeB, 'DOC-STORE-B-222']] as [$store, $doc]) {
            StockMovement::create([
                'item_id' => $item->id,
                'movement_type' => 'purchase_in',
                'quantity' => '5.000',
                'stock_before' => '0.000',
                'stock_after' => '5.000',
                'unit_cost' => '300.000',
                'source_type' => 'purchase',
                'source_id' => 1,
                'document_number' => $doc,
                'user_id' => $this->admin->id,
                'store_id' => $store->id,
            ]);
        }

        return $item;
    }

    public function test_item_movements_print_without_store_id_is_scoped_to_users_store(): void
    {
        $item = $this->movementsFixture();
        $user = $this->userWithPermissions(['items.view'], $this->storeA);

        $response = $this->actingAs($user)->get("/items/{$item->id}/movements/print");

        $response->assertStatus(200);
        $response->assertSee('DOC-STORE-A-111');
        $response->assertDontSee('DOC-STORE-B-222');
    }

    #[DataProvider('malformedStoreIds')]
    public function test_item_movements_print_rejects_malformed_store_id(string $pattern): void
    {
        $item = $this->movementsFixture();
        $user = $this->userWithPermissions(['items.view'], $this->storeA);

        $response = $this->actingAs($user)
            ->get("/items/{$item->id}/movements/print?".$this->storeQuery($pattern), ['Accept' => 'application/json']);

        $this->assertContains($response->getStatusCode(), [403, 422]);
        $this->assertStringNotContainsString('DOC-STORE-B-222', (string) $response->getContent());
    }

    public function test_item_movements_print_rejects_all_and_foreign_store_for_non_admin(): void
    {
        $item = $this->movementsFixture();
        $user = $this->userWithPermissions(['items.view'], $this->storeA);

        $this->actingAs($user)->get("/items/{$item->id}/movements/print?store_id=all")->assertStatus(403);
        $this->actingAs($user)->get("/items/{$item->id}/movements/print?store_id=".$this->storeB->id)->assertStatus(403);
    }

    public function test_reports_print_store_filter_is_enforced_for_non_admin(): void
    {
        $user = $this->userWithPermissions(['reports.view'], $this->storeA);

        $this->actingAs($user)->get('/reports/print?tab=sales')->assertStatus(200)->assertSee($this->storeA->name);
        $this->actingAs($user)->get('/reports/print?tab=sales&store_id=all')->assertStatus(403);
        $this->actingAs($user)->get('/reports/print?tab=sales&store_id='.$this->storeB->id)->assertStatus(403);
        $this->assertContains(
            $this->actingAs($user)->get('/reports/print?tab=sales&store_id='.$this->storeA->id.'abc', ['Accept' => 'application/json'])->getStatusCode(),
            [403, 422]
        );
    }

    // ------------------------------------------------------------------
    // Report row 7: no unauthenticated web route outside an explicit allowlist
    // ------------------------------------------------------------------

    public function test_unauthenticated_csv_export_routes_are_removed(): void
    {
        foreach (['customers.export.csv', 'suppliers.export.csv', 'items.export.csv', 'items.movements.export'] as $name) {
            $this->assertFalse(Route::has($name), "{$name} must not be registered.");
        }

        $customerId = $this->customer->id;
        $response = $this->get("/customers/{$customerId}/export-csv");
        $this->assertStringNotContainsString($this->customer->phone, (string) $response->getContent());
        $this->assertStringNotContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }

    public function test_every_non_api_web_route_is_authenticated_or_allowlisted(): void
    {
        $publicAllowlist = [
            '{any?}',              // SPA shell (no data)
            'spa/{any?}',          // tenant SPA shell (no data)
            'stock-transfers',     // SPA shell (no data)
            'brochure',            // marketing page
            'manifest.json',       // PWA manifest
            'sw.js',               // service worker
            'telescope-access',    // super-admin bridge, gated by PlatformSuperAdmin
            'login',               // guest auth
            'logout',              // ends session only
            'impersonate/{token}', // stancl one-time impersonation token
            'up',                  // health check
        ];

        $unguarded = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'api/') || in_array($uri, $publicAllowlist, true)) {
                continue;
            }

            if ($this->isVendorRoute($route)) {
                continue; // vendor routes (Telescope, Pulse, Livewire, Sanctum, storage, tenancy assets) carry their own gates
            }

            $middleware = $route->gatherMiddleware();
            if (! in_array('auth', $middleware, true) && ! in_array('auth:sanctum', $middleware, true)) {
                $unguarded[] = implode('|', $route->methods()).' '.$uri.' ['.($route->getName() ?? '-').']';
            }
        }

        $this->assertSame([], $unguarded, 'Unauthenticated web routes outside the allowlist: '.implode(', ', $unguarded));
    }

    private function isVendorRoute(RoutingRoute $route): bool
    {
        $uses = $route->getAction('uses');

        if ($uses instanceof \Closure) {
            $file = str_replace('\\', '/', (string) (new \ReflectionFunction($uses))->getFileName());

            return str_contains($file, '/vendor/');
        }

        $controller = is_string($uses) ? $uses : (string) $route->getAction('controller');
        if ($controller === '') {
            return false;
        }

        $class = explode('@', $controller)[0];
        if (! class_exists($class)) {
            return false;
        }

        $file = str_replace('\\', '/', (string) (new \ReflectionClass($class))->getFileName());

        return str_contains($file, '/vendor/');
    }

    public function test_session_store_switch_requires_auth_and_store_access(): void
    {
        $this->postJson('/store/switch', ['store_id' => $this->storeB->id])->assertStatus(401);

        $user = $this->userWithPermissions(['pos.access'], $this->storeA);
        $this->actingAs($user)->postJson('/store/switch', ['store_id' => $this->storeB->id])->assertStatus(403);
        $this->actingAs($user)->postJson('/store/switch', ['store_id' => $this->storeA->id])->assertOk();
        $this->assertEquals($this->storeA->id, session('current_store_id'));
    }

    // ------------------------------------------------------------------
    // QA-4: tenant isolation of the Blade invoice print pages
    // ------------------------------------------------------------------

    #[DataProvider('invoicePrintRoutes')]
    public function test_invoice_of_another_tenant_cannot_be_printed_from_either_host(string $name, string $probe): void
    {
        $other = $this->createTenant(); // ends tenancy
        $otherStoreId = (int) $this->tenantStore($other)->id;
        $otherAdminId = (int) $this->tenantAdmin($other)->id;
        $foreignInvoiceId = $this->inTenant($other, function () use ($otherStoreId, $otherAdminId): int {
            $customer = Customer::create(['name' => 'عميل مستأجر آخر سري', 'phone' => '01000007048', 'current_balance' => '0.000', 'is_active' => true]);

            return (int) Invoice::create([
                'invoice_number' => 'INV-OTHER-TENANT-5521',
                'store_id' => $otherStoreId,
                'customer_id' => $customer->id,
                'user_id' => $otherAdminId,
                'invoice_date' => now()->toDateString(),
                'subtotal' => '250.000',
                'net_total' => '250.000',
                'paid_amount' => '250.000',
                'remaining_amount' => '0.000',
                'status' => 'confirmed',
                'payment_method' => 'cash',
            ])->id;
        });
        $this->useTenantForTest($this->tenant);
        $this->mountProbe($name, $probe);

        // On this tenant's host, the other tenant's invoice id does not exist.
        $response = $this->actingAs($this->admin)->get(str_replace('{id}', (string) $foreignInvoiceId, $probe));
        $response->assertStatus(404);
        $this->assertStringNotContainsString('INV-OTHER-TENANT-5521', (string) $response->getContent());
        $this->assertStringNotContainsString('عميل مستأجر آخر سري', (string) $response->getContent());

        // On the other tenant's host, this tenant's admin is nobody and this tenant's invoice is unknown.
        URL::forceRootUrl('http://'.$this->tenantDomain($other));
        $crossHost = $this->actingAs($this->admin)->get(str_replace('{id}', (string) $this->invoiceA->id, $probe));
        $this->assertContains($crossHost->getStatusCode(), [302, 401, 403, 404]);
        $this->assertStringNotContainsString($this->invoiceA->invoice_number, (string) $crossHost->getContent());
        $this->assertStringNotContainsString('INV-OTHER-TENANT-5521', (string) $crossHost->getContent());
    }
}
