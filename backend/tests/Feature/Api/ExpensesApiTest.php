<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Expense;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class ExpensesApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected int $mainStoreId;

    protected int $branchStoreId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->mainStoreId = (int) $this->tenantStore($this->tenant)->id;
        $this->branchStoreId = $this->inTenant($this->tenant, fn (): int => Store::create([
            'name' => 'فرع المعادي',
            'code' => 'MAADI-001',
            'type' => 'branch',
            'is_main' => false,
            'is_active' => true,
        ])->id);

        $this->adminUser = $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: ['name' => 'مستخدم بدون صلاحيات']);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/expenses', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_create_expense(): void
    {
        $payload = [
            'title' => 'مصروف ممنوع',
            'category' => 'تشغيلي',
            'cost_center' => 'operational',
            'amount' => 100.0,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ];

        $response = $this->postJson('/api/v1/expenses', $payload, $this->unauthorizedHeaders);

        $response->assertStatus(403);
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Expense::query()->count()));
    }

    public function test_authenticated_user_can_list_expenses_with_summary(): void
    {
        $this->inTenant($this->tenant, fn () => $this->makeExpense('EXP-260821-0001', [
            'title' => 'فاتورة كهرباء فرع رئيسي',
            'category' => 'كهرباء ومياه ومرافق',
            'cost_center' => 'utilities',
            'amount' => '1250.000',
        ]));

        $response = $this->getJson('/api/v1/expenses', $this->adminHeaders);

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
            'title' => 'شراء أكياس تعبئة بن وكراتين',
            'category' => 'شنط وأكياس وتغليف',
            'cost_center' => 'packaging',
            'amount' => 850.500,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'notes' => 'مطبوعات لوجو المحل',
        ];

        $response = $this->postJson('/api/v1/expenses', $payload, $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'شراء أكياس تعبئة بن وكراتين',
                    'category' => 'شنط وأكياس وتغليف',
                    'cost_center' => 'packaging',
                    'amount' => 850.500,
                    'payment_method' => 'cash',
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('expenses', [
            'title' => 'شراء أكياس تعبئة بن وكراتين',
            'cost_center' => 'packaging',
        ]));
    }

    public function test_create_expense_fails_validation_on_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/expenses', [
            'title' => 'ناقص بيانات',
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category', 'cost_center', 'amount', 'expense_date', 'payment_method']);
    }

    public function test_can_view_single_expense_details(): void
    {
        $expenseId = $this->inTenant($this->tenant, fn (): int => $this->makeExpense('EXP-260821-0002', [
            'title' => 'صيانة ماكينة الإسبريسو',
            'category' => 'صيانة معدات',
            'cost_center' => 'maintenance',
            'amount' => '600.000',
            'payment_method' => 'visa',
        ])->id);

        $response = $this->getJson('/api/v1/expenses/'.$expenseId, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $expenseId,
                    'expense_number' => 'EXP-260821-0002',
                    'title' => 'صيانة ماكينة الإسبريسو',
                    'amount' => 600.000,
                ],
            ]);
    }

    public function test_can_update_expense_details(): void
    {
        $expenseId = $this->inTenant($this->tenant, fn (): int => $this->makeExpense('EXP-260821-0003', [
            'title' => 'نثريات',
            'category' => 'نثريات',
            'cost_center' => 'operational',
            'amount' => '100.000',
        ])->id);

        $payload = [
            'title' => 'نثريات وضيافة عملاء',
            'category' => 'ضيافة وبوفيه',
            'cost_center' => 'hospitality',
            'amount' => 150.000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'instapay',
            'notes' => 'تحديث الوصف',
        ];

        $response = $this->putJson('/api/v1/expenses/'.$expenseId, $payload, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'نثريات وضيافة عملاء',
                    'category' => 'ضيافة وبوفيه',
                    'cost_center' => 'hospitality',
                    'amount' => 150.000,
                    'payment_method' => 'instapay',
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('expenses', [
            'id' => $expenseId,
            'title' => 'نثريات وضيافة عملاء',
        ]));
    }

    public function test_can_delete_expense(): void
    {
        $expenseId = $this->inTenant($this->tenant, fn (): int => $this->makeExpense('EXP-260821-0004', [
            'title' => 'مصروف ملغي',
            'category' => 'نثريات',
            'cost_center' => 'operational',
            'amount' => '50.000',
        ])->id);

        $response = $this->deleteJson('/api/v1/expenses/'.$expenseId, [], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertSoftDeleted('expenses', [
            'id' => $expenseId,
        ]));
    }

    public function test_expenses_of_another_tenant_are_invisible_and_immutable(): void
    {
        $other = $this->createTenant();
        $otherStoreId = (int) $this->tenantStore($other)->id;
        $otherAdminId = (int) $this->tenantAdmin($other)->id;
        $foreignId = $this->inTenant($other, fn (): int => Expense::create([
            'expense_number' => 'EXP-260821-0099',
            'title' => 'مصروف مستأجر آخر',
            'category' => 'نثريات',
            'cost_center' => 'operational',
            'amount' => '75.000',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'user_id' => $otherAdminId,
            'store_id' => $otherStoreId,
        ])->id);

        $this->getJson('/api/v1/expenses', $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('summary.count_filtered', 0)
            ->assertJsonMissing(['title' => 'مصروف مستأجر آخر']);

        $this->getJson('/api/v1/expenses/'.$foreignId, $this->adminHeaders)->assertNotFound();
        $this->putJson('/api/v1/expenses/'.$foreignId, [
            'title' => 'اختراق',
            'category' => 'نثريات',
            'cost_center' => 'operational',
            'amount' => 1.000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ], $this->adminHeaders)->assertNotFound();
        $this->deleteJson('/api/v1/expenses/'.$foreignId, [], $this->adminHeaders)->assertNotFound();

        $this->inTenant($other, fn () => $this->assertDatabaseHas('expenses', [
            'id' => $foreignId,
            'title' => 'مصروف مستأجر آخر',
            'amount' => '75.000',
            'deleted_at' => null,
        ]));
    }

    /**
     * Runs inside the tenant.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function makeExpense(string $number, array $attributes): Expense
    {
        return Expense::create(array_merge([
            'expense_number' => $number,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStoreId,
        ], $attributes));
    }
}
