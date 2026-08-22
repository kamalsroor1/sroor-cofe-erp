<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $store;
    protected Customer $customer;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin']);

        $this->store = Store::create([
            'name'      => 'المحمصة المركزية',
            'code'      => 'ROAST-MAIN',
            'type'      => 'retail',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($role);
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;

        $this->customer = Customer::create([
            'name'            => 'عميل مميز للداشبورد',
            'phone'           => '01099998888',
            'current_balance' => '750.000',
            'balance'         => '750.000',
            'price_tier'      => 'retail',
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
            'current_stock'   => '4.000', // Low stock!
            'min_stock'       => '15.000',
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id'  => $this->item->id,
            'quantity' => '4.000',
        ]);

        // Create a confirmed invoice today
        $invoice = Invoice::create([
            'invoice_number'   => 'INV-DASH-001',
            'store_id'         => $this->store->id,
            'customer_id'      => $this->customer->id,
            'user_id'          => $this->adminUser->id,
            'invoice_date'     => now()->toDateString(),
            'total_amount'     => '1300.000',
            'discount_amount'  => '0.000',
            'tax_amount'       => '0.000',
            'net_total'        => '1300.000',
            'paid_amount'      => '1300.000',
            'remaining_amount' => '0.000',
            'total_cost'       => '800.000',
            'status'           => 'confirmed',
            'payment_type'     => 'cash',
            'payment_method'   => 'cash',
        ]);

        InvoiceItem::create([
            'invoice_id'      => $invoice->id,
            'item_id'         => $this->item->id,
            'quantity'        => '2.000',
            'unit_price'      => '650.000',
            'cost_price'      => '400.000',
            'discount_amount' => '0.000',
            'total_price'     => '1300.000',
        ]);
    }

    public function test_can_fetch_dashboard_summary_and_analytics(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/dashboard/summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'metrics' => [
                        'today_sales'          => 1300.0,
                        'today_invoices_count' => 1,
                        'cash_sales'           => 1300.0,
                        'customers_debt'       => 750.0,
                        'low_stock_count'      => 1,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'active_store',
                    'metrics' => [
                        'today_sales',
                        'today_invoices_count',
                        'cash_sales',
                        'credit_sales',
                        'total_cash_collected',
                        'today_expenses',
                        'net_cash_today',
                        'customers_debt',
                        'monthly_sales',
                        'monthly_gross_profit',
                        'monthly_margin',
                        'low_stock_count',
                    ],
                    'analytics',
                    'low_stock_items',
                    'top_selling_items',
                    'recent_invoices',
                ],
            ]);
    }
}
