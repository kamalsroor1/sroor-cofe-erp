<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class ExpenseServiceTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected int $mainStoreId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->user = $this->createTenantUser($this->tenant, 'admin');

        $this->mainStoreId = $this->inTenant($this->tenant, fn (): int => Store::create([
            'name' => 'المخزن الرئيسي',
            'code' => 'MAIN-02',
            'type' => 'main_store',
            'is_active' => true,
            'is_default' => true,
        ])->id);
    }

    public function test_can_create_expense_with_exact_decimal_and_user_attribution(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));

            $expense = Expense::create([
                'expense_number' => 'EXP-2026-001',
                'category' => 'شنط وأكياس',
                'title' => 'شراء شنط تعبئة بن مقاس 250 جم',
                'amount' => '350.500',
                'expense_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'user_id' => $this->user->id,
                'store_id' => $this->mainStoreId,
                'notes' => 'فاتورة ضريبية',
            ]);

            $this->assertDatabaseHas('expenses', [
                'id' => $expense->id,
                'category' => 'شنط وأكياس',
                'amount' => '350.500',
                'payment_method' => 'cash',
            ]);

            $this->assertEquals('350.500', $expense->amount);
        });
    }

    public function test_soft_deleting_and_restoring_expense(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));

            $expense = Expense::create([
                'expense_number' => 'EXP-2026-002',
                'category' => 'صيانة مطاحن ومعدات',
                'title' => 'صيانة ترس مطحنة المحل',
                'amount' => '500.000',
                'expense_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'user_id' => $this->user->id,
                'store_id' => $this->mainStoreId,
            ]);

            $expense->delete();
            $this->assertSoftDeleted('expenses', ['id' => $expense->id]);

            $expense->restore();
            $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'deleted_at' => null]);
        });
    }

    public function test_expense_numbers_and_rows_are_private_to_each_tenant(): void
    {
        $other = $this->createTenant();
        $otherStoreId = (int) $this->tenantStore($other)->id;
        $otherAdminId = (int) $this->tenantAdmin($other)->id;

        $make = fn (int $userId, int $storeId): int => Expense::create([
            'expense_number' => 'EXP-2026-777',
            'category' => 'نثريات',
            'title' => 'نفس رقم المصروف',
            'amount' => '10.250',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'user_id' => $userId,
            'store_id' => $storeId,
        ])->id;

        // The same document number is legal in both tenants (separate databases).
        $mine = $this->inTenant($this->tenant, fn (): int => $make($this->user->id, $this->mainStoreId));
        $theirs = $this->inTenant($other, fn (): int => $make($otherAdminId, $otherStoreId));
        $this->assertNotSame($mine, $theirs);

        // Deleting in A leaves B's expense alive.
        $this->inTenant($this->tenant, fn () => Expense::findOrFail($mine)->delete());
        $this->inTenant($other, function () use ($theirs, $mine): void {
            $this->assertDatabaseHas('expenses', ['id' => $theirs, 'amount' => '10.250', 'deleted_at' => null]);
            $this->assertNull(Expense::withTrashed()->find($mine));
        });
    }
}
