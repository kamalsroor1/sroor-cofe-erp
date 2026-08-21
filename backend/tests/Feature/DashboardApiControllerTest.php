<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Store;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Item;
use App\Models\Invoice;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class DashboardApiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->store = Store::create([
            'name'      => 'المقر الرئيسي',
            'code'      => 'MAIN-01',
            'type'      => 'retail_shop',
            'is_active' => true,
            'is_main'   => true,
        ]);

        $this->user = User::factory()->create([
            'name'             => 'مدير الفرع',
            'phone'            => '01099887766',
            'default_store_id' => $this->store->id,
            'is_active'        => true,
        ]);
        $this->user->assignRole($role);
    }

    public function test_dashboard_api_returns_structured_resource_data(): void
    {
        $this->actingAs($this->user);

        // 1. Customer & Supplier with balances
        $customer = Customer::create([
            'name'            => 'عميل تجريبي',
            'phone'           => '01011111111',
            'current_balance' => '1500.000',
            'is_active'       => true,
        ]);

        Supplier::create([
            'name'            => 'مورد تجريبي',
            'phone'           => '01022222222',
            'current_balance' => '700.000',
            'is_active'       => true,
        ]);

        // 2. Item & Invoice
        $item = Item::create([
            'name'          => 'بن برازيلي فاخر',
            'code'          => 'BRZ-01',
            'unit'          => 'كجم',
            'cost_price'    => '100.000',
            'selling_price' => '160.000',
            'current_stock' => '2.000',
            'min_stock_level' => '10.000',
            'is_active'     => true,
        ]);

        $invoice = Invoice::create([
            'invoice_number'   => 'INV-API-001',
            'customer_id'      => $customer->id,
            'store_id'         => $this->store->id,
            'user_id'          => $this->user->id,
            'invoice_date'     => now()->toDateString(),
            'subtotal'         => '320.000',
            'total_amount'     => '320.000',
            'net_total'        => '320.000',
            'paid_amount'      => '320.000',
            'remaining_amount' => '0.000',
            'status'           => 'confirmed',
            'payment_type'     => 'cash',
        ]);

        $invoice->items()->create([
            'item_id'     => $item->id,
            'quantity'    => '2.000',
            'unit_price'  => '160.000',
            'cost_price'  => '100.000',
            'total_price' => '320.000',
        ]);

        // 3. Shift
        CashShift::create([
            'store_id'             => $this->store->id,
            'user_id'              => $this->user->id,
            'shift_number'         => 1,
            'status'               => 'open',
            'opening_cash_balance' => '500.000',
            'opened_at'            => now(),
        ]);

        // 4. Activity Log
        ActivityLog::create([
            'user_id'     => $this->user->id,
            'store_id'    => $this->store->id,
            'module'      => 'invoices',
            'action'      => 'create',
            'description' => 'إنشاء فاتورة تجريبية',
            'ip_address'  => '127.0.0.1',
        ]);

        // Call Action & Resource
        $action = app(\App\Actions\Dashboard\GetDashboardApiOverviewAction::class);
        $data = $action->execute($this->store->id);
        $resource = new \App\Http\Resources\Api\DashboardOverviewResource($data);
        $response = $resource->response()->getData(true);

        $this->assertTrue($response['data']['success']);
        $this->assertEquals(1, $response['data']['customers_count']);
        $this->assertEquals(1, $response['data']['suppliers_count']);
        $this->assertEquals('1500.000', $response['data']['total_receivable']);
        $this->assertEquals('700.000', $response['data']['total_payable']);
        $this->assertEquals('320.000', $response['data']['today_metrics']['net_sales']);
        $this->assertEquals('200.000', $response['data']['today_metrics']['total_cogs']); // 2 * 100 = 200
        $this->assertEquals('120.000', $response['data']['today_metrics']['net_profit']); // 320 - 200 = 120
        $this->assertTrue($response['data']['has_active_shift']);
        $this->assertEquals(1, $response['data']['low_stock_count']);
        $this->assertCount(1, $response['data']['recent_invoices']);
        $this->assertCount(1, $response['data']['recent_logs']);
    }
}
