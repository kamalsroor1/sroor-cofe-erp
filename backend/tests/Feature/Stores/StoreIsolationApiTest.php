<?php

declare(strict_types=1);

namespace Tests\Feature\Stores;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * STOR-2: `store.active` is mounted on every branch-scoped tenant API route (POS, invoices,
 * shifts, daily journal, transfers, treasury, reports, dashboard) and `store.active:concrete` on
 * the single-branch writes. A cashier of store A never reads or changes store B's data:
 *  - a header for B, or `all`, is 403 on every mounted endpoint; a user without any usable
 *    store is 403 even without a header (proves the middleware itself is mounted);
 *  - a record of B requested from A is 404 (CTO Q13) and nothing changes;
 *  - list sums cover A only; `all` (header or ?store_id=all) only with stores.view_all;
 *  - checkout sells from the active store only: a body store_id of B is 403 (stock unchanged),
 *    an accessible body store_id is ignored (CTO Q14);
 *  - a token of another tenant is 401.
 */
final class StoreIsolationApiTest extends TenantTestCase
{
    /**
     * Every endpoint STOR-2 mounts: [route name, method, uri, concrete].
     *
     * @return array<string, array{string, string, string, bool}>
     */
    public static function mountedEndpoints(): array
    {
        return [
            'dashboard' => ['api.dashboard.index', 'GET', '/api/v1/dashboard', false],
            'dashboard summary' => ['api.dashboard.summary', 'GET', '/api/v1/dashboard/summary', false],
            'invoices index' => ['api.invoices.index', 'GET', '/api/v1/invoices', false],
            'invoices show' => ['api.invoices.show', 'GET', '/api/v1/invoices/{record}', false],
            'invoices store' => ['api.invoices.store', 'POST', '/api/v1/invoices', true],
            'invoices cancel' => ['api.invoices.cancel', 'POST', '/api/v1/invoices/{record}/cancel', false],
            'pos bootstrap' => ['api.pos.bootstrap', 'GET', '/api/v1/pos/bootstrap', false],
            'pos checkout' => ['api.pos.checkout', 'POST', '/api/v1/pos/checkout', true],
            'pos last price' => ['api.pos.last_price', 'GET', '/api/v1/pos/last-price?customer_id=1&item_id=1', false],
            'pos quick keys' => ['api.pos.quick_keys.index', 'GET', '/api/v1/pos/quick-keys', false],
            'shifts index' => ['api.shifts.index', 'GET', '/api/v1/shifts', false],
            'shifts current' => ['api.shifts.current', 'GET', '/api/v1/shifts/current', false],
            'shifts open' => ['api.shifts.open', 'POST', '/api/v1/shifts/open', true],
            'shifts close' => ['api.shifts.close', 'POST', '/api/v1/shifts/close', true],
            'shifts z-report' => ['api.shifts.z_report', 'GET', '/api/v1/shifts/{record}/z-report', false],
            'daily journal' => ['api.daily_journal.index', 'GET', '/api/v1/daily-journal', false],
            'treasury summary' => ['api.treasury.summary', 'GET', '/api/v1/treasury/summary', false],
            'reports summary' => ['api.reports.summary', 'GET', '/api/v1/reports/summary', false],
            'reports comprehensive' => ['api.reports.comprehensive', 'GET', '/api/v1/reports/comprehensive', false],
            'reports items' => ['api.reports.items', 'GET', '/api/v1/reports/items', false],
            'reports stores' => ['api.reports.stores', 'GET', '/api/v1/reports/stores', false],
            'reports customers' => ['api.reports.customers', 'GET', '/api/v1/reports/customers', false],
            'reports expenses' => ['api.reports.expenses', 'GET', '/api/v1/reports/expenses', false],
            'reports inventory' => ['api.reports.inventory', 'GET', '/api/v1/reports/inventory', false],
            'reports treasury' => ['api.reports.treasury', 'GET', '/api/v1/reports/treasury', false],
            'reports top items' => ['api.reports.top_items', 'GET', '/api/v1/reports/top-items', false],
            'reports item card' => ['api.reports.item_card', 'GET', '/api/v1/reports/items/{record}/card', false],
            'transfers index' => ['api.transfers.index', 'GET', '/api/v1/transfers', false],
            'transfers show' => ['api.transfers.show', 'GET', '/api/v1/transfers/{record}', false],
            'transfers store' => ['api.transfers.store', 'POST', '/api/v1/transfers', true],
            'transfers cancel' => ['api.transfers.cancel', 'POST', '/api/v1/transfers/{record}/cancel', false],
        ];
    }

