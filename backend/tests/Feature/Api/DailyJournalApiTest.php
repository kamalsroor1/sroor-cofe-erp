<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DailyJournalApiTest extends TestCase
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
            'name'      => 'الفرع الرئيسي',
            'code'      => 'MAIN-001',
            'type'      => 'retail',
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
        $response = $this->getJson('/api/v1/daily-journal');
        $response->assertStatus(401);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unauthorizedToken)
            ->getJson('/api/v1/daily-journal');

        $response->assertStatus(403);
    }

    public function test_can_fetch_daily_journal_with_complete_ledger_and_accurate_metrics(): void
    {
        $today = now()->toDateString();

        // 1. Active Shift with 500 opening cash
        CashShift::create([
            'user_id'              => $this->adminUser->id,
            'store_id'             => $this->mainStore->id,
            'shift_number'         => 'SHF-001',
            'status'               => 'open',
            'opened_at'            => now(),
            'opening_cash_balance' => '500.000',
        ]);

        $customer = Customer::create([
            'name'            => 'عميل اليومية',
            'phone'           => '01011112222',
            'current_balance' => '0.000',
            'is_active'       => true,
        ]);

        // 2. Cash Invoice: Net 300, Paid 300
        Invoice::create([
            'store_id'        => $this->mainStore->id,
            'customer_id'     => $customer->id,
            'user_id'         => $this->adminUser->id,
            'invoice_number'  => 'INV-101',
            'invoice_date'    => $today,
            'subtotal'        => 300.000,
            'discount_amount' => 0.000,
            'tax_amount'      => 0.000,
            'net_total'       => 300.000,
            'paid_amount'     => 300.000,
            'remaining_amount'=> 0.000,
            'payment_type'    => 'cash',
            'status'          => 'confirmed',
        ]);

        // 3. Customer Cash Payment: 200
        Payment::create([
            'payment_number' => 'PAY-1001',
            'user_id'        => $this->adminUser->id,
            'customer_id'    => $customer->id,
            'amount'         => '200.000',
            'payment_method' => 'cash',
            'payment_date'   => $today,
            'notes'          => 'تحصيل نقدي',
        ]);

        // 4. Cash Expense: 150
        Expense::create([
            'store_id'       => $this->mainStore->id,
            'user_id'        => $this->adminUser->id,
            'expense_number' => 'EXP-101',
            'title'          => 'شراء أدوات نظافة',
            'amount'         => '150.000',
            'category'       => 'تشغيلي',
            'cost_center'    => 'فرع رئيسي',
            'expense_date'   => $today,
            'payment_method' => 'cash',
        ]);

        // Calculations:
        // Cash in = 300 + 200 = 500
        // Cash out = 150
        // Net Cash Today = 500 - 150 = 350
        // Expected Cash in Drawer = 500 (opening) + 350 = 850

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/daily-journal?date=' . $today);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'date'    => $today,
                    'summary' => [
                        'total_sales'             => 300.0,
                        'cash_sales'              => 300.0,
                        'customer_payments'       => 200.0,
                        'total_cash_in'           => 500.0,
                        'total_expenses'          => 150.0,
                        'cash_expenses'           => 150.0,
                        'total_cash_out'          => 150.0,
                        'net_cash_today'          => 350.0,
                        'opening_cash_balance'    => 500.0,
                        'expected_cash_in_drawer' => 850.0,
                    ],
                ],
            ]);
    }

    public function test_daily_journal_respects_x_store_id_header(): void
    {
        $today = now()->toDateString();

        // Branch store expense
        Expense::create([
            'store_id'       => $this->branchStore->id,
            'user_id'        => $this->adminUser->id,
            'expense_number' => 'EXP-MAADI-01',
            'title'          => 'إيجار فرع المعادي',
            'amount'         => '5000.000',
            'category'       => 'إيجارات',
            'cost_center'    => 'فرع المعادي',
            'expense_date'   => $today,
            'payment_method' => 'cash',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'X-Store-Id'    => (string) $this->branchStore->id,
        ])->getJson('/api/v1/daily-journal?date=' . $today);

        $response->assertStatus(200)
            ->assertJsonPath('data.store_id', $this->branchStore->id);

        $this->assertEquals(5000, $response->json('data.summary.total_expenses'));
    }

    public function test_daily_journal_handles_empty_day_cleanly(): void
    {
        $futureDate = now()->addDays(30)->toDateString();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/daily-journal?date=' . $futureDate);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'summary' => [
                        'total_sales'    => 0.0,
                        'total_cash_in'  => 0.0,
                        'total_cash_out' => 0.0,
                        'net_cash_today' => 0.0,
                    ],
                ],
            ]);
    }
}
