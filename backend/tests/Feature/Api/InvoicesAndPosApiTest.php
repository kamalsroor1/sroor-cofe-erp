<?php

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoicesAndPosApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $store;
    protected Customer $customer;
    protected Item $itemA;
    protected Item $itemB;
    protected CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);

        $role = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'invoices.view']);
        Permission::create(['name' => 'invoices.create']);
        Permission::create(['name' => 'invoices.cancel']);
        Permission::create(['name' => 'pos.access']);

        $this->store = Store::create([
            'name'      => 'الفرع الرئيسي',
            'code'      => 'MAIN-001',
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
            'name'            => 'كافيه الأهرام',
            'phone'           => '01098765432',
            'balance'         => '0.000',
            'price_tier'      => 'retail',
            'is_active'       => true,
        ]);

        $this->itemA = Item::create([
            'name'            => 'بن كولومبي وسط',
            'code'            => 'BN-COL-MED',
            'category'        => 'coffee_beans',
            'unit'            => 'كجم',
            'cost_price'      => '400.000',
            'selling_price'   => '550.000',
            'price_retail'    => '550.000',
            'price_wholesale' => '500.000',
            'current_stock'   => '50.000',
            'min_stock'       => '10.000',
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id'  => $this->itemA->id,
            'quantity' => '50.000',
        ]);

        $this->itemB = Item::create([
            'name'            => 'بن حبشي إسبريسو',
            'code'            => 'BN-ETH-ESP',
            'category'        => 'coffee_beans',
            'unit'            => 'كجم',
            'cost_price'      => '450.000',
            'selling_price'   => '650.000',
            'price_retail'    => '650.000',
            'price_wholesale' => '600.000',
            'current_stock'   => '30.000',
            'min_stock'       => '5.000',
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id'  => $this->itemB->id,
            'quantity' => '30.000',
        ]);

        $this->shift = CashShift::create([
            'user_id'              => $this->adminUser->id,
            'store_id'             => $this->store->id,
            'shift_number'         => 'SHF-260821-001',
            'status'               => 'open',
            'opened_at'            => now(),
            'opening_cash_balance' => '500.000',
        ]);
    }

    public function test_can_list_sales_invoices_with_summary(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/invoices');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
                'summary' => ['total_count', 'total_sales', 'total_paid', 'total_due'],
            ]);
    }

    public function test_can_create_sales_invoice_and_deduct_inventory(): void
    {
        $payload = [
            'customer_id'    => $this->customer->id,
            'store_id'       => $this->store->id,
            'invoice_date'   => now()->toDateString(),
            'payment_type'   => 'cash',
            'payment_method' => 'cash',
            'discount_type'  => 'fixed',
            'discount_value' => '50.000',
            'items'          => [
                [
                    'item_id'    => $this->itemA->id,
                    'quantity'   => 5.000,
                    'unit_price' => 550.000,
                ],
                [
                    'item_id'    => $this->itemB->id,
                    'quantity'   => 2.000,
                    'unit_price' => 650.000,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/invoices', $payload);

        // Subtotal = (5 * 550) + (2 * 650) = 2750 + 1300 = 4050
        // Net Total = 4050 - 50 = 4000
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'customer_id' => $this->customer->id,
                    'subtotal'    => 4050.000,
                    'net_total'   => 4000.000,
                    'paid_amount' => 4000.000,
                    'remaining_amount' => 0.000,
                    'status'      => 'confirmed',
                ],
            ]);

        // Stock deduction check
        $this->assertEquals(45.000, (float)Item::find($this->itemA->id)->current_stock);
        $this->assertEquals(28.000, (float)Item::find($this->itemB->id)->current_stock);
    }

    public function test_can_show_invoice_with_whatsapp_payload(): void
    {
        $payload = [
            'customer_id'    => $this->customer->id,
            'store_id'       => $this->store->id,
            'payment_type'   => 'cash',
            'items'          => [
                [
                    'item_id'    => $this->itemA->id,
                    'quantity'   => 1.000,
                    'unit_price' => 550.000,
                ],
            ],
        ];

        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/invoices', $payload);

        $invoiceId = $createResponse->json('data.id');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/invoices/' . $invoiceId);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'invoice_number',
                    'customer_name',
                    'items',
                ],
                'whatsapp' => [
                    'phone',
                    'clean_phone',
                    'message_text',
                    'whatsapp_url',
                ],
            ]);
    }

    public function test_can_cancel_sales_invoice_and_restore_inventory(): void
    {
        $payload = [
            'customer_id'    => $this->customer->id,
            'store_id'       => $this->store->id,
            'payment_type'   => 'cash',
            'items'          => [
                [
                    'item_id'    => $this->itemA->id,
                    'quantity'   => 10.000,
                    'unit_price' => 550.000,
                ],
            ],
        ];

        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/invoices', $payload);

        $invoiceId = $createResponse->json('data.id');

        // Stock decreased from 50 to 40
        $this->assertEquals(40.000, (float)Item::find($this->itemA->id)->current_stock);

        // Cancel
        $cancelResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/invoices/' . $invoiceId . '/cancel', [
                'reason' => 'إلغاء الطلب من العميل',
            ]);

        $cancelResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // Stock restored back to 50
        $this->assertEquals(50.000, (float)Item::find($this->itemA->id)->current_stock);
    }

    public function test_can_get_pos_bootstrap_data(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/pos/bootstrap');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'items',
                    'categories',
                    'customers',
                    'default_customer',
                    'active_store',
                    'active_shift',
                ],
            ]);
    }

    public function test_can_checkout_fast_pos_invoice(): void
    {
        $payload = [
            'customer_id'    => $this->customer->id,
            'store_id'       => $this->store->id,
            'invoice_date'   => now()->toDateString(),
            'payment_type'   => 'cash',
            'payment_method' => 'cash',
            'items'          => [
                [
                    'item_id'    => $this->itemA->id,
                    'quantity'   => 2.000,
                    'unit_price' => 550.000,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/pos/checkout', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'net_total'   => 1100.000,
                    'paid_amount' => 1100.000,
                    'status'      => 'confirmed',
                ],
            ]);
    }

    public function test_can_register_quick_customer_from_pos(): void
    {
        $payload = [
            'name'       => 'كافيه العروبة الجديد',
            'phone'      => '01112223334',
            'price_tier' => 'wholesale',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/pos/quick-customer', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success'  => true,
                'customer' => [
                    'name'       => 'كافيه العروبة الجديد',
                    'phone'      => '01112223334',
                    'price_tier' => 'wholesale',
                ],
            ]);

        $this->assertDatabaseHas('customers', [
            'name'  => 'كافيه العروبة الجديد',
            'phone' => '01112223334',
        ]);
    }
}
