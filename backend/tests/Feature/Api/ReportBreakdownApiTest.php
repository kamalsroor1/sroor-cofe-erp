<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use App\Models\ReturnItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * SHOP-05: sales by cashier, sales by category, customer debt aging, supplier payables (+ CSV).
 */
final class ReportBreakdownApiTest extends TenantTestCase
{
    private const ENDPOINTS = [
        '/api/v1/reports/sales-by-cashier',
        '/api/v1/reports/sales-by-category',
        '/api/v1/reports/customer-aging',
        '/api/v1/reports/supplier-payables',
        '/api/v1/reports/sales-by-cashier/export',
        '/api/v1/reports/sales-by-category/export',
        '/api/v1/reports/customer-aging/export',
        '/api/v1/reports/supplier-payables/export',
    ];

    private Tenant $tenant;

    private Store $store;

    private Store $branch;

    private User $admin;

    private string $adminToken;

    private int $phone = 1000005000;

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

    private function makeUser(?string $role, array $permissions = [], string $name = 'user'): User
    {
        $user = User::factory()->create([
            'name' => $name, 'phone' => '0'.($this->phone++), 'password' => Hash::make('password'),
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

    private function fetch(string $url, ?string $token = null, string $store = 'main')
    {
        $storeHeader = $store === 'all' ? 'all' : (string) $this->store->id;

        return $this->withHeaders([
            'Authorization' => 'Bearer '.($token ?? $this->adminToken),
            'X-Store-Id' => $storeHeader,
        ])->get($url, ['Accept' => 'application/json']);
    }

    private function customer(string $name, string $balance): Customer
    {
        return Customer::create(['name' => $name, 'phone' => '0'.($this->phone++), 'current_balance' => $balance, 'balance' => $balance, 'price_tier' => 'retail', 'is_active' => true]);
    }

    private function invoice(Customer $customer, User $cashier, string $netTotal, int $daysAgo = 0, ?Store $store = null, string $discount = '0.000', string $paid = ''): Invoice
    {
        static $n = 0;
        $n++;
        $paid = $paid === '' ? $netTotal : $paid;

        return Invoice::create([
            'invoice_number' => 'INV-B-'.$n, 'customer_id' => $customer->id, 'user_id' => $cashier->id,
            'store_id' => ($store ?? $this->store)->id, 'invoice_date' => now()->subDays($daysAgo)->toDateString(),
            'subtotal' => $netTotal, 'discount_amount' => $discount, 'net_total' => $netTotal, 'paid_amount' => $paid,
            'remaining_amount' => bcsub($netTotal, $paid, 3), 'total_cost' => '0.000', 'status' => 'confirmed', 'payment_type' => 'cash',
        ]);
    }

    private function item(string $name, ?Category $category): Item
    {
        static $n = 0;
        $n++;

        return Item::create([
            'name' => $name, 'code' => 'SH5-'.$n, 'category_id' => $category?->id, 'unit' => 'pcs',
            'cost_price' => '1.000', 'selling_price' => '10.000', 'current_stock' => '100.000', 'is_active' => true,
        ]);
    }

    private function line(Invoice $invoice, Item $item, string $qty, string $total): InvoiceItem
    {
        return InvoiceItem::create([
            'invoice_id' => $invoice->id, 'item_id' => $item->id, 'quantity' => $qty, 'unit_price' => bcdiv($total, $qty, 3),
            'cost_price' => '1.000', 'discount_amount' => '0.000', 'total_price' => $total,
        ]);
    }

    private function salesReturn(User $user, Customer $customer, Item $item, string $qty, string $total, ?InvoiceItem $line = null, ?Store $store = null): void
    {
        static $n = 0;
        $n++;
        $return = ReturnDocument::create([
            'return_number' => 'RET-B-'.$n, 'return_type' => 'sales_return', 'customer_id' => $customer->id,
            'invoice_id' => $line?->invoice_id, 'user_id' => $user->id, 'store_id' => ($store ?? $this->store)->id,
            'total_amount' => $total, 'return_date' => now()->toDateString(),
        ]);
        ReturnItem::create([
            'return_id' => $return->id, 'item_id' => $item->id, 'quantity' => $qty, 'unit_price' => bcdiv($total, $qty, 3),
            'total_price' => $total, 'invoice_item_id' => $line?->id,
        ]);
    }

    private function csvLines(string $body): array
    {
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);

        return array_map('str_getcsv', array_values(array_filter(preg_split('/\r?\n/', $body))));
    }

    public function test_guest_is_unauthorized_on_every_endpoint(): void
    {
        foreach (self::ENDPOINTS as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    }

    public function test_cashier_without_reports_view_is_forbidden(): void
    {
        $token = $this->makeUser('cashier')->createToken('t')->plainTextToken;
        foreach (self::ENDPOINTS as $url) {
            $this->fetch($url, $token)->assertForbidden();
        }
    }

    public function test_period_longer_than_the_maximum_is_422(): void
    {
        $from = now()->subDays(400)->toDateString();
        $to = now()->toDateString();
        foreach (['/api/v1/reports/sales-by-cashier', '/api/v1/reports/sales-by-category', '/api/v1/reports/sales-by-cashier/export'] as $url) {
            $this->fetch($url.'?from='.$from.'&to='.$to)->assertStatus(422);
        }
    }

    public function test_store_filter_outside_the_users_stores_is_forbidden(): void
    {
        $token = $this->makeUser(null, ['reports.view'])->createToken('t')->plainTextToken;
        $this->fetch('/api/v1/reports/sales-by-cashier', $token)->assertOk();
        foreach (self::ENDPOINTS as $url) {
            $this->fetch($url.'?store_id='.$this->branch->id, $token)->assertForbidden();
        }
    }

    public function test_sales_by_cashier_nets_returns_and_is_store_scoped(): void
    {
        $customer = $this->customer('عميل', '0.000');
        $ali = $this->makeUser(null, [], 'Ali');
        $mona = $this->makeUser(null, [], 'Mona');
        $item = $this->item('صنف', null);

        $line = $this->line($this->invoice($customer, $ali, '100.500', discount: '5.000'), $item, '1.000', '100.500');
        $this->invoice($customer, $ali, '50.250', paid: '20.000');
        $this->invoice($customer, $mona, '80.000');
        $this->invoice($customer, $mona, '999.000', store: $this->branch);
        $this->salesReturn($ali, $customer, $item, '1.000', '30.125', $line);

        $res = $this->fetch('/api/v1/reports/sales-by-cashier')->assertOk();
        $rows = collect($res->json('data'))->keyBy('name');

        $this->assertSame('150.750', $rows['Ali']['sales']);
        $this->assertSame('30.125', $rows['Ali']['returns_value']);
        $this->assertSame('120.625', $rows['Ali']['net_sales']);
        $this->assertSame('5.000', $rows['Ali']['discounts']);
        $this->assertSame('30.250', $rows['Ali']['credit']);
        $this->assertSame(2, $rows['Ali']['invoices_count']);
        $this->assertSame('80.000', $rows['Mona']['net_sales']);
        $this->assertSame('200.625', $res->json('totals.net_sales'));
        $this->assertSame('230.750', $res->json('totals.sales'));

        $all = $this->fetch('/api/v1/reports/sales-by-cashier', null, 'all')->assertOk();
        $this->assertSame('1199.625', $all->json('totals.net_sales'));
    }

    public function test_sales_by_category_nets_returns_on_the_line_basis(): void
    {
        $customer = $this->customer('عميل', '0.000');
        $coffee = Category::create(['name' => 'قهوة', 'color' => '#7c2d12', 'sort_order' => 1, 'is_active' => true]);
        $tea = Category::create(['name' => 'شاي', 'color' => '#166534', 'sort_order' => 2, 'is_active' => true]);
        $beans = $this->item('بن', $coffee);
        $leaves = $this->item('شاي أخضر', $tea);
        $loose = $this->item('بدون فئة', null);

        $invoice = $this->invoice($customer, $this->admin, '360.000');
        $coffeeLine = $this->line($invoice, $beans, '4.000', '200.000');
        $this->line($invoice, $leaves, '2.000', '100.000');
        $this->line($invoice, $loose, '3.000', '60.000');
        $this->salesReturn($this->admin, $customer, $beans, '1.000', '50.000', $coffeeLine);

        $res = $this->fetch('/api/v1/reports/sales-by-category')->assertOk();
        $rows = collect($res->json('data'))->keyBy('name');

        $this->assertSame('200.000', $rows['قهوة']['gross_sales']);
        $this->assertSame('50.000', $rows['قهوة']['returns_value']);
        $this->assertSame('150.000', $rows['قهوة']['net_sales']);
        $this->assertSame('1.000', $rows['قهوة']['returns_qty']);
        $this->assertSame('100.000', $rows['شاي']['net_sales']);
        $this->assertSame('60.000', $rows[__('reports.uncategorized')]['net_sales']);
        $this->assertSame('310.000', $res->json('totals.net_sales'));
        $this->assertSame('48.39', $rows['قهوة']['share_percent']);
        $this->assertSame('قهوة', $res->json('data.0.name'));
    }

    public function test_customer_aging_settles_the_oldest_invoices_first(): void
    {
        // Invoices of 750 in four age buckets; the customer still owes 500 (250 was paid), so the
        // payment cleared the 120-day invoice (300) only partly: 50 of it is still due.
        $customer = $this->customer('مدين', '500.000');
        $this->invoice($customer, $this->admin, '100.000', 10);
        $this->invoice($customer, $this->admin, '200.000', 45, $this->branch);
        $this->invoice($customer, $this->admin, '150.000', 75);
        $this->invoice($customer, $this->admin, '300.000', 120);
        $opening = $this->customer('رصيد افتتاحي', '80.000');
        $this->customer('مسدد', '0.000');

        $res = $this->fetch('/api/v1/reports/customer-aging', null, 'all')->assertOk();
        $rows = collect($res->json('data'))->keyBy('name');

        $this->assertCount(2, $rows);
        $this->assertSame('100.000', $rows['مدين']['bucket_0_30']);
        $this->assertSame('200.000', $rows['مدين']['bucket_31_60']);
        $this->assertSame('150.000', $rows['مدين']['bucket_61_90']);
        $this->assertSame('50.000', $rows['مدين']['bucket_90_plus']);
        $this->assertSame('0.000', $rows['مدين']['unallocated']);
        $this->assertSame('500.000', $rows['مدين']['total']);
        $this->assertSame('80.000', $rows['رصيد افتتاحي']['unallocated']);
        $this->assertSame($opening->id, $rows['رصيد افتتاحي']['id']);
        $this->assertSame('580.000', $res->json('totals.total'));
        $this->assertSame(2, $res->json('meta.total'));

        // Branch filter: only what is allocated to the branch's invoices.
        $branch = $this->fetch('/api/v1/reports/customer-aging?store_id='.$this->branch->id, null, 'all')->assertOk();
        $this->assertCount(1, $branch->json('data'));
        $this->assertSame('200.000', $branch->json('data.0.bucket_31_60'));
        $this->assertSame('200.000', $branch->json('totals.total'));
        $this->assertSame('0.000', $branch->json('totals.unallocated'));
    }

    public function test_supplier_payables_are_aged_by_purchase_date(): void
    {
        $supplier = Supplier::create(['name' => 'مورد', 'phone' => '0100', 'current_balance' => '250.000', 'is_active' => true]);
        Supplier::create(['name' => 'مورد مسدد', 'current_balance' => '0.000', 'is_active' => true]);
        foreach ([[400, 5], [200, 100]] as $i => [$total, $daysAgo]) {
            Purchase::create([
                'purchase_number' => 'PUR-B-'.$i, 'supplier_id' => $supplier->id, 'user_id' => $this->admin->id,
                'store_id' => $this->store->id, 'purchase_date' => now()->subDays($daysAgo)->toDateString(),
                'status' => 'confirmed', 'subtotal' => $total, 'net_total' => $total, 'paid_amount' => '0.000', 'remaining_amount' => $total,
            ]);
        }

        $res = $this->fetch('/api/v1/reports/supplier-payables')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('250.000', $res->json('data.0.bucket_0_30'));
        $this->assertSame('0.000', $res->json('data.0.bucket_90_plus'));
        $this->assertSame('250.000', $res->json('totals.total'));
    }

    public function test_csv_ends_with_the_totals_row_and_neutralises_formulas(): void
    {
        $evil = $this->customer('=HYPERLINK("http://x")', '120.000');
        $this->invoice($evil, $this->admin, '120.000', 3);
        $this->customer('عميل عادي', '30.000');

        $json = $this->fetch('/api/v1/reports/customer-aging', null, 'all')->assertOk();
        $res = $this->fetch('/api/v1/reports/customer-aging/export', null, 'all')->assertOk();
        $this->assertStringContainsString('text/csv', (string) $res->headers->get('Content-Type'));
        $body = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);

        $lines = $this->csvLines($body);
        $this->assertCount(4, $lines); // header + 2 customers + totals
        $last = end($lines);
        $this->assertSame(__('reports.csv_totals_row'), $last[0]);
        $this->assertSame($json->json('totals.total'), end($last));
        $this->assertSame('150.000', end($last));
        $this->assertSame('\'=HYPERLINK("http://x")', $lines[1][0]);

        $cashier = $this->fetch('/api/v1/reports/sales-by-cashier/export')->assertOk();
        $cashierLines = $this->csvLines($cashier->streamedContent());
        $this->assertSame(__('reports.csv_totals_row'), end($cashierLines)[0]);
        $this->assertSame('120.000', end($cashierLines)[2]);
    }

    public function test_reports_never_include_another_tenants_data(): void
    {
        $mine = $this->customer('عميلي', '10.000');
        $this->invoice($mine, $this->admin, '10.000', 1, paid: '0.000');

        $other = $this->createTenant();
        $otherStoreId = (int) $this->tenantStore($other)->id;
        $otherAdminId = (int) $this->tenantAdmin($other)->id;
        $this->inTenant($other, function () use ($otherStoreId, $otherAdminId): void {
            $customer = Customer::create(['name' => 'عميل آخر', 'current_balance' => '7777.000', 'is_active' => true]);
            Supplier::create(['name' => 'مورد آخر', 'current_balance' => '5555.000', 'is_active' => true]);
            Invoice::create([
                'invoice_number' => 'INV-OTHER-1', 'store_id' => $otherStoreId, 'customer_id' => $customer->id,
                'user_id' => $otherAdminId, 'invoice_date' => now()->toDateString(), 'net_total' => '7777.000',
                'paid_amount' => '0.000', 'remaining_amount' => '7777.000', 'status' => 'confirmed',
            ]);
        });
        $this->useTenantForTest($this->tenant);

        $this->fetch('/api/v1/reports/sales-by-cashier', null, 'all')->assertOk()->assertJsonPath('totals.sales', '10.000');
        $this->fetch('/api/v1/reports/sales-by-category', null, 'all')->assertOk()->assertJsonPath('totals.gross_sales', '0.000');
        $aging = $this->fetch('/api/v1/reports/customer-aging', null, 'all')->assertOk();
        $this->assertSame('10.000', $aging->json('totals.total'));
        $this->assertStringNotContainsString('عميل آخر', $aging->getContent());
        $this->fetch('/api/v1/reports/supplier-payables', null, 'all')->assertOk()->assertJsonPath('totals.total', '0.000');
    }

    public function test_query_count_does_not_grow_with_the_number_of_invoices(): void
    {
        $customer = $this->customer('كثير', '1000000.000');
        $category = Category::create(['name' => 'قهوة', 'color' => '#000000', 'sort_order' => 1, 'is_active' => true]);
        $item = $this->item('بن', $category);

        $seed = function (int $count, int $offset) use ($customer, $item): void {
            $now = now()->toDateTimeString();
            $invoices = [];
            for ($i = 0; $i < $count; $i++) {
                $invoices[] = [
                    'invoice_number' => 'BULK-'.($offset + $i), 'customer_id' => $customer->id, 'user_id' => $this->admin->id,
                    'store_id' => $this->store->id, 'invoice_date' => now()->subDays($i % 120)->toDateString(),
                    'subtotal' => '10.000', 'net_total' => '10.000', 'paid_amount' => '0.000', 'remaining_amount' => '10.000',
                    'status' => 'confirmed', 'payment_type' => 'credit', 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            foreach (array_chunk($invoices, 200) as $chunk) {
                DB::table('invoices')->insert($chunk);
            }
            $ids = DB::table('invoices')->where('invoice_number', 'like', 'BULK-%')->where('id', '>', 0)->pluck('id')->slice(-$count);
            $lines = $ids->map(fn ($id) => [
                'invoice_id' => $id, 'item_id' => $item->id, 'quantity' => '1.000', 'cost_price' => '1.000',
                'unit_price' => '10.000', 'discount_amount' => '0.000', 'total_price' => '10.000', 'created_at' => $now, 'updated_at' => $now,
            ])->all();
            foreach (array_chunk($lines, 200) as $chunk) {
                DB::table('invoice_items')->insert($chunk);
            }
        };

        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            // Sanctum's last_used_at / tenant activity writes are time-throttled, not data-bound.
            if (! str_starts_with($query->sql, 'update')) {
                $queries++;
            }
        });
        $count = function (string $url) use (&$queries): int {
            $queries = 0;
            $this->fetch($url, null, 'all')->assertOk();

            return $queries;
        };

        $seed(10, 0);
        foreach (array_slice(self::ENDPOINTS, 0, 4) as $url) {
            $count($url); // warm-up: first-request caches (permissions, settings)
        }
        $small = [];
        foreach (array_slice(self::ENDPOINTS, 0, 4) as $url) {
            $small[$url] = $count($url);
        }

        $seed(990, 10);
        foreach (array_slice(self::ENDPOINTS, 0, 4) as $url) {
            $this->assertSame($small[$url], $count($url), $url);
        }
    }
}
