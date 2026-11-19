<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\Catalog\FeatureCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * SHOP-05: CSV export of the existing reports: P&L summary, top items, item profitability.
 */
final class ReportExportApiTest extends TenantTestCase
{
    private const ENDPOINTS = [
        '/api/v1/reports/summary/export',
        '/api/v1/reports/top-items/export',
        '/api/v1/reports/items/export',
    ];

    private Tenant $tenant;

    private Store $store;

    private Store $branch;

    private User $admin;

    private string $adminToken;

    private int $phone = 1000006000;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rate_limits.exports.per_minute' => 1000]);

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        $this->store = $this->adoptMainStore(['name' => 'الرئيسي', 'code' => 'MAIN-001', 'type' => 'retail', 'is_main' => true, 'is_active' => true]);
        $this->branch = Store::create(['name' => 'فرع', 'code' => 'BR-001', 'type' => 'branch', 'is_main' => false, 'is_active' => true]);

        $this->admin = $this->makeUser('admin');
        $this->adminToken = $this->admin->createToken('t')->plainTextToken;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeUser(?string $role, array $permissions = []): User
    {
        $user = User::factory()->create([
            'name' => 'user', 'phone' => '0'.($this->phone++), 'password' => Hash::make('password'),
            'is_active' => true, 'default_store_id' => $this->store->id,
        ]);
        if ($role !== null) {
            $user->assignRole(Role::findByName($role));
        }
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findByName($permission));
        }

        return $user;
    }

    private function fetch(string $url, ?string $token = null): TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer '.($token ?? $this->adminToken),
            'X-Store-Id' => (string) $this->store->id,
        ])->get($url, ['Accept' => 'application/json']);
    }

    /**
     * @return list<list<string|null>>
     */
    private function csvLines(TestResponse $response): array
    {
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $body = (string) preg_replace('/^\xEF\xBB\xBF/', '', $body);

        return array_map('str_getcsv', array_values(array_filter((array) preg_split('/\r?\n/', $body))));
    }

    /**
     * @return array<string, string|null> label => value of a two-column summary sheet
     */
    private function summarySheet(TestResponse $response): array
    {
        $lines = $this->csvLines($response);
        $this->assertSame([__('reports.csv_metric'), __('reports.csv_value')], $lines[0]);

        $sheet = [];
        foreach (array_slice($lines, 1) as $line) {
            $sheet[(string) $line[0]] = $line[1];
        }

        return $sheet;
    }

    private function item(string $name): Item
    {
        static $n = 0;
        $n++;

        return Item::create([
            'name' => $name, 'code' => 'EXP-'.$n, 'unit' => 'pcs',
            'cost_price' => '2.000', 'selling_price' => '10.000', 'current_stock' => '100.000', 'is_active' => true,
        ]);
    }

    /**
     * One confirmed invoice of the active store, one line per [item, qty, line total].
     *
     * @param  list<array{0: Item, 1: string, 2: string}>  $lines
     */
    private function sell(array $lines, ?Store $store = null): Invoice
    {
        static $n = 0;
        $n++;
        $customer = Customer::create(['name' => 'عميل '.$n, 'phone' => '0'.($this->phone++), 'current_balance' => '0.000', 'is_active' => true]);
        $total = '0.000';
        foreach ($lines as [, , $lineTotal]) {
            $total = bcadd($total, $lineTotal, 3);
        }
        $cost = '0.000';
        foreach ($lines as [, $qty]) {
            $cost = bcadd($cost, bcmul($qty, '2.000', 3), 3);
        }

        $invoice = Invoice::create([
            'invoice_number' => 'INV-EXP-'.$n, 'customer_id' => $customer->id, 'user_id' => $this->admin->id,
            'store_id' => ($store ?? $this->store)->id, 'invoice_date' => now()->toDateString(),
            'subtotal' => $total, 'discount_amount' => '0.000', 'net_total' => $total, 'paid_amount' => $total,
            'remaining_amount' => '0.000', 'total_cost' => $cost, 'status' => 'confirmed', 'payment_type' => 'cash',
        ]);
        foreach ($lines as [$item, $qty, $lineTotal]) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id, 'item_id' => $item->id, 'quantity' => $qty, 'unit_price' => bcdiv($lineTotal, $qty, 3),
                'cost_price' => '2.000', 'discount_amount' => '0.000', 'total_price' => $lineTotal,
            ]);
        }

        return $invoice;
    }

    private function seedSales(): void
    {
        $beans = $this->item('بن');
        $tea = $this->item('شاي');
        $sugar = $this->item('سكر');
        $this->sell([[$beans, '3.000', '45.500'], [$tea, '2.000', '20.000']]);
        $this->sell([[$beans, '1.000', '15.250'], [$sugar, '4.000', '8.000']]);
        $this->sell([[$beans, '9.000', '999.000']], $this->branch); // another store: never in the main store's CSV
    }

    /**
     * @param  array<string, mixed>  $features  overrides of the "everything on" feature map
     */
    private function tenantOnPlan(array $features): Tenant
    {
        $slug = 'exp-'.Str::lower(Str::random(10));
        $plan = Plan::query()->create([
            'name' => $slug, 'slug' => $slug, 'price_monthly' => '499.000', 'price_yearly' => '4990.000',
            'features' => array_merge(array_fill_keys(FeatureCatalog::keys(), true), $features),
            'max_users' => null, 'max_stores' => null, 'max_warehouses' => null, 'max_vans' => null,
            'max_items' => null, 'is_active' => true, 'sort_order' => 1,
        ]);

        return $this->createTenant(['plan_id' => $plan->id, 'status' => 'active']);
    }

    public function test_guest_is_unauthorized(): void
    {
        foreach (self::ENDPOINTS as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    }

    public function test_user_without_reports_view_is_forbidden(): void
    {
        $token = $this->makeUser('cashier')->createToken('t')->plainTextToken;
        foreach (self::ENDPOINTS as $url) {
            $this->fetch($url, $token)->assertForbidden();
        }
    }

    public function test_store_filter_outside_the_users_stores_is_forbidden(): void
    {
        $token = $this->makeUser(null, ['reports.view'])->createToken('t')->plainTextToken;
        foreach (self::ENDPOINTS as $url) {
            $this->fetch($url.'?store_id='.$this->branch->id, $token)->assertForbidden();
        }
    }

    public function test_period_longer_than_the_maximum_is_422(): void
    {
        $query = '?from='.now()->subDays(400)->toDateString().'&to='.now()->toDateString();
        foreach (self::ENDPOINTS as $url) {
            $this->fetch($url.$query)->assertStatus(422);
        }
    }

    public function test_summary_csv_holds_the_same_figures_as_the_json_summary(): void
    {
        $this->seedSales();

        $json = $this->fetch('/api/v1/reports/summary')->assertOk()->json('summary');
        $sheet = $this->summarySheet($this->fetch('/api/v1/reports/summary/export')->assertOk());

        $this->assertSame('88.750', $json['total_sales']);
        $this->assertSame('88.750', $sheet[__('reports.pl_total_sales')]);
        $this->assertSame($json['gross_profit'], $sheet[__('reports.pl_gross_profit')]);
        $this->assertSame($json['total_cogs'], $sheet[__('reports.pl_total_cogs')]);
        $this->assertSame($json['net_profit'], $sheet[__('reports.pl_net_profit')]);
        $this->assertSame('2', $sheet[__('reports.pl_invoices_count')]);
        $this->assertSame(now()->startOfMonth()->toDateString(), $sheet[__('reports.csv_period_from')]);
        $this->assertArrayNotHasKey(__('reports.csv_totals_row'), $sheet, 'A label / value sheet has no totals row.');
    }

    public function test_top_items_csv_lists_the_top_rows_and_ends_with_their_totals(): void
    {
        $this->seedSales();

        $lines = $this->csvLines($this->fetch('/api/v1/reports/top-items/export')->assertOk());
        $header = $lines[0];
        $col = fn (string $key): int => (int) array_search(__($key), $header, true);

        $this->assertSame(__('reports.col_item'), $header[0]);
        $this->assertCount(5, $lines); // header + 3 items + totals
        $this->assertSame('بن', $lines[1][0]); // highest net revenue first
        $this->assertSame('60.750', $lines[1][$col('reports.col_net_sales')]);
        $this->assertSame('4.000', $lines[1][$col('reports.col_net_qty')]);
        $this->assertSame('52.750', $lines[1][$col('reports.col_profit')]);

        $totals = end($lines);
        $this->assertSame(__('reports.csv_totals_row'), $totals[0]);
        $this->assertSame('88.750', $totals[$col('reports.col_net_sales')]);
        $this->assertSame('20.000', $totals[$col('reports.col_cogs')]);
        $this->assertSame('68.750', $totals[$col('reports.col_profit')]);
        $this->assertSame('77.4', $totals[$col('reports.col_margin')]);
    }

    public function test_items_csv_has_every_row_not_one_page_and_the_json_totals(): void
    {
        $this->seedSales();

        $json = $this->fetch('/api/v1/reports/items?per_page=1')->assertOk();
        $this->assertCount(1, $json->json('data'));

        $lines = $this->csvLines($this->fetch('/api/v1/reports/items/export?per_page=1')->assertOk());
        $header = $lines[0];
        $col = fn (string $key): int => (int) array_search(__($key), $header, true);

        $this->assertCount(5, $lines); // header + all 3 items + totals
        $totals = end($lines);
        $this->assertSame(__('reports.csv_totals_row'), $totals[0]);
        $this->assertSame($json->json('totals.total_revenue'), $totals[$col('reports.col_net_sales')]);
        $this->assertSame($json->json('totals.profit'), $totals[$col('reports.col_profit')]);
        $this->assertSame($json->json('totals.margin'), $totals[$col('reports.col_margin')]);
        $this->assertSame('88.750', $totals[$col('reports.col_net_sales')]);
    }

    public function test_a_user_without_items_view_cost_gets_no_cost_or_profit_in_any_csv(): void
    {
        $this->seedSales();
        $token = $this->makeUser(null, ['reports.view'])->createToken('t')->plainTextToken;

        $sheet = $this->summarySheet($this->fetch('/api/v1/reports/summary/export', $token)->assertOk());
        $this->assertSame('88.750', $sheet[__('reports.pl_total_sales')]);
        foreach (['pl_gross_cogs', 'pl_total_cogs', 'pl_returns_cost', 'pl_gross_profit', 'pl_margin_percentage', 'pl_net_profit', 'pl_stock_cost_valuation', 'pl_expected_stock_profit'] as $key) {
            $this->assertArrayNotHasKey(__('reports.'.$key), $sheet, $key);
        }

        foreach (['/api/v1/reports/top-items/export', '/api/v1/reports/items/export'] as $url) {
            $lines = $this->csvLines($this->fetch($url, $token)->assertOk());
            $this->assertContains(__('reports.col_net_sales'), $lines[0], $url);
            foreach (['col_cogs', 'col_profit', 'col_margin'] as $key) {
                $this->assertNotContains(__('reports.'.$key), $lines[0], $url.' '.$key);
            }
            $this->assertCount(count($lines[0]), end($lines), 'The totals row has no hidden column either.');
            $this->assertStringNotContainsString('68.750', implode(',', array_map(fn (array $l): string => implode(',', $l), $lines)));
        }

        // The same user WITH items.view_cost gets them.
        $costUser = $this->makeUser(null, ['reports.view', 'items.view_cost'])->createToken('t')->plainTextToken;
        $lines = $this->csvLines($this->fetch('/api/v1/reports/top-items/export', $costUser)->assertOk());
        $this->assertContains(__('reports.col_profit'), $lines[0]);
    }

    public function test_a_shop_without_reports_advanced_gets_the_plan_gated_figures_stripped(): void
    {
        $tenant = $this->tenantOnPlan(['reports.advanced' => false]);
        $headers = $this->tenantHeaders($tenant);

        $sheet = $this->summarySheet($this->get('/api/v1/reports/summary/export', $headers)->assertOk());
        $this->assertArrayHasKey(__('reports.pl_gross_profit'), $sheet, 'The one figure the owner kept on every plan.');
        $this->assertArrayHasKey(__('reports.pl_total_sales'), $sheet);
        foreach (['pl_net_profit', 'pl_total_cogs', 'pl_margin_percentage', 'pl_expected_stock_profit'] as $key) {
            $this->assertArrayNotHasKey(__('reports.'.$key), $sheet, $key);
        }

        $top = $this->csvLines($this->get('/api/v1/reports/top-items/export', $headers)->assertOk());
        foreach (['col_cogs', 'col_profit', 'col_margin'] as $key) {
            $this->assertNotContains(__('reports.'.$key), $top[0], $key);
        }

        $this->getJson('/api/v1/reports/items/export', $headers)->assertStatus(403);
    }

    public function test_a_shop_without_reports_export_cannot_export_any_report(): void
    {
        $tenant = $this->tenantOnPlan(['reports.export' => false]);
        $headers = $this->tenantHeaders($tenant);

        foreach ([...self::ENDPOINTS, '/api/v1/reports/sales-by-cashier/export', '/api/v1/reports/customer-aging/export'] as $url) {
            $this->getJson($url, $headers)
                ->assertStatus(403)
                ->assertJsonPath('details.feature', 'reports.export');
        }
        $this->getJson('/api/v1/reports/summary', $headers)->assertOk();
    }
}