    /** Permissions a cashier of A gets on top of the role, so only the STORE decides the outcome. */
    private const CASHIER_EXTRA = ['reports.view', 'transfers.view', 'transfers.create', 'invoices.cancel'];

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    /**
     * Store A = the harness main store, B and C extra branches; one item with stock in A and B.
     *
     * @return array{a: int, b: int, c: int, item: int, customer: int}
     */
    private function seedStores(Tenant $tenant): array
    {
        $storeA = (int) $this->tenantStore($tenant)->id;

        return $this->inTenant($tenant, function () use ($storeA): array {
            $make = fn (string $code): int => (int) Store::query()->create([
                'name' => 'فرع '.$code,
                'code' => $code,
                'type' => 'retail',
                'is_main' => false,
                'is_active' => true,
            ])->id;

            $storeB = $make('BR-B');
            $storeC = $make('BR-C');

            $item = Item::query()->create([
                'code' => 'ISO-1',
                'name' => 'صنف العزل',
                'unit' => 'كجم',
                'cost_price' => '10.000',
                'selling_price' => '20.000',
                'current_stock' => '30.000',
                'is_active' => true,
            ]);

            StoreStock::query()->create(['store_id' => $storeA, 'item_id' => $item->id, 'quantity' => '10.000']);
            StoreStock::query()->create(['store_id' => $storeB, 'item_id' => $item->id, 'quantity' => '20.000']);

            $customer = Customer::query()->create([
                'name' => 'عميل العزل',
                'phone' => '01000007711',
                'current_balance' => '0.000',
                'is_active' => true,
            ]);

            return ['a' => $storeA, 'b' => $storeB, 'c' => $storeC, 'item' => (int) $item->id, 'customer' => (int) $customer->id];
        });
    }

    private function cashierOfA(Tenant $tenant): User
    {
        return $this->createTenantUser($tenant, 'cashier', self::CASHIER_EXTRA);
    }

    /** Sees every store (stores.view_all) but is assigned to none besides its default store A. */
    private function viewAllManager(Tenant $tenant): User
    {
        return $this->createTenantUser($tenant, null, [
            'stores.view_all', 'invoices.view', 'reports.view', 'transfers.view', 'daily_journal.view',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutPayload(array $seed, string $quantity, ?int $bodyStoreId = null): array
    {
        $payload = [
            'customer_id' => $seed['customer'],
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $seed['item'], 'quantity' => $quantity, 'unit_price' => '20.000']],
        ];

        if ($bodyStoreId !== null) {
            $payload['store_id'] = $bodyStoreId;
        }

        return $payload;
    }

    /** Admin sells $quantity × 20.000 from $storeId; returns the invoice id. */
    private function sellAsAdmin(Tenant $tenant, array $seed, int $storeId, string $quantity): int
    {
        return (int) $this->postJson('/api/v1/pos/checkout', $this->checkoutPayload($seed, $quantity), $this->tenantHeaders($tenant, null, $storeId))
            ->assertStatus(201)
            ->json('data.id');
    }

    /** The legacy list/summary endpoints return money as JSON numbers: normalise to a scale-3 string. */
    private function money(mixed $value): string
    {
        $this->assertIsNumeric($value);

        return bcadd((string) $value, '0', 3);
    }

    private function stockOf(Tenant $tenant, int $storeId, int $itemId): string
    {
        return (string) $this->inTenant($tenant, fn () => StoreStock::query()
            ->where('store_id', $storeId)->where('item_id', $itemId)->value('quantity'));
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(Tenant $tenant, ?User $user, int|string|null $store): array
    {
        $headers = $this->tenantHeaders($tenant, $user);
        unset($headers['X-Store-Id']);

        if ($store !== null) {
            $headers['X-Store-Id'] = (string) $store;
        }

        return $headers;
    }

    // ------------------------------------------------------------------
    // 1. Every mounted endpoint
    // ------------------------------------------------------------------

    #[DataProvider('mountedEndpoints')]
    public function test_every_mounted_endpoint_is_store_scoped(string $name, string $method, string $uri, bool $concrete): void
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route, $name);
        $this->assertContains($concrete ? 'store.active:concrete' : 'store.active', $route->gatherMiddleware(), $name);

        $tenant = $this->createTenant();
        $seed = $this->seedStores($tenant);
        $cashier = $this->cashierOfA($tenant);
        $storeless = $this->createTenantUser($tenant, 'cashier', self::CASHIER_EXTRA, ['default_store_id' => null]);
        $uri = str_replace('{record}', '1', $uri);

        // Store B (not assigned) and `all` are refused for the cashier of A.
        foreach ([$seed['b'], 'all'] as $store) {
            $this->json($method, $uri, [], $this->headersFor($tenant, $cashier, $store))
                ->assertStatus(403)
                ->assertJsonPath('success', false)
                ->assertJsonPath('error_code', 'store_access_denied');
        }

        // No header and no usable store: only ResolveActiveStore can refuse this (ApiTokenAuth
        // checks an explicit header only), so a 403 here proves the middleware runs.
        $this->json($method, $uri, [], $this->headersFor($tenant, $storeless, null))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'store_access_denied');

