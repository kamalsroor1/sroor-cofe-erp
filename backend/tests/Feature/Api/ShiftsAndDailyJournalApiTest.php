<?php

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShiftsAndDailyJournalApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'daily_journal.view']);
        Permission::create(['name' => 'pos.sell']);

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

    public function test_can_check_current_shift_when_no_active_shift(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/shifts/current');

        $response->assertStatus(200)
            ->assertJson([
                'success'    => true,
                'has_active' => false,
            ]);
    }

    public function test_can_open_a_new_cashier_shift(): void
    {
        $payload = [
            'opening_cash_balance' => '500.000',
            'notes'                => 'استلام درج الصباح',
            'store_id'             => $this->store->id,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/shifts/open', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'status'               => 'open',
                    'opening_cash_balance' => 500.000,
                    'notes'                => 'استلام درج الصباح',
                ],
            ]);

        $this->assertDatabaseHas('cash_shifts', [
            'status'               => 'open',
            'opening_cash_balance' => '500.000',
        ]);
    }

    public function test_can_get_current_active_shift_with_live_totals(): void
    {
        $shift = CashShift::create([
            'user_id'              => $this->adminUser->id,
            'store_id'             => $this->store->id,
            'shift_number'         => 'SHF-260821-001',
            'status'               => 'open',
            'opened_at'            => now(),
            'opening_cash_balance' => '300.000',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/shifts/current');

        $response->assertStatus(200)
            ->assertJson([
                'success'      => true,
                'has_active'   => true,
                'active_shift' => [
                    'id'                   => $shift->id,
                    'shift_number'         => 'SHF-260821-001',
                    'opening_cash_balance' => 300.000,
                ],
            ]);
    }

    public function test_can_close_shift_and_calculate_discrepancy(): void
    {
        $shift = CashShift::create([
            'user_id'              => $this->adminUser->id,
            'store_id'             => $this->store->id,
            'shift_number'         => 'SHF-260821-002',
            'status'               => 'open',
            'opened_at'            => now()->subHours(4),
            'opening_cash_balance' => '200.000',
        ]);

        $payload = [
            'shift_id'            => $shift->id,
            'actual_cash_balance' => '250.000',
            'notes'               => 'تسليم الدرج',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/shifts/close', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'status'              => 'closed',
                    'actual_cash_balance' => 250.000,
                ],
            ]);

        $this->assertDatabaseHas('cash_shifts', [
            'id'     => $shift->id,
            'status' => 'closed',
        ]);
    }

    public function test_can_get_shift_z_report(): void
    {
        $shift = CashShift::create([
            'user_id'                  => $this->adminUser->id,
            'store_id'                 => $this->store->id,
            'shift_number'             => 'SHF-260821-003',
            'status'                   => 'closed',
            'opened_at'                => now()->subHours(8),
            'closed_at'                => now(),
            'opening_cash_balance'     => '200.000',
            'total_cash_sales'         => '1500.000',
            'total_credit_sales'       => '300.000',
            'total_payments_collected' => '1500.000',
            'total_expenses'           => '100.000',
            'expected_cash_balance'    => '1600.000',
            'actual_cash_balance'      => '1600.000',
            'cash_difference'          => '0.000',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/shifts/' . $shift->id . '/z-report');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'report'  => [
                    'shift_number'          => 'SHF-260821-003',
                    'status'                => 'closed',
                    'opening_cash_balance'  => 200.000,
                    'total_cash_sales'      => 1500.000,
                    'expected_cash_balance' => 1600.000,
                    'actual_cash_balance'   => 1600.000,
                    'cash_difference'       => 0.000,
                ],
            ]);
    }

    public function test_can_query_daily_journal_with_invoices_and_expenses(): void
    {
        $today = now()->toDateString();

        $customer = Customer::create([
            'name'       => 'عميل اليومية',
            'phone'      => '01000000000',
            'is_active'  => true,
        ]);

        Invoice::create([
            'invoice_number' => 'INV-260821-001',
            'customer_id'    => $customer->id,
            'user_id'        => $this->adminUser->id,
            'store_id'       => $this->store->id,
            'invoice_date'   => $today,
            'subtotal'       => '500.000',
            'tax_amount'     => '0.000',
            'discount_amount'=> '0.000',
            'net_total'      => '500.000',
            'paid_amount'    => '500.000',
            'remaining_amount'=> '0.000',
            'payment_type'   => 'cash',
            'status'         => 'confirmed',
        ]);

        Expense::create([
            'expense_number' => 'EXP-260821-0001',
            'title'          => 'شراء بن ومشروبات ضيافة',
            'category'       => 'ضيافة وبوفيه',
            'cost_center'    => 'hospitality',
            'amount'         => '75.000',
            'expense_date'   => $today,
            'payment_method' => 'cash',
            'user_id'        => $this->adminUser->id,
            'store_id'       => $this->store->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/daily-journal?date=' . $today);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'date',
                    'summary' => [
                        'total_sales',
                        'cash_sales',
                        'total_cash_in',
                        'total_expenses',
                        'total_cash_out',
                        'net_cash_today',
                        'expected_cash_in_drawer',
                    ],
                    'invoices',
                    'expenses',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data'    => [
                    'summary' => [
                        'total_sales'    => 500.000,
                        'cash_sales'     => 500.000,
                        'total_expenses' => 75.000,
                        'net_cash_today' => 425.000,
                    ],
                ],
            ]);
    }
}
