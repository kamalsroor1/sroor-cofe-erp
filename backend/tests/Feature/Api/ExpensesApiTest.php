<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Expense;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
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

    /**
     * Trello #221: every tenant (any retail vertical) gets neutral suggested categories,
     * not coffee-shop ones (cups, grinder maintenance, buffet).
     */
    public function test_quick_categories_are_neutral_retail_suggestions(): void
    {
        $expected = array_map(
            static fn (string $key): string => __('expenses.quick_categories.'.$key),
            ['rent', 'utilities', 'salaries', 'maintenance', 'shipping', 'bags', 'office_supplies', 'marketing', 'hospitality', 'other'],
        );

        $categories = $this->getJson('/api/v1/expenses', $this->adminHeaders)
            ->assertStatus(200)
            ->json('quick_categories');

        $this->assertSame($expected, $categories);

        foreach (['ar', 'en'] as $locale) {
            foreach ((array) trans('expenses.quick_categories', [], $locale) as $label) {
                $this->assertDoesNotMatchRegularExpression('/أكواب|مطاحن|بوفيه|بن |قهوة|cups|grinder|coffee/iu', (string) $label);
            }
        }
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

    public function test_expense_creation_with_same_idempotency_key_is_idempotent(): void
    {
        $key = (string) Str::uuid();
        $headers = array_merge($this->adminHeaders, ['Idempotency-Key' => $key]);
        $payload = [
            'title' => 'مصروف إيداع مكرر',
            'category' => 'تشغيلي',
            'cost_center' => 'operational',
            'amount' => 250.000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ];

        $first = $this->postJson('/api/v1/expenses', $payload, $headers);
        $first->assertStatus(201);
        $expenseId = (int) $first->json('data.id');
        $expenseNumber = (string) $first->json('data.expense_number');

        $second = $this->postJson('/api/v1/expenses', $payload, $headers);
        $second->assertStatus(200)
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.id', $expenseId)
            ->assertJsonPath('data.expense_number', $expenseNumber);

        $this->inTenant($this->tenant, fn () => $this->assertSame(1, Expense::query()->count()));

        // A new Idempotency-Key creates a new expense.
        $third = $this->postJson('/api/v1/expenses', $payload, array_merge($this->adminHeaders, [
            'Idempotency-Key' => (string) Str::uuid(),
        ]));
        $third->assertStatus(201);
        $this->assertNotSame($expenseId, (int) $third->json('data.id'));
        $this->inTenant($this->tenant, fn () => $this->assertSame(2, Expense::query()->count()));

        // Reusing the first key on another store or by another user is rejected (422).
        $branchHeaders = array_merge($this->adminHeaders, [
            'X-Store-Id' => (string) $this->branchStoreId,
            'Idempotency-Key' => $key,
        ]);
        $this->postJson('/api/v1/expenses', $payload, $branchHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_uuid']);

        $otherManager = $this->createTenantUser($this->tenant, null, ['expenses.manage']);
        $otherHeaders = array_merge($this->tenantHeaders($this->tenant, $otherManager), [
            'Idempotency-Key' => $key,
        ]);
        $this->postJson('/api/v1/expenses', $payload, $otherHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_uuid']);
    }

    public function test_accountant_cannot_create_expense_on_unauthorized_store_via_body_store_id(): void
    {
        $accountant = $this->createTenantUser($this->tenant, 'accountant');
        $headersWithStore = $this->tenantHeaders($this->tenant, $accountant, $this->mainStoreId);
        $headersWithoutStore = $headersWithStore;
        unset($headersWithoutStore['X-Store-Id']);

        $payload = [
            'title' => 'مصروف فرع غير مصرح',
            'category' => 'نثريات',
            'cost_center' => 'operational',
            'amount' => 120.000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'store_id' => $this->branchStoreId,
        ];

        foreach ([$headersWithStore, $headersWithoutStore] as $headers) {
            $this->postJson('/api/v1/expenses', $payload, $headers)
                ->assertStatus(403)
                ->assertJsonPath('success', false)
                ->assertJsonPath('error_code', 'store_access_denied');
        }

        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Expense::query()->count()));
    }

    public function test_tenant_b_cannot_view_update_or_delete_tenant_a_expense_by_real_id(): void
    {
        $tenantAFirstId = $this->inTenant($this->tenant, fn (): int => $this->makeExpense('EXP-A-001', [
            'title' => 'مصروف متجر أ الأول',
            'category' => 'نثريات',
            'cost_center' => 'operational',
            'amount' => '100.000',
        ])->id);
        $tenantASecondId = $this->inTenant($this->tenant, fn (): int => $this->makeExpense('EXP-A-002', [
            'title' => 'مصروف متجر أ الثاني',
            'category' => 'مرافق',
            'cost_center' => 'utilities',
            'amount' => '450.000',
        ])->id);

        $tenantB = $this->createTenant();
        $tenantBHeaders = $this->tenantHeaders($tenantB);
        $tenantBStoreId = (int) $this->tenantStore($tenantB)->id;
        $tenantBAdminId = (int) $this->tenantAdmin($tenantB)->id;
        $tenantBFirstId = $this->inTenant($tenantB, fn (): int => Expense::create([
            'expense_number' => 'EXP-B-001',
            'title' => 'مصروف متجر ب الأول',
            'category' => 'نثريات',
            'cost_center' => 'operational',
            'amount' => '50.000',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'user_id' => $tenantBAdminId,
            'store_id' => $tenantBStoreId,
        ])->id);

        // Tenant B gets 404 on GET/PUT/DELETE for every real expense ID belonging to Tenant A.
        foreach ([$tenantAFirstId, $tenantASecondId] as $foreignId) {
            $this->getJson('/api/v1/expenses/'.$foreignId, $tenantBHeaders)->assertNotFound();
            $this->putJson('/api/v1/expenses/'.$foreignId, [
                'title' => 'تعديل غير مصرح',
                'category' => 'نثريات',
                'cost_center' => 'operational',
                'amount' => 1.000,
                'expense_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ], $tenantBHeaders)->assertNotFound();
            $this->deleteJson('/api/v1/expenses/'.$foreignId, [], $tenantBHeaders)->assertNotFound();
        }

        // Tenant B reads its own expense (200), while Tenant A gets 404 for Tenant B's ID.
        $this->getJson('/api/v1/expenses/'.$tenantBFirstId, $tenantBHeaders)
            ->assertOk()
            ->assertJsonPath('data.id', $tenantBFirstId)
            ->assertJsonPath('data.title', 'مصروف متجر ب الأول');
        $this->getJson('/api/v1/expenses/'.$tenantBFirstId, $this->adminHeaders)->assertNotFound();

        $this->inTenant($this->tenant, function () use ($tenantAFirstId, $tenantASecondId): void {
            $this->assertDatabaseHas('expenses', [
                'id' => $tenantAFirstId,
                'title' => 'مصروف متجر أ الأول',
                'amount' => '100.000',
                'deleted_at' => null,
            ]);
            $this->assertDatabaseHas('expenses', [
                'id' => $tenantASecondId,
                'title' => 'مصروف متجر أ الثاني',
                'amount' => '450.000',
                'deleted_at' => null,
            ]);
        });
    }

    public function test_accountant_can_list_view_create_update_and_delete_expenses_in_allowed_store(): void
    {
        $adminMainId = $this->inTenant($this->tenant, fn (): int => $this->makeExpense('EXP-MAIN-001', [
            'title' => 'مصروف فرع رئيسي للأدمن',
            'category' => 'نثريات',
            'cost_center' => 'operational',
            'amount' => '200.000',
        ])->id);
        $adminBranchId = $this->inTenant($this->tenant, fn (): int => $this->makeExpense('EXP-BR-001', [
            'title' => 'مصروف فرع المعادي',
            'category' => 'إيجار',
            'cost_center' => 'rent',
            'amount' => '900.000',
            'store_id' => $this->branchStoreId,
        ])->id);

        $accountant = $this->createTenantUser($this->tenant, 'accountant');
        $headers = $this->tenantHeaders($this->tenant, $accountant, $this->mainStoreId);

        // List: sees allowed store expenses, never unauthorized branch expenses.
        $list = $this->getJson('/api/v1/expenses', $headers)->assertOk();
        $this->assertSame([$adminMainId], array_column($list->json('data'), 'id'));

        // Create
        $created = $this->postJson('/api/v1/expenses', [
            'title' => 'مصروف محاسب جديد',
            'category' => 'مرافق',
            'cost_center' => 'utilities',
            'amount' => 325.500,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'notes' => 'فاتورة مياه',
        ], $headers)->assertStatus(201);
        $createdId = (int) $created->json('data.id');

        // View own created + admin's expense in allowed store
        $this->getJson('/api/v1/expenses/'.$createdId, $headers)
            ->assertOk()
            ->assertJsonPath('data.title', 'مصروف محاسب جديد');
        $this->getJson('/api/v1/expenses/'.$adminMainId, $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $adminMainId);

        // Update
        $this->putJson('/api/v1/expenses/'.$createdId, [
            'title' => 'مصروف محاسب معدل',
            'category' => 'مرافق',
            'cost_center' => 'utilities',
            'amount' => 400.000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'notes' => 'تم التعديل',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.title', 'مصروف محاسب معدل')
            ->assertJsonPath('data.payment_method', 'bank_transfer');

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('expenses', [
            'id' => $createdId,
            'title' => 'مصروف محاسب معدل',
            'amount' => '400.000',
        ]));

        // Delete
        $this->deleteJson('/api/v1/expenses/'.$createdId, [], $headers)->assertOk();
        $this->inTenant($this->tenant, fn () => $this->assertSoftDeleted('expenses', ['id' => $createdId]));

        // Unauthorized branch expense stays invisible and immutable (404)
        $this->getJson('/api/v1/expenses/'.$adminBranchId, $headers)->assertNotFound();
        $this->putJson('/api/v1/expenses/'.$adminBranchId, [
            'title' => 'تعديل فرع آخر',
            'category' => 'إيجار',
            'cost_center' => 'rent',
            'amount' => 1.000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ], $headers)->assertNotFound();
        $this->deleteJson('/api/v1/expenses/'.$adminBranchId, [], $headers)->assertNotFound();
    }

    public function test_cashier_without_expenses_manage_cannot_mutate_expenses_and_non_journal_user_gets_403_on_index(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $cashierHeaders = $this->tenantHeaders($this->tenant, $cashier, $this->mainStoreId);

        $adminExpenseId = $this->inTenant($this->tenant, fn (): int => $this->makeExpense('EXP-ADM-001', [
            'title' => 'مصروف أدمن',
            'category' => 'نثريات',
            'cost_center' => 'operational',
            'amount' => '150.000',
        ])->id);

        // Cashier has daily_journal.view (ROLE-01): sees only own expenses (empty here).
        $this->getJson('/api/v1/expenses', $cashierHeaders)
            ->assertOk()
            ->assertJsonPath('summary.count_filtered', 0)
            ->assertJsonCount(0, 'data');

        $cashierExpenseId = $this->inTenant($this->tenant, fn (): int => $this->makeExpense('EXP-CSH-001', [
            'title' => 'مصروف مسجل باسم الكاشير',
            'category' => 'نثريات',
            'cost_center' => 'operational',
            'amount' => '25.000',
            'user_id' => $cashier->id,
        ])->id);

        // Even for an expense recorded under their user_id, cashier cannot create/update/delete without expenses.manage.
        $payload = [
            'title' => 'محاولة تعديل',
            'category' => 'نثريات',
            'cost_center' => 'operational',
            'amount' => 30.000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ];
        $this->postJson('/api/v1/expenses', $payload, $cashierHeaders)->assertForbidden();
        $this->putJson('/api/v1/expenses/'.$cashierExpenseId, $payload, $cashierHeaders)->assertForbidden();
        $this->deleteJson('/api/v1/expenses/'.$cashierExpenseId, [], $cashierHeaders)->assertForbidden();
        $this->getJson('/api/v1/expenses/'.$adminExpenseId, $cashierHeaders)->assertNotFound();

        // A user with neither expenses.manage nor daily_journal.view gets 403 on index/show/update/delete.
        $this->getJson('/api/v1/expenses', $this->unauthorizedHeaders)->assertForbidden();
        $this->getJson('/api/v1/expenses/'.$cashierExpenseId, $this->unauthorizedHeaders)->assertForbidden();
        $this->putJson('/api/v1/expenses/'.$cashierExpenseId, $payload, $this->unauthorizedHeaders)->assertForbidden();
        $this->deleteJson('/api/v1/expenses/'.$cashierExpenseId, [], $this->unauthorizedHeaders)->assertForbidden();
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
