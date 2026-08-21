<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomersApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'customers.manage']);

        $this->store = Store::create([
            'name'      => 'المخزن الرئيسي',
            'code'      => 'MAIN-001',
            'type'      => 'warehouse',
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
    }

    public function test_authenticated_user_can_list_customers_with_metrics(): void
    {
        Customer::create([
            'name'            => 'عميل تجريبي مدين',
            'phone'           => '01011112222',
            'current_balance' => '1500.000',
            'is_active'       => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/customers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
                'summary' => ['total_debt', 'debtors_count', 'total_customers'],
            ])
            ->assertJson([
                'success' => true,
                'summary' => [
                    'debtors_count'   => 1,
                    'total_customers' => 1,
                ],
            ]);
    }

    public function test_can_create_a_new_customer_with_opening_balance(): void
    {
        $payload = [
            'name'            => 'مطحن الأمل للبن',
            'phone'           => '01099887766',
            'address'         => 'وسط البلد، القاهرة',
            'tax_number'      => '123-456-789',
            'opening_balance' => '2500.000',
            'notes'           => 'عميل جملة',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/customers', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'name'            => 'مطحن الأمل للبن',
                    'phone'           => '01099887766',
                    'current_balance' => 2500.000,
                ],
            ]);

        $this->assertDatabaseHas('customers', [
            'name'  => 'مطحن الأمل للبن',
            'phone' => '01099887766',
        ]);
    }

    public function test_can_view_single_customer_profile(): void
    {
        $customer = Customer::create([
            'name'            => 'كافيه السلام',
            'phone'           => '01044332211',
            'current_balance' => '750.000',
            'is_active'       => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/customers/' . $customer->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'              => $customer->id,
                    'name'            => 'كافيه السلام',
                    'current_balance' => 750.000,
                ],
            ]);
    }

    public function test_can_update_customer_details(): void
    {
        $customer = Customer::create([
            'name'            => 'محل النور',
            'phone'           => '01055554444',
            'current_balance' => '0.000',
            'is_active'       => true,
        ]);

        $payload = [
            'name'    => 'محل النور للقهوة الفاخرة',
            'phone'   => '01055554444',
            'address' => 'ميدان التحرير',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson('/api/v1/customers/' . $customer->id, $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'name'    => 'محل النور للقهوة الفاخرة',
                    'address' => 'ميدان التحرير',
                ],
            ]);

        $this->assertDatabaseHas('customers', [
            'id'   => $customer->id,
            'name' => 'محل النور للقهوة الفاخرة',
        ]);
    }

    public function test_can_collect_customer_payment_and_decrease_balance(): void
    {
        $customer = Customer::create([
            'name'            => 'عميل سداد مديونية',
            'phone'           => '01077778888',
            'current_balance' => '1000.000',
            'is_active'       => true,
        ]);

        Invoice::create([
            'store_id'        => $this->store->id,
            'customer_id'     => $customer->id,
            'user_id'         => $this->adminUser->id,
            'invoice_number'  => 'INV-1000',
            'invoice_date'    => now(),
            'subtotal'        => 1000.000,
            'discount_amount' => 0.000,
            'tax_amount'      => 0.000,
            'net_total'       => 1000.000,
            'paid_amount'     => 0.000,
            'remaining_amount'=> 1000.000,
            'payment_type'    => 'credit',
            'status'          => 'confirmed',
        ]);

        $payload = [
            'amount'         => 400.000,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
            'notes'          => 'سداد دفعة نقدية',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/customers/' . $customer->id . '/collect-payment', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'customer' => [
                        'id'              => $customer->id,
                        'current_balance' => 600.000, // 1000 - 400
                    ],
                ],
            ]);

        $this->assertEquals('600.000', (string)$customer->fresh()->current_balance);
        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'amount'      => '400.000',
        ]);
    }

    public function test_can_generate_customer_account_statement_ledger(): void
    {
        $customer = Customer::create([
            'name'            => 'عميل كشف حساب',
            'phone'           => '01099990000',
            'current_balance' => '1200.000',
            'is_active'       => true,
        ]);

        // Create confirmed invoice
        Invoice::create([
            'store_id'        => $this->store->id,
            'customer_id'     => $customer->id,
            'user_id'         => $this->adminUser->id,
            'invoice_number'  => 'INV-1001',
            'invoice_date'    => now(),
            'subtotal'        => 1200.000,
            'discount_amount' => 0.000,
            'tax_amount'      => 0.000,
            'net_total'       => 1200.000,
            'paid_amount'     => 0.000,
            'remaining_amount'=> 1200.000,
            'payment_type'    => 'credit',
            'status'          => 'confirmed',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/customers/' . $customer->id . '/statement');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'customer' => ['id', 'name', 'current_balance'],
                    'summary'  => ['total_debit', 'total_credit', 'current_balance', 'transactions_count'],
                    'ledger'   => [
                        '*' => ['date', 'type', 'ref_number', 'debit', 'credit', 'balance_after', 'notes'],
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data'    => [
                    'summary' => [
                        'total_debit'     => 1200.000,
                        'current_balance' => 1200.000,
                    ],
                ],
            ]);
    }

    public function test_can_toggle_customer_active_status(): void
    {
        $customer = Customer::create([
            'name'            => 'عميل إيقاف',
            'phone'           => '01088889999',
            'current_balance' => '0.000',
            'is_active'       => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->patchJson('/api/v1/customers/' . $customer->id . '/toggle-active');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'is_active' => false,
                ],
            ]);

        $this->assertFalse((bool)$customer->fresh()->is_active);
    }
}