        // `all` on a single-branch write is refused even for the admin; elsewhere it is allowed.
        $all = $this->json($method, $uri, [], $this->headersFor($tenant, null, 'all'));
        if ($concrete) {
            $all->assertStatus(403)
                ->assertJsonPath('error_code', 'concrete_store_required')
                ->assertJsonPath('message', __('store_scope.concrete_store_required'));
        } else {
            $this->assertNotSame(403, $all->getStatusCode(), $name.' refused `all` for the admin');
        }

        // A token of another tenant never authenticates here.
        $other = $this->createTenant();
        $otherToken = $this->tenantToken($other);
        $this->json($method, $uri, [], [
            'Accept' => 'application/json',
            'X-Tenant' => (string) $tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$otherToken,
            'X-Store-Id' => (string) $seed['a'],
        ])->assertStatus(401);

        // Nothing was written by any refused request.
        $this->assertSame('20.000', $this->stockOf($tenant, $seed['b'], $seed['item']));
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Invoice::query()->count()));
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => CashShift::query()->count()));
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => StockTransfer::query()->count()));
    }

    // ------------------------------------------------------------------
    // 2. Records of another store: 404, nothing changed
    // ------------------------------------------------------------------

    public function test_invoice_of_store_b_is_not_found_for_the_cashier_of_a_and_cannot_be_cancelled(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedStores($tenant);
        $invoiceB = $this->sellAsAdmin($tenant, $seed, $seed['b'], '2.000');
        $cashier = $this->cashierOfA($tenant);

        $this->getJson('/api/v1/invoices/'.$invoiceB, $this->tenantHeaders($tenant, $cashier))->assertStatus(404);
        $this->postJson('/api/v1/invoices/'.$invoiceB.'/cancel', ['reason' => 'x'], $this->tenantHeaders($tenant, $cashier))
            ->assertStatus(404);

        $this->assertSame('confirmed', $this->inTenant($tenant, fn () => Invoice::query()->whereKey($invoiceB)->value('status')));
        $this->assertSame('18.000', $this->stockOf($tenant, $seed['b'], $seed['item']));

        // Readable for a user who may see every store, and for a cashier assigned to B.
        $this->getJson('/api/v1/invoices/'.$invoiceB, $this->tenantHeaders($tenant, $this->viewAllManager($tenant)))
            ->assertOk()
            ->assertJsonPath('data.id', $invoiceB);

        $cashierB = $this->cashierOfA($tenant);
        $this->inTenant($tenant, fn () => User::query()->findOrFail($cashierB->id)->stores()->attach($seed['b']));
        $this->getJson('/api/v1/invoices/'.$invoiceB, $this->tenantHeaders($tenant, $cashierB))->assertOk();

        // Missing permission is still 403, not 404.
        $noPermission = $this->createTenantUser($tenant, null, ['items.view']);
        $this->getJson('/api/v1/invoices/'.$invoiceB, $this->tenantHeaders($tenant, $noPermission))->assertStatus(403);
    }

    public function test_shift_of_store_b_is_not_found_for_the_cashier_of_a_and_stays_open(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedStores($tenant);
        $shiftB = (int) $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '100.000'], $this->tenantHeaders($tenant, null, $seed['b']))
            ->assertStatus(201)
            ->json('data.id');
        $cashier = $this->cashierOfA($tenant);

        $this->getJson('/api/v1/shifts/'.$shiftB.'/z-report', $this->tenantHeaders($tenant, $cashier))->assertStatus(404);
        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftB, 'actual_cash_balance' => '0.000'], $this->tenantHeaders($tenant, $cashier))
            ->assertStatus(404);

        $this->assertSame('open', $this->inTenant($tenant, fn () => CashShift::query()->whereKey($shiftB)->value('status')));

        // The cashier's own store has no open shift: current is empty, B's shift is not leaked.
        $this->getJson('/api/v1/shifts/current', $this->tenantHeaders($tenant, $cashier))
            ->assertOk()
            ->assertJsonPath('has_active', false);

        $ids = collect($this->getJson('/api/v1/shifts', $this->tenantHeaders($tenant, $cashier))->assertOk()->json('data'))->pluck('id')->all();
        $this->assertNotContains($shiftB, $ids);
    }

    public function test_transfer_between_other_stores_is_not_found_for_the_cashier_of_a(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedStores($tenant);
        $transferId = (int) $this->postJson('/api/v1/transfers', [
            'from_store_id' => $seed['b'],
            'to_store_id' => $seed['c'],
            'transfer_date' => now()->toDateString(),
            'items' => [['item_id' => $seed['item'], 'quantity' => '1.500']],
        ], $this->tenantHeaders($tenant, null, $seed['b']))->assertStatus(201)->json('data.id');
        $cashier = $this->cashierOfA($tenant);

        $this->getJson('/api/v1/transfers/'.$transferId, $this->tenantHeaders($tenant, $cashier))->assertStatus(404);

        $ids = collect($this->getJson('/api/v1/transfers', $this->tenantHeaders($tenant, $cashier))->assertOk()->json('data'))->pluck('id')->all();
        $this->assertNotContains($transferId, $ids);

        $this->getJson('/api/v1/transfers/'.$transferId, $this->tenantHeaders($tenant, $this->viewAllManager($tenant)))->assertOk();
        $this->assertSame('18.500', $this->stockOf($tenant, $seed['b'], $seed['item']));
    }

    // ------------------------------------------------------------------
    // 3. Lists and sums cover the active store only; `all` needs stores.view_all
    // ------------------------------------------------------------------

    public function test_sums_cover_store_a_only_and_all_needs_view_all(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedStores($tenant);
        $invoiceA = $this->sellAsAdmin($tenant, $seed, $seed['a'], '1.000');   // 20.000
        $invoiceB = $this->sellAsAdmin($tenant, $seed, $seed['b'], '2.000');   // 40.000
        $cashier = $this->cashierOfA($tenant);
        $headers = $this->tenantHeaders($tenant, $cashier);

        $list = $this->getJson('/api/v1/invoices', $headers)->assertOk();
        $this->assertSame([$invoiceA], collect($list->json('data'))->pluck('id')->all());
        $this->assertSame(1, $list->json('summary.total_count'));
        $this->assertSame('20.000', $this->money($list->json('summary.total_sales')));

        $this->assertSame('20.000', $this->money($this->getJson('/api/v1/treasury/summary', $headers)->assertOk()->json('today.sales_total')));
        $this->assertSame('20.000', $this->money($this->getJson('/api/v1/dashboard', $headers)->assertOk()->json('metrics.today_sales')));
        $this->assertSame('20.000', $this->money($this->getJson('/api/v1/daily-journal', $headers)->assertOk()->json('data.summary.total_sales')));

        // The per-branch comparison shows only the cashier's branch.
        $rows = collect($this->getJson('/api/v1/reports/stores', $headers)->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$seed['a']], $rows);

        // `all` and other branches, in any form, are refused for the cashier.
        $this->getJson('/api/v1/invoices?store_id=all', $headers)->assertStatus(403);
        $this->getJson('/api/v1/invoices?store_id='.$seed['b'], $headers)->assertStatus(403);
        $this->getJson('/api/v1/reports/summary?store_id='.$seed['b'], $headers)->assertStatus(403);

        // stores.view_all: every store through the header or ?store_id=all.
        $manager = $this->viewAllManager($tenant);
        foreach ([
            ['/api/v1/invoices', $this->headersFor($tenant, $manager, 'all')],
            ['/api/v1/invoices?store_id=all', $this->tenantHeaders($tenant, $manager)],
        ] as [$uri, $managerHeaders]) {
            $all = $this->getJson($uri, $managerHeaders)->assertOk();
            $this->assertSame(2, $all->json('summary.total_count'));
            $this->assertSame('60.000', $this->money($all->json('summary.total_sales')));
        }

        $this->assertEqualsCanonicalizing(
            [$seed['a'], $seed['b'], $seed['c']],
            collect($this->getJson('/api/v1/reports/stores', $this->headersFor($tenant, $manager, 'all'))->assertOk()->json('data'))->pluck('id')->all(),
        );

        // stores.view_all is not access to a concrete unassigned branch (STOR-1); the admin's
        // concrete store still scopes the list to that store.
        $this->getJson('/api/v1/invoices', $this->tenantHeaders($tenant, $manager, $seed['b']))->assertStatus(403);
        $this->assertSame(
            [$invoiceB],
            collect($this->getJson('/api/v1/invoices', $this->tenantHeaders($tenant, null, $seed['b']))->assertOk()->json('data'))->pluck('id')->all(),
        );
    }

    // ------------------------------------------------------------------
    // 4. Checkout: the active store only (CTO Q14)
    // ------------------------------------------------------------------

    public function test_rejected_checkout_leaves_stock_and_invoices_unchanged(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedStores($tenant);
        $cashier = $this->cashierOfA($tenant);

        // Header of B.
        $this->postJson('/api/v1/pos/checkout', $this->checkoutPayload($seed, '1.000'), $this->headersFor($tenant, $cashier, $seed['b']))
            ->assertStatus(403);
        // Own header, body store_id of B.
        $this->postJson('/api/v1/pos/checkout', $this->checkoutPayload($seed, '1.000', $seed['b']), $this->tenantHeaders($tenant, $cashier))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'store_access_denied');
        // `all` for the admin.
        $this->postJson('/api/v1/pos/checkout', $this->checkoutPayload($seed, '1.000'), $this->headersFor($tenant, null, 'all'))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'concrete_store_required');
        // Same on the invoice endpoint.
        $this->postJson('/api/v1/invoices', $this->checkoutPayload($seed, '1.000', $seed['b']), $this->tenantHeaders($tenant, $cashier))
            ->assertStatus(403);

        $this->assertSame('10.000', $this->stockOf($tenant, $seed['a'], $seed['item']));
        $this->assertSame('20.000', $this->stockOf($tenant, $seed['b'], $seed['item']));
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Invoice::query()->count()));
    }

    public function test_an_accessible_body_store_id_is_ignored_and_the_active_store_sells(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedStores($tenant);
        $cashier = $this->cashierOfA($tenant);
        $this->inTenant($tenant, fn () => User::query()->findOrFail($cashier->id)->stores()->attach($seed['b']));

        $invoiceId = (int) $this->postJson('/api/v1/pos/checkout', $this->checkoutPayload($seed, '0.250', $seed['b']), $this->tenantHeaders($tenant, $cashier, $seed['a']))
            ->assertStatus(201)
            ->json('data.id');

        $this->assertSame($seed['a'], (int) $this->inTenant($tenant, fn () => Invoice::query()->whereKey($invoiceId)->value('store_id')));
        $this->assertSame('9.750', $this->stockOf($tenant, $seed['a'], $seed['item']));
        $this->assertSame('20.000', $this->stockOf($tenant, $seed['b'], $seed['item']));
    }
}
