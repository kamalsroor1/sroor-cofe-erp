<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Expense;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpensesApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected User $unauthorizedUser;
    protected string $unauthorizedToken;
    protected Store $mainStore;
    protected Store $branchStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->mainStore = Store::create([
            'name'      => 'المخزن الرئيسي',
            'code'      => 'MAIN-001',
            'type'      => 'warehouse',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $this->branchStore = Store::create([
            'name'      => 'فرع المعادي',
            'code'      => 'MAADI-001',
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

        $this->unauthorizedUser = User::factory()->create([
            'name'             => 'مستخدم بدون صلاحيات',
            'phone'            => '01000000000',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $this->unauthorizedToken = $this->unauthorizedUser->createToken('unauth-token')->plainTextToken;
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/expenses');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_create_expense(): void
    {
        $payload = [
            'title'          => 'مصروف ممنوع',
            'category'       => 'تشغيلي',
            'cost_center'    => 'operational',
            'amount'         => 100.0,
            'expense_date'   => now()->toDateString(),
            'payment_method' => 'cash',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unauthorizedToken)
            ->postJson('/api/v1/expenses', $payload);

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_list_expenses_with_summary(): void
    {
        Expense::create([
            'expense_number' => 'EXP-260821-0001',
            'title'          => 'فاتورة كهرباء فرع رئيسي',
            'category'       => 'كهرباء ومياه ومرافق',
            'cost_center'    => 'utilities',
            'amount'         => '1250.000',
            'expense_date'   => now()->toDateString(),
            'payment_method' => 'cash',
            'user_id'        => $this->adminUser->id,
            'store_id'       => $this->mainStore->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/expenses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
                'summary' => ['total_month', 'total_cash', 'total_filtered', 'count_filtered'],
                'cost_centers',
                'quick_categories',
            ])
            ->assertJson([
                'success' => true,
                'summary' => [
                    'count_filtered' => 1,
                ],
            ]);
    }

    public function test_can_create_a_new_expense_with_sequential_number(): void
    {
        $payload = [
            'title'          => 'شراء أكياس تعبئة بن وكراتين',
            'category'       => 'شنط وأكياس وتغليف',
            'cost_center'    => 'packaging',
            'amount'         => 850.500,
            'expense_date'   => now()->toDateString(),
            'payment_method' => 'cash',
            'notes'          => 'مطبوعات لوجو المحل',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/expenses', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'title'          => 'شراء أكياس تعبئة بن وكراتين',
                    'category'       => 'شنط وأكياس وتغليف',
                    'cost_center'    => 'packaging',
                    'amount'         => 850.500,
                    'payment_method' => 'cash',
                ],
            ]);

        $this->assertDatabaseHas('expenses', [
            'title'       => 'شراء أكياس تعبئة بن وكراتين',
            'cost_center' => 'packaging',
        ]);
    }

    public function test_create_expense_fails_validation_on_missing_fields(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/expenses', [
                'title' => 'ناقص بيانات',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category', 'cost_center', 'amount', 'expense_date', 'payment_method']);
    }

    public function test_can_view_single_expense_details(): void
    {
        $expense = Expense::create([
            'expense_number' => 'EXP-260821-0002',
            'title'          => 'صيانة ماكينة الإسبريسو',
            'category'       => 'صيانة معدات',
            'cost_center'    => 'maintenance',
            'amount'         => '600.000',
            'expense_date'   => now()->toDateString(),
            'payment_method' => 'visa',
            'user_id'        => $this->adminUser->id,
            'store_id'       => $this->mainStore->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/expenses/' . $expense->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'             => $expense->id,
                    'expense_number' => 'EXP-260821-0002',
                    'title'          => 'صيانة ماكينة الإسبريسو',
                    'amount'         => 600.000,
                ],
            ]);
    }

    public function test_can_update_expense_details(): void
    {
        $expense = Expense::create([
            'expense_number' => 'EXP-260821-0003',
            'title'          => 'نثريات',
            'category'       => 'نثريات',
            'cost_center'    => 'operational',
            'amount'         => '100.000',
            'expense_date'   => now()->toDateString(),
            'payment_method' => 'cash',
            'user_id'        => $this->adminUser->id,
            'store_id'       => $this->mainStore->id,
        ]);

        $payload = [
            'title'          => 'نثريات وضيافة عملاء',
            'category'       => 'ضيافة وبوفيه',
            'cost_center'    => 'hospitality',
            'amount'         => 150.000,
            'expense_date'   => now()->toDateString(),
            'payment_method' => 'instapay',
            'notes'          => 'تحديث الوصف',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson('/api/v1/expenses/' . $expense->id, $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'title'          => 'نثريات وضيافة عملاء',
                    'category'       => 'ضيافة وبوفيه',
                    'cost_center'    => 'hospitality',
                    'amount'         => 150.000,
                    'payment_method' => 'instapay',
                ],
            ]);

        $this->assertDatabaseHas('expenses', [
            'id'    => $expense->id,
            'title' => 'نثريات وضيافة عملاء',
        ]);
    }

    public function test_can_delete_expense(): void
    {
        $expense = Expense::create([
            'expense_number' => 'EXP-260821-0004',
            'title'          => 'مصروف ملغي',
            'category'       => 'نثريات',
            'cost_center'    => 'operational',
            'amount'         => '50.000',
            'expense_date'   => now()->toDateString(),
            'payment_method' => 'cash',
            'user_id'        => $this->adminUser->id,
            'store_id'       => $this->mainStore->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/expenses/' . $expense->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertSoftDeleted('expenses', [
            'id' => $expense->id,
        ]);
    }
}
