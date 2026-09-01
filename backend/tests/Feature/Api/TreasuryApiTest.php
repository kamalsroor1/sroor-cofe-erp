<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TreasuryApiTest extends TestCase
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
        $response = $this->getJson('/api/v1/treasury/summary');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_treasury_summary(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unauthorizedToken)
            ->getJson('/api/v1/treasury/summary');

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_get_treasury_summary(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->withHeader('X-Store-Id', (string)$this->store->id)
            ->getJson('/api/v1/treasury/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'store_id',
                'today' => [
                    'date',
                    'sales_total',
                    'cash_collected',
                    'customer_receipts',
                    'total_inflow',
                    'supplier_paid',
                    'expenses_total',
                    'total_outflow',
                    'net_cash',
                ],
                'balances' => [
                    'total_receivable',
                    'total_payable',
                    'accounts',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_treasury_summary_reflects_sales_expenses_and_net_cash(): void
    {
        $customer = Customer::create([
            'name'            => 'عميل تجربة خزينة',
            'phone'           => '01011112222',
            'current_balance' => '500.000',
            'is_active'       => true,
        ]);

        $supplier = Supplier::create([
            'name'            => 'مورد تجربة خزينة',
            'phone'           => '01033334444',
            'current_balance' => '1200.000',
            'is_active'       => true,
        ]);

        Invoice::create([
            'store_id'        => $this->store->id,
            'customer_id'     => $customer->id,
            'user_id'         => $this->adminUser->id,
            'invoice_number'  => 'INV-100',
            'invoice_date'    => now()->toDateString(),
            'subtotal'        => '1000.000',
            'discount_amount' => '0.000',
            'tax_amount'      => '0.000',
            'net_total'       => '1000.000',
            'paid_amount'     => '1000.000',
            'remaining_amount'=> '0.000',
            'payment_type'    => 'cash',
            'status'          => 'confirmed',
            'payment_status'  => 'paid',
        ]);

        Expense::create([
            'store_id'       => $this->store->id,
            'user_id'        => $this->adminUser->id,
            'expense_number' => 'EXP-100',
            'title'          => 'مصروف بوفيه',
            'amount'         => '200.000',
            'category'       => 'hospitality',
            'payment_method' => 'cash',
            'expense_date'   => now()->toDateString(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->withHeader('X-Store-Id', (string)$this->store->id)
            ->getJson('/api/v1/treasury/summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'today'   => [
                    'sales_total'    => 1000.0,
                    'cash_collected' => 1000.0,
                    'expenses_total' => 200.0,
                    'net_cash'       => 800.0,
                ],
                'balances' => [
                    'total_receivable' => 500.0,
                    'total_payable'    => 1200.0,
                ],
            ]);
    }

    public function test_treasury_summary_includes_active_shift(): void
    {
        $shift = CashShift::create([
            'store_id'             => $this->store->id,
            'user_id'              => $this->adminUser->id,
            'shift_number'         => 'SH-101',
            'opened_at'            => now(),
            'opening_cash_balance' => '500.000',
            'status'               => 'open',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->withHeader('X-Store-Id', (string)$this->store->id)
            ->getJson('/api/v1/treasury/summary');

        $response->assertStatus(200)
            ->assertJson([
                'success'      => true,
                'active_shift' => [
                    'id'                   => $shift->id,
                    'shift_number'         => 'SH-101',
                    'opening_cash_balance' => 500.0,
                ],
            ]);
    }
}
