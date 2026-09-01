<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $mainStore;
    protected Store $branchStore;
    protected Customer $customer;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->mainStore = Store::create([
            'name'      => 'المحمصة المركزية',
            'code'      => 'ROAST-MAIN',
            'type'      => 'retail',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $this->branchStore = Store::create([
            'name'      => 'فرع المعادي',
            'code'      => 'ROAST-MAADI',
            'type'      => 'branch',
            'is_main'   => false,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $this->adminUser->assignRole($adminRole);
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;

        $this->customer = Customer::create([
            'name'            => 'عميل مميز للداشبورد',
            'phone'           => '01099998888',
            'current_balance' => '750.000',
            'is_active'       => true,
        ]);

        $this->item = Item::create([
            'name'            => 'بن إثيوبي هرري',
            'code'            => 'BN-ETH-HAR',
            'category'        => 'coffee_beans',
            'unit'            => 'كجم',
            'cost_price'      => '400.000',
            'selling_price'   => '650.000',
            'price_retail'    => '650.000',
            'price_wholesale' => '600.000',
            'current_stock'   => '4.000', // Low stock alert trigger
            'min_stock'       => '15.000',
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->mainStore->id,
            'item_id'  => $this->item->id,
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
            'invoice_number'   => 'INV-DASH-001',
            'store_id'         => $this->mainStore->id,
            'customer_id'      => $this->customer->id,
            'user_id'          => $this->adminUser->id,
            'invoice_date'     => $today,
            'subtotal'         => '1300.000',
            'discount_amount'  => '0.000',
            'tax_amount'       => '0.000',
            'net_total'        => '1300.000',
            'paid_amount'      => '1300.000',
            'remaining_amount' => '0.000',
            'status'           => 'confirmed',
            'payment_type'     => 'cash',
        ]);

        InvoiceItem::create([
            'invoice_id'      => $invoice->id,
            'item_id'         => $this->item->id,
            'quantity'        => '2.000',
            'unit_price'      => '650.000',
            'cost_price'      => '400.000',
            'unit_cost'       => '400.000',
            'total_price'     => '1300.000',
            'discount_amount' => '0.000',
            'tax_amount'      => '0.000',
            'net_price'       => '1300.000',
        ]);

        // 2. Active Shift
        CashShift::create([
            'user_id'              => $this->adminUser->id,
            'store_id'             => $this->mainStore->id,
            'shift_number'         => 'SHF-DASH-01',
            'status'               => 'open',
            'opened_at'            => now(),
            'opening_cash_balance' => '1000.000',
        ]);

        // 3. Expense
        Expense::create([
            'store_id'       => $this->mainStore->id,
            'user_id'        => $this->adminUser->id,
            'expense_number' => 'EXP-DASH-01',
            'title'          => 'فواتير تشغيل',
            'amount'         => '200.000',
            'category'       => 'تشغيلي',
            'cost_center'    => 'فرع رئيسي',
            'expense_date'   => $today,
            'payment_method' => 'cash',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
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

        $this->assertEquals(1300.0, (float)$response->json('data.metrics.today_sales'));
    }

    public function test_dashboard_respects_x_store_id_header(): void
    {
        $today = now()->toDateString();

        // Create invoice on branch store
        Invoice::create([
            'invoice_number'   => 'INV-MAADI-001',
            'store_id'         => $this->branchStore->id,
            'customer_id'      => $this->customer->id,
            'user_id'          => $this->adminUser->id,
            'invoice_date'     => $today,
            'subtotal'         => '2500.000',
            'discount_amount'  => '0.000',
            'tax_amount'       => '0.000',
            'net_total'        => '2500.000',
            'paid_amount'      => '2500.000',
            'remaining_amount' => '0.000',
            'status'           => 'confirmed',
            'payment_type'     => 'cash',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'X-Store-Id'    => (string) $this->branchStore->id,
        ])->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $this->assertEquals(2500.0, (float)$response->json('data.metrics.today_sales'));
    }

    public function test_dashboard_low_stock_alerts_detected_correctly(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $lowStockItems = $response->json('data.low_stock_items');
        $this->assertNotEmpty($lowStockItems);
        $this->assertEquals('بن إثيوبي هرري', $lowStockItems[0]['name']);
    }
}
