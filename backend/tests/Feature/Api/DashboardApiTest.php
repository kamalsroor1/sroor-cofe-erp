<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

class DashboardApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    protected string $adminToken;

    protected Store $mainStore;

    protected Store $branchStore;

    protected Customer $customer;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixtures and assertions run inside the tenant DB; every HTTP call selects the
        // tenant with X-Tenant (TenantTestCase ends tenancy around each request).
        $this->tenant = $this->createTenant();
        tenancy()->initialize($this->tenant);
        $this->withHeaders(['X-Tenant' => (string) $this->tenant->getTenantKey()]);

        // The harness main store, given this test's fixture identity.
        $this->mainStore = Store::query()->findOrFail($this->tenantStore($this->tenant)->id);
        $this->mainStore->update([
            'name' => 'المحمصة المركزية',
            'code' => 'ROAST-MAIN',
            'type' => 'retail',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->branchStore = Store::create([
            'name' => 'فرع المعادي',
            'code' => 'ROAST-MAADI',
            'type' => 'branch',
            'is_main' => false,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');

        $this->adminUser = User::factory()->create([
            'name' => 'كمال سرور',
            'phone' => self::ADMIN_PHONE,
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $this->adminUser->assignRole($adminRole);
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;

        $this->customer = Customer::create([
            'name' => 'عميل مميز للداشبورد',
            'phone' => '01000007005',
            'current_balance' => '750.000',
            'is_active' => true,
        ]);

        $this->item = Item::create([
            'name' => 'بن إثيوبي هرري',
            'code' => 'BN-ETH-HAR',
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '400.000',
            'selling_price' => '650.000',
            'price_retail' => '650.000',
            'price_wholesale' => '600.000',
            'current_stock' => '4.000', // Low stock alert trigger
            'min_stock' => '15.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $this->mainStore->id,
            'item_id' => $this->item->id,
            'quantity' => '4.000',
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/dashboard');
        $response->assertStatus(401);
    }

    public function test_authenticated_admin_can_fetch_complete_dashboard_payload(): void
    {
        $today = now()->toDateString();

        // 1. Invoice
        $invoice = Invoice::create([
            'invoice_number' => 'INV-DASH-001',
            'store_id' => $this->mainStore->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->adminUser->id,
            'invoice_date' => $today,
            'subtotal' => '1300.000',
            'discount_amount' => '0.000',
            'tax_amount' => '0.000',
            'net_total' => '1300.000',
            'paid_amount' => '1300.000',
            'remaining_amount' => '0.000',
            'status' => 'confirmed',
            'payment_type' => 'cash',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_id' => $this->item->id,
            'quantity' => '2.000',
            'unit_price' => '650.000',
            'cost_price' => '400.000',
            'unit_cost' => '400.000',
            'total_price' => '1300.000',
            'discount_amount' => '0.000',
            'tax_amount' => '0.000',
            'net_price' => '1300.000',
        ]);

        // 2. Active Shift
        CashShift::create([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStore->id,
            'shift_number' => 'SHF-DASH-01',
            'status' => 'open',
            'opened_at' => now(),
            'opening_cash_balance' => '1000.000',
        ]);

        // 3. Expense
        Expense::create([
            'store_id' => $this->mainStore->id,
            'user_id' => $this->adminUser->id,
            'expense_number' => 'EXP-DASH-01',
            'title' => 'فواتير تشغيل',
            'amount' => '200.000',
            'category' => 'تشغيلي',
            'cost_center' => 'فرع رئيسي',
            'expense_date' => $today,
            'payment_method' => 'cash',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'metrics' => [
                        'today_sales',
                        'monthly_sales',
                        'monthly_gross_profit',
                        'customers_debt',
                        'today_invoices_count',
                    ],
                    'analytics' => [
                        'daily_trend',
                        'hourly_sales',
                        'peak_hour',
                        'payment_distribution',
                        'period',
                    ],
                    'recent_invoices',
                    'low_stock_items',
                    'active_shift',
                ],
                'metrics',
            ]);

        $this->assertEquals(1300.0, (float) $response->json('data.metrics.today_sales'));
    }

    public function test_active_shift_reports_opening_cash_and_expected_drawer_cash(): void
    {
        $today = now()->toDateString();

        CashShift::create([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStore->id,
            'shift_number' => 'SHF-DASH-02',
            'status' => 'open',
            'opened_at' => now()->subMinutes(5),
            'opening_cash_balance' => '1000.000',
        ]);

        Invoice::create([
            'invoice_number' => 'INV-DASH-CASH',
            'store_id' => $this->mainStore->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->adminUser->id,
            'invoice_date' => $today,
            'subtotal' => '1300.000',
            'discount_amount' => '0.000',
            'tax_amount' => '0.000',
            'net_total' => '1300.000',
            'paid_amount' => '1300.000',
            'remaining_amount' => '0.000',
            'status' => 'confirmed',
            'payment_type' => 'cash',
        ]);

        Expense::create([
            'store_id' => $this->mainStore->id,
            'user_id' => $this->adminUser->id,
            'expense_number' => 'EXP-DASH-02',
            'title' => 'مصروف نقدي',
            'amount' => '200.000',
            'category' => 'تشغيلي',
            'cost_center' => 'operational',
            'expense_date' => $today,
            'payment_method' => 'cash',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'X-Store-Id' => (string) $this->mainStore->id,
        ])->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        // Opening balance comes from opening_cash_balance; current cash is the
        // shift's expected drawer balance: 1000 + 1300 cash sale - 200 cash expense.
        $this->assertEquals(1000.0, $response->json('data.active_shift.starting_cash'));
        $this->assertEquals(2100.0, $response->json('data.active_shift.current_cash'));
    }

    public function test_dashboard_respects_x_store_id_header(): void
    {
        $today = now()->toDateString();

        // Create invoice on branch store
        Invoice::create([
            'invoice_number' => 'INV-MAADI-001',
            'store_id' => $this->branchStore->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->adminUser->id,
            'invoice_date' => $today,
            'subtotal' => '2500.000',
            'discount_amount' => '0.000',
            'tax_amount' => '0.000',
            'net_total' => '2500.000',
            'paid_amount' => '2500.000',
            'remaining_amount' => '0.000',
            'status' => 'confirmed',
            'payment_type' => 'cash',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'X-Store-Id' => (string) $this->branchStore->id,
        ])->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $this->assertEquals(2500.0, (float) $response->json('data.metrics.today_sales'));
    }

    public function test_dashboard_low_stock_alerts_detected_correctly(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $lowStockItems = $response->json('data.low_stock_items');
        $this->assertNotEmpty($lowStockItems);
        $this->assertEquals('بن إثيوبي هرري', $lowStockItems[0]['name']);
    }

    public function test_dashboard_never_mixes_in_another_tenants_sales_or_stock(): void
    {
        $other = $this->createTenant(); // ends tenancy
        $otherStoreId = (int) $this->tenantStore($other)->id;
        $otherAdminId = (int) $this->tenantAdmin($other)->id;
        $this->inTenant($other, function () use ($otherStoreId, $otherAdminId): void {
            $customer = Customer::create(['name' => 'عميل آخر', 'current_balance' => '5000.000', 'is_active' => true]);
            Invoice::create([
                'invoice_number' => 'INV-OTHER-001',
                'store_id' => $otherStoreId,
                'customer_id' => $customer->id,
                'user_id' => $otherAdminId,
                'invoice_date' => now()->toDateString(),
                'subtotal' => '9999.000',
                'net_total' => '9999.000',
                'paid_amount' => '9999.000',
                'remaining_amount' => '0.000',
                'status' => 'confirmed',
                'payment_type' => 'cash',
            ]);
            Item::create([
                'name' => 'صنف ناقص لمستأجر آخر',
                'code' => 'BN-OTHER-LOW',
                'cost_price' => '1.000',
                'selling_price' => '2.000',
                'current_stock' => '1.000',
                'min_stock' => '50.000',
                'is_active' => true,
            ]);
        });
        tenancy()->initialize($this->tenant);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $this->assertEquals(0.0, (float) $response->json('data.metrics.today_sales'));
        $this->assertEquals(750.0, (float) $response->json('data.metrics.customers_debt'));
        $this->assertNotContains('صنف ناقص لمستأجر آخر', collect($response->json('data.low_stock_items'))->pluck('name')->all());

        // This tenant's token cannot open the other tenant's dashboard.
        $this->withHeaders(['X-Tenant' => (string) $other->getTenantKey(), 'Authorization' => 'Bearer '.$this->adminToken])
            ->getJson('/api/v1/dashboard')
            ->assertStatus(401);
    }
}
