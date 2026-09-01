<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected User $unauthorizedUser;
    protected string $unauthorizedToken;
    protected Store $store;
    protected Customer $customer;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->store = Store::create([
            'name'      => 'الفرع الرئيسي',
            'code'      => 'MAIN-001',
            'type'      => 'retail',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($adminRole);
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;

        $this->unauthorizedUser = User::factory()->create([
            'name'             => 'مستخدم بدون صلاحيات',
            'phone'            => '01000000000',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->unauthorizedToken = $this->unauthorizedUser->createToken('unauth-token')->plainTextToken;

        $this->customer = Customer::create([
            'name'            => 'عميل تجريبي للسندات',
            'phone'           => '01011112222',
            'balance'         => '1500.000',
            'current_balance' => '1500.000',
            'is_active'       => true,
        ]);

        $this->supplier = Supplier::create([
            'name'            => 'مورد حبوب البن الفاخر',
            'phone'           => '01033334444',
            'balance'         => '5000.000',
            'current_balance' => '5000.000',
            'is_active'       => true,
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/payments');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_record_customer_receipt_or_supplier_voucher(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unauthorizedToken)
            ->postJson('/api/v1/payments/customer-receipt', [
                'customer_id' => $this->customer->id,
                'amount'      => 500,
            ]);

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_list_payments_with_summary(): void
    {
        Payment::create([
            'payment_number' => 'PAY-TEST-001',
            'customer_id'    => $this->customer->id,
            'user_id'        => $this->adminUser->id,
            'amount'         => '500.000',
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/payments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'summary' => [
                    'total_collections',
                    'total_disbursements',
                ],
                'data',
                'pagination' => [
                    'current_page',
                    'last_page',
                    'total',
                ],
            ]);

        $this->assertEquals(500.0, (float)$response->json('summary.total_collections'));
    }

    public function test_can_record_customer_receipt_and_update_balance(): void
    {
        $payload = [
            'customer_id'    => $this->customer->id,
            'amount'         => 500.0,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
            'notes'          => 'سداد دفعة نقدية',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/payments/customer-receipt', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'customer_id' => $this->customer->id,
                    'amount'      => '500.000',
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'customer_id' => $this->customer->id,
            'amount'      => '500.000',
        ]);
    }

    public function test_record_customer_receipt_fails_validation_on_missing_fields(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/payments/customer-receipt', [
                'amount' => 500,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_can_record_supplier_voucher_and_update_balance(): void
    {
        $payload = [
            'supplier_id'    => $this->supplier->id,
            'amount'         => 2000.0,
            'payment_method' => 'bank_transfer',
            'payment_date'   => now()->toDateString(),
            'notes'          => 'سداد تحويل بنكي للمورد',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/payments/supplier-voucher', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'supplier_id' => $this->supplier->id,
                    'amount'      => '2000.000',
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'supplier_id' => $this->supplier->id,
            'amount'      => '2000.000',
        ]);
    }

    public function test_record_supplier_voucher_fails_validation_on_missing_fields(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/payments/supplier-voucher', [
                'amount' => 1000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_id']);
    }
}
