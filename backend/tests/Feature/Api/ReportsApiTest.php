<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $storeMain;
    protected Store $storeBranch;
    protected Customer $customer;
    protected Item $itemCoffee;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'reports.view']);
        Permission::create(['name' => 'reports.advanced']);

        $this->storeMain = Store::create([
            'name'      => 'المقر الرئيسي',
            'code'      => 'MAIN',
            'type'      => 'warehouse',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $this->storeBranch = Store::create([
            'name'      => 'فرع التجمع',
            'code'      => 'TAGAMO',
            'type'      => 'retail',
            'is_main'   => false,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->storeMain->id,
        ]);
        $this->adminUser->assignRole($role);
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;

        $this->customer = Customer::create([
            'name'            => 'كافيه العروبة',
            'phone'           => '01099887766',
            'current_balance' => '500.000',
            'balance'         => '500.000',
            'price_tier'      => 'retail',
            'is_active'       => true,
        ]);

        $this->itemCoffee = Item::create([
            'name'            => 'بن برازيلي كولومبي توليفة',
            'code'            => 'BN-MIX-01',
            'category'        => 'coffee_beans',
            'unit'            => 'كجم',
            'cost_price'      => '300.000',
            'selling_price'   => '500.000',
            'price_retail'    => '500.000',
            'price_wholesale' => '450.000',
            'current_stock'   => '80.000',
            'min_stock'       => '10.000',
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->storeMain->id,
            'item_id'  => $this->itemCoffee->id,
            'quantity' => '80.000',
        ]);

        // Create Confirmed Invoice
        $invoice = Invoice::create([
            'invoice_number'   => 'INV-TEST-001',
            'store_id'         => $this->storeMain->id,
            'customer_id'      => $this->customer->id,
            'user_id'          => $this->adminUser->id,
            'invoice_date'     => now()->toDateString(),
            'total_amount'     => '1000.000',
            'discount_amount'  => '0.000',
            'tax_amount'       => '0.000',
            'net_total'        => '1000.000',
            'paid_amount'      => '700.000',
            'remaining_amount' => '300.000',
            'total_cost'       => '600.000',
            'status'           => 'confirmed',
            'payment_method'   => 'cash',
        ]);

        InvoiceItem::create([
            'invoice_id'      => $invoice->id,
            'item_id'         => $this->itemCoffee->id,
            'quantity'        => '2.000',
            'unit_price'      => '500.000',
            'cost_price'      => '300.000',
            'discount_amount' => '0.000',
            'total_price'     => '1000.000',
        ]);

        // Create Operating Expense
        Expense::create([
            'expense_number' => 'EXP-2026-0001',
            'title'          => 'شراء مستلزمات ضيافة',
            'store_id'       => $this->storeMain->id,
            'user_id'        => $this->adminUser->id,
            'category'       => 'نثريات وضيافة',
            'amount'         => '150.000',
            'expense_date'   => now()->toDateString(),
            'notes'          => 'مشتريات أكواب وسكر',
        ]);
    }

    public function test_can_get_profit_and_loss_summary(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/reports/summary?preset=this_month');

        // Sales = 1000, COGS = 600, Gross Profit = 400, Expenses = 150, Net Profit = 250
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'summary' => [
                    'total_sales'    => 1000.0,
                    'total_cogs'     => 600.0,
                    'gross_profit'   => 400.0,
                    'total_expenses' => 150.0,
                    'net_profit'     => 250.0,
                    'invoices_count' => 1,
                    'total_paid'     => 700.0,
                    'total_remaining'=> 300.0,
                ],
            ]);
    }

    public function test_can_get_comprehensive_reports_bundle(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/reports/comprehensive?period=this_month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'period',
                'summary' => ['total_sales', 'total_cogs', 'gross_profit', 'net_profit', 'total_expenses'],
                'item_profits',
                'store_breakdown',
                'customer_sales',
                'expenses_breakdown',
                'inventory_data' => ['stock_cost_valuation', 'stock_selling_valuation', 'expected_stock_profit', 'items'],
                'treasury_data',
            ]);
    }

    public function test_can_get_items_profitability_report(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/reports/items?period=this_month');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    [
                        'item_id'       => $this->itemCoffee->id,
                        'total_qty'     => 2.0,
                        'total_revenue' => 1000.0,
                        'total_cogs'    => 600.0,
                        'profit'        => 400.0,
                        'margin'        => 40.0,
                    ],
                ],
            ]);
    }

    public function test_can_get_stores_comparative_report(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/reports/stores?period=this_month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'invoice_count', 'total_sales', 'gross_profit', 'margin', 'share_pct'],
                ],
            ]);
    }

    public function test_can_get_customers_sales_report(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/reports/customers?period=this_month');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    [
                        'customer_id'  => $this->customer->id,
                        'total_bought' => 1000.0,
                        'total_paid'   => 700.0,
                    ],
                ],
            ]);
    }

    public function test_can_get_expenses_breakdown_report(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/reports/expenses?period=this_month');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    [
                        'category' => 'نثريات وضيافة',
                        'amount'   => 150.0,
                        'count'    => 1,
                    ],
                ],
            ]);
    }

    public function test_can_get_inventory_valuation_report(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/reports/inventory?stock_filter=all');

        // 80 kg * 300 cost = 24000 | 80 kg * 500 sell = 40000 | profit = 16000
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'stock_cost_valuation'    => 24000.0,
                    'stock_selling_valuation' => 40000.0,
                    'expected_stock_profit'   => 16000.0,
                ],
            ]);
    }
}
