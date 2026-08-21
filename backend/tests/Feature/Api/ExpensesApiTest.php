<?php

namespace Tests\Feature\Api;

use App\Models\Expense;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpensesApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'expenses.manage']);

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
            'store_id'       => $this->store->id,
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
            'amount'         => '850.500',
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
            'store_id'       => $this->store->id,
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
            'store_id'       => $this->store->id,
        ]);

        $payload = [
            'title'          => 'نثريات وضيافة عملاء',
            'category'       => 'ضيافة وبوفيه',
            'cost_center'    => 'hospitality',
            'amount'         => '150.000',
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
            'store_id'       => $this->store->id,
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
