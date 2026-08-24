<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Purchase;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuppliersApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected User $unauthorizedUser;
    protected string $unauthorizedToken;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->store = Store::create([
            'name'      => 'المخزن الرئيسي',
            'code'      => 'MAIN-001',
            'type'      => 'warehouse',
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
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $this->unauthorizedUser = User::factory()->create([
            'name'             => 'مستخدم بدون صلاحيات',
            'phone'            => '01000000000',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->unauthorizedToken = $this->unauthorizedUser->createToken('unauth-token')->plainTextToken;
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/suppliers');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_suppliers(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unauthorizedToken)
            ->getJson('/api/v1/suppliers');

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_list_suppliers_with_metrics(): void
    {
        Supplier::create([
            'name'            => 'شركة النيل للبن والمستلزمات',
            'company_name'    => 'النيل للاستيراد',
            'phone'           => '01011113333',
            'current_balance' => '5000.000',
            'is_active'       => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/suppliers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
                'summary' => ['total_payable', 'creditors_count', 'total_suppliers'],
            ])
            ->assertJson([
                'success' => true,
                'summary' => [
                    'creditors_count' => 1,
                    'total_suppliers' => 1,
                ],
            ]);
    }

    public function test_can_create_a_new_supplier_with_opening_balance(): void
    {
        $payload = [
            'name'            => 'مؤسسة البن البرازيلي',
            'company_name'    => 'البن البرازيلي ش.م.م',
            'phone'           => '01099881122',
            'address'         => 'ميناء الإسكندرية',
            'opening_balance' => '15000.000',
            'notes'           => 'مورد حبوب بن خضراء رئيسي',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/suppliers', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'name'            => 'مؤسسة البن البرازيلي',
                    'company_name'    => 'البن البرازيلي ش.م.م',
                    'current_balance' => 15000.000,
                ],
            ]);

        $this->assertDatabaseHas('suppliers', [
            'name'         => 'مؤسسة البن البرازيلي',
            'company_name' => 'البن البرازيلي ش.م.م',
        ]);
    }

    public function test_create_supplier_fails_validation_on_missing_name(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/suppliers', [
                'company_name' => 'شركة بدون اسم',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_view_single_supplier_profile(): void
    {
        $supplier = Supplier::create([
            'name'            => 'مطاحن الشرق',
            'company_name'    => 'الشرق لمعدات القهوة',
            'phone'           => '01044335566',
            'current_balance' => '3200.000',
            'is_active'       => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/suppliers/' . $supplier->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'              => $supplier->id,
                    'name'            => 'مطاحن الشرق',
                    'current_balance' => 3200.000,
                ],
            ]);
    }

    public function test_can_update_supplier_details(): void
    {
        $supplier = Supplier::create([
            'name'            => 'شركة الأهرام',
            'company_name'    => 'الأهرام للتوزيع',
            'phone'           => '01055556666',
            'current_balance' => '0.000',
            'is_active'       => true,
        ]);

        $payload = [
            'name'         => 'شركة الأهرام للتجارة والتوزيع',
            'company_name' => 'مجموعة الأهرام القابضة',
            'phone'        => '01055556666',
            'address'      => 'مدينة نصر، القاهرة',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson('/api/v1/suppliers/' . $supplier->id, $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'name'         => 'شركة الأهرام للتجارة والتوزيع',
                    'company_name' => 'مجموعة الأهرام القابضة',
                    'address'      => 'مدينة نصر، القاهرة',
                ],
            ]);

        $this->assertDatabaseHas('suppliers', [
            'id'   => $supplier->id,
            'name' => 'شركة الأهرام للتجارة والتوزيع',
        ]);
    }

    public function test_can_pay_supplier_and_decrease_balance(): void
    {
        $supplier = Supplier::create([
            'name'            => 'مورد سداد دفعة',
            'company_name'    => 'الشركة الحديثة',
            'phone'           => '01077779999',
            'current_balance' => '5000.000',
            'is_active'       => true,
        ]);

        Purchase::create([
            'store_id'        => $this->store->id,
            'supplier_id'     => $supplier->id,
            'user_id'         => $this->adminUser->id,
            'purchase_number' => 'PUR-1000',
            'purchase_date'   => now(),
            'subtotal'        => '5000.000',
            'discount_amount' => '0.000',
            'tax_amount'      => '0.000',
            'net_total'       => '5000.000',
            'paid_amount'     => '0.000',
            'remaining_amount'=> '5000.000',
            'payment_type'    => 'credit',
            'status'          => 'confirmed',
        ]);

        $payload = [
            'amount'         => '2000.000',
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
            'notes'          => 'سداد دفعة للمورد',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/suppliers/' . $supplier->id . '/pay', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'supplier' => [
                        'id'              => $supplier->id,
                        'current_balance' => 3000.000, // 5000 - 2000
                    ],
                ],
            ]);

        $this->assertEquals('3000.000', (string)$supplier->fresh()->current_balance);
        $this->assertDatabaseHas('payments', [
            'supplier_id' => $supplier->id,
            'amount'      => '2000.000',
        ]);
    }

    public function test_can_generate_supplier_account_statement_ledger(): void
    {
        $supplier = Supplier::create([
            'name'            => 'مورد كشف حساب',
            'company_name'    => 'المطاحن الكبرى',
            'phone'           => '01099991111',
            'current_balance' => '4000.000',
            'is_active'       => true,
        ]);

        Purchase::create([
            'store_id'        => $this->store->id,
            'supplier_id'     => $supplier->id,
            'user_id'         => $this->adminUser->id,
            'purchase_number' => 'PUR-1001',
            'purchase_date'   => now(),
            'subtotal'        => '4000.000',
            'discount_amount' => '0.000',
            'tax_amount'      => '0.000',
            'net_total'       => '4000.000',
            'paid_amount'     => '0.000',
            'remaining_amount'=> '4000.000',
            'payment_type'    => 'credit',
            'status'          => 'confirmed',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/suppliers/' . $supplier->id . '/statement');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'supplier' => ['id', 'name', 'current_balance'],
                    'summary'  => ['total_purchases', 'total_paid', 'current_balance', 'transactions_count'],
                    'ledger'   => [
                        '*' => ['date', 'type', 'ref_number', 'debit', 'credit', 'balance_after', 'notes'],
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data'    => [
                    'summary' => [
                        'total_purchases' => 4000.000,
                        'current_balance' => 4000.000,
                    ],
                ],
            ]);
    }

    public function test_can_toggle_supplier_active_status(): void
    {
        $supplier = Supplier::create([
            'name'            => 'مورد إيقاف',
            'company_name'    => 'شركة التوقف',
            'phone'           => '01088882222',
            'current_balance' => '0.000',
            'is_active'       => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->patchJson('/api/v1/suppliers/' . $supplier->id . '/toggle-active');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'is_active' => false,
                ],
            ]);

        $this->assertFalse((bool)$supplier->fresh()->is_active);
    }
}
