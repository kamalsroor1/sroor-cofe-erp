<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShiftApiTest extends TestCase
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
        $response = $this->getJson('/api/v1/shifts');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_shifts(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unauthorizedToken)
            ->getJson('/api/v1/shifts');

        $response->assertStatus(403);
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

    public function test_open_shift_fails_validation_on_negative_cash(): void
    {
        $payload = [
            'opening_cash_balance' => -50,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/shifts/open', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['opening_cash_balance']);
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
}
