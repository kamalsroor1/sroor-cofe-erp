<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $mainStore;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $this->user = User::factory()->create();
        $this->user->assignRole($adminRole);
        $this->actingAs($this->user);

        $this->mainStore = Store::create([
            'name' => 'المخزن الرئيسي',
            'code' => 'MAIN-01',
            'type' => 'main_store',
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    public function test_can_create_expense_with_exact_decimal_and_user_attribution(): void
    {
        $expense = Expense::create([
            'expense_number' => 'EXP-2026-001',
            'category' => 'شنط وأكياس',
            'title' => 'شراء شنط تعبئة بن مقاس 250 جم',
            'amount' => '350.500',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'user_id' => $this->user->id,
            'store_id' => $this->mainStore->id,
            'notes' => 'فاتورة ضريبية',
        ]);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'category' => 'شنط وأكياس',
            'amount' => '350.500',
            'payment_method' => 'cash',
        ]);

        $this->assertEquals('350.500', $expense->amount);
    }

    public function test_soft_deleting_and_restoring_expense(): void
    {
        $expense = Expense::create([
            'expense_number' => 'EXP-2026-002',
            'category' => 'صيانة مطاحن ومعدات',
            'title' => 'صيانة ترس مطحنة المحل',
            'amount' => '500.000',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'user_id' => $this->user->id,
            'store_id' => $this->mainStore->id,
        ]);

        $expense->delete();
        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);

        $expense->restore();
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'deleted_at' => null]);
    }
}
