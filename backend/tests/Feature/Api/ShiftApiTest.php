<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

class ShiftApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    protected string $adminToken;

    protected User $unauthorizedUser;

    protected string $unauthorizedToken;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixtures and DB assertions run inside the tenant; every request selects it with X-Tenant.
        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        $this->store = $this->adoptMainStore([
            'name' => 'المخزن الرئيسي',
            'code' => 'MAIN-001',
            'type' => 'warehouse',
            'is_main' => true,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');

        $this->adminUser = User::factory()->create([
            'name' => 'كمال سرور',
            'phone' => self::ADMIN_PHONE,
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($adminRole);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $this->unauthorizedUser = User::factory()->create([
            'name' => 'مستخدم بدون صلاحيات',
            'phone' => '01000000000',
            'password' => Hash::make('password'),
            'is_active' => true,
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
        $response = $this->withHeader('Authorization', 'Bearer '.$this->unauthorizedToken)
            ->getJson('/api/v1/shifts');

        $response->assertStatus(403);
    }

    public function test_can_check_current_shift_when_no_active_shift(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/shifts/current');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'has_active' => false,
            ]);
    }

    public function test_can_open_a_new_cashier_shift(): void
    {
        $payload = [
            'opening_cash_balance' => '500.000',
            'notes' => 'استلام درج الصباح',
            'store_id' => $this->store->id,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/shifts/open', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'open',
                    'opening_cash_balance' => 500.000,
                    'notes' => 'استلام درج الصباح',
                ],
            ]);

        $this->assertDatabaseHas('cash_shifts', [
            'status' => 'open',
            'opening_cash_balance' => '500.000',
        ]);
    }

    public function test_open_shift_fails_validation_on_negative_cash(): void
    {
        $payload = [
            'opening_cash_balance' => -50,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/shifts/open', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['opening_cash_balance']);
    }

    public function test_can_get_current_active_shift_with_live_totals(): void
    {
        $shift = CashShift::create([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->store->id,
            'shift_number' => 'SHF-260821-001',
            'status' => 'open',
            'opened_at' => now(),
            'opening_cash_balance' => '300.000',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/shifts/current');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'has_active' => true,
                'active_shift' => [
                    'id' => $shift->id,
                    'shift_number' => 'SHF-260821-001',
                    'opening_cash_balance' => 300.000,
                ],
            ]);
    }

    public function test_can_close_shift_and_calculate_discrepancy(): void
    {
        $shift = CashShift::create([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->store->id,
            'shift_number' => 'SHF-260821-002',
            'status' => 'open',
            'opened_at' => now()->subHours(4),
            'opening_cash_balance' => '200.000',
        ]);

        $payload = [
            'shift_id' => $shift->id,
            'actual_cash_balance' => '250.000',
            'notes' => 'تسليم الدرج',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/shifts/close', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'closed',
                    'actual_cash_balance' => 250.000,
                ],
            ]);

        $this->assertDatabaseHas('cash_shifts', [
            'id' => $shift->id,
            'status' => 'closed',
        ]);
    }

    public function test_can_get_shift_z_report(): void
    {
        $shift = CashShift::create([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->store->id,
            'shift_number' => 'SHF-260821-003',
            'status' => 'closed',
            'opened_at' => now()->subHours(8),
            'closed_at' => now(),
            'opening_cash_balance' => '200.000',
            'total_cash_sales' => '1500.000',
            'total_credit_sales' => '300.000',
            'total_payments_collected' => '1500.000',
            'total_expenses' => '100.000',
            'expected_cash_balance' => '1600.000',
            'actual_cash_balance' => '1600.000',
            'cash_difference' => '0.000',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/shifts/'.$shift->id.'/z-report');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'report' => [
                    'shift_number' => 'SHF-260821-003',
                    'status' => 'closed',
                    'opening_cash_balance' => '200.000',
                    'total_cash_sales' => '1500.000',
                    'expected_cash_balance' => '1600.000',
                    'actual_cash_balance' => '1600.000',
                    'cash_difference' => '0.000',
                ],
            ]);

        // assertJson is loose (200.0 == '200.000'); golden rule 1 needs the exact 3-decimal string.
        $response->assertJsonPath('report.opening_cash_balance', '200.000')
            ->assertJsonPath('report.total_cash_sales', '1500.000')
            ->assertJsonPath('report.total_credit_sales', '300.000')
            ->assertJsonPath('report.expected_cash_balance', '1600.000')
            ->assertJsonPath('report.actual_cash_balance', '1600.000')
            ->assertJsonPath('report.cash_difference', '0.000');
    }

    public function test_shifts_of_another_tenant_are_invisible_and_untouched(): void
    {
        $other = $this->createTenant(); // ends tenancy
        $otherStoreId = (int) $this->tenantStore($other)->id;
        $otherAdminId = (int) $this->tenantAdmin($other)->id;
        $foreignShiftId = $this->inTenant($other, fn (): int => (int) CashShift::create([
            'user_id' => $otherAdminId,
            'store_id' => $otherStoreId,
            'shift_number' => 'SHF-OTHER-001',
            'status' => 'open',
            'opened_at' => now(),
            'opening_cash_balance' => '900.000',
        ])->id);
        $this->useTenantForTest($this->tenant);

        // No active shift here, although the other tenant has one open.
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/shifts/current')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'has_active' => false]);

        // The other tenant's shift id does not resolve here.
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/shifts/'.$foreignShiftId.'/z-report')
            ->assertStatus(404);

        // Closing the other tenant's shift by id is rejected and leaves it untouched.
        $close = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/shifts/close', ['shift_id' => $foreignShiftId, 'actual_cash_balance' => '900.000']);
        $this->assertContains($close->status(), [404, 422]);

        $this->inTenant($other, function () use ($foreignShiftId): void {
            $shift = CashShift::query()->findOrFail($foreignShiftId);
            $this->assertSame('open', $shift->status);
            $this->assertSame('900.000', (string) $shift->opening_cash_balance);
            $this->assertNull($shift->closed_at);
            $this->assertSame('0.000', (string) $shift->actual_cash_balance);
        });
    }

    // ------------------------------------------------------------------
    // SHIFT-01: who may close / print a shift, and what a refusal looks like
    // ------------------------------------------------------------------

    /** A cashier-role user of the main store (the role holds daily_journal.close_shift and pos.access). */
    private function cashier(): User
    {
        return $this->createTenantUser($this->tenant, 'cashier');
    }

    /** Opens a 500.000 shift on the main store through the API as $user; returns its id. */
    private function openShiftAs(User $user): int
    {
        return (int) $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '500.000'], $this->tenantHeaders($this->tenant, $user))
            ->assertStatus(201)
            ->json('data.id');
    }

    public function test_cashier_closes_the_shift_they_opened_with_per_shift_totals(): void
    {
        $customer = Customer::create(['name' => 'عميل نقدي', 'phone' => '01100000077', 'current_balance' => '0.000', 'is_active' => true]);
        $item = Item::create([
            'name' => 'SHIFT-01', 'code' => 'SHIFT-01', 'unit' => 'كجم', 'cost_price' => '10.000', 'selling_price' => '20.000',
            'current_stock' => '50.000', 'is_active' => true,
        ]);
        StoreStock::create(['store_id' => $this->store->id, 'item_id' => $item->id, 'quantity' => '50.000']);

        $cashier = $this->cashier();
        $shiftId = $this->openShiftAs($cashier);

        // One cash sale of 2 x 20.000 on the shift.
        $this->postJson('/api/v1/pos/checkout', [
            'customer_id' => $customer->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $item->id, 'quantity' => '2.000', 'unit_price' => '20.000']],
        ], $this->tenantHeaders($this->tenant, $cashier))->assertStatus(201);

        // The close dialog reads this figure (per shift, not the day's journal).
        $this->getJson('/api/v1/shifts/current', $this->tenantHeaders($this->tenant, $cashier))
            ->assertOk()
            ->assertJsonPath('active_shift.id', $shiftId)
            ->assertJsonPath('metrics.expected_cash_balance', '540.000');

        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '540.000'], $this->tenantHeaders($this->tenant, $cashier))
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_cash_balance', '540.000')
            ->assertJsonPath('data.cash_difference', '0.000');

        $shift = CashShift::query()->findOrFail($shiftId);
        $this->assertSame('closed', $shift->status);
        $this->assertSame('500.000', (string) $shift->opening_cash_balance);
        $this->assertSame('40.000', (string) $shift->total_cash_sales);
        $this->assertSame('540.000', (string) $shift->expected_cash_balance);
        $this->assertSame('540.000', (string) $shift->actual_cash_balance);
        $this->assertSame('0.000', (string) $shift->cash_difference);
    }

    public function test_cashier_gets_the_z_report_of_their_own_closed_shift(): void
    {
        $cashier = $this->cashier();
        $shiftId = $this->openShiftAs($cashier);

        // Open shift (the "print Z" button of the banner).
        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $cashier))
            ->assertOk()
            ->assertJsonPath('report.id', $shiftId);

        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '500.000'], $this->tenantHeaders($this->tenant, $cashier))
            ->assertOk();

        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $cashier))
            ->assertOk()
            ->assertJsonPath('report.status', 'closed')
            ->assertJsonPath('report.expected_cash_balance', '500.000')
            ->assertJsonPath('report.cash_difference', '0.000');
    }

    public function test_same_store_user_with_close_shift_closes_a_shift_opened_by_someone_else(): void
    {
        $shiftId = $this->openShiftAs($this->cashier());
        $colleague = $this->createTenantUser($this->tenant, null, ['daily_journal.close_shift']);

        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '500.000'], $this->tenantHeaders($this->tenant, $colleague))
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->assertSame('closed', CashShift::query()->whereKey($shiftId)->value('status'));
    }

    public function test_user_without_close_shift_gets_a_translated_403(): void
    {
        $shiftId = $this->openShiftAs($this->cashier());
        $viewer = $this->createTenantUser($this->tenant, null, ['daily_journal.view', 'pos.access']);

        $response = $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '500.000'], $this->tenantHeaders($this->tenant, $viewer))
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('auth.forbidden'));

        $this->assertStringNotContainsString('App\\Models', (string) $response->getContent());
        $this->assertSame('open', CashShift::query()->whereKey($shiftId)->value('status'));
    }

    public function test_user_of_another_store_cannot_close_or_read_the_shift_and_no_class_name_leaks(): void
    {
        $shiftId = $this->openShiftAs($this->cashier());
        $otherStore = Store::create(['name' => 'فرع آخر', 'code' => 'BR-SHIFT01', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);
        $outsider = $this->createTenantUser($this->tenant, 'cashier', [], ['default_store_id' => $otherStore->id]);
        $headers = $this->tenantHeaders($this->tenant, $outsider, $otherStore);

        $close = $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '500.000'], $headers)
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('common.record_not_found'));
        $this->assertStringNotContainsString('App\\Models', (string) $close->getContent());
        $this->assertStringNotContainsString('CashShift', (string) $close->getContent());

        $report = $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $headers)
            ->assertStatus(404)
            ->assertJsonPath('message', __('common.record_not_found'));
        $this->assertStringNotContainsString('App\\Models', (string) $report->getContent());

        // Naming the shift's own store instead is refused by the store check (403, translated).
        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '500.000'], $this->tenantHeaders($this->tenant, $outsider))
            ->assertStatus(403)
            ->assertJsonPath('message', __('common.store_access_denied'));

        $this->assertSame('open', CashShift::query()->whereKey($shiftId)->value('status'));
    }

    public function test_model_not_found_404_is_translated_and_never_names_the_model_class(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->adminToken, 'Accept' => 'application/json'];

        // Thrown by a controller/action (findOrFail) and by route-model binding.
        foreach (['/api/v1/shifts/999999/z-report', '/api/v1/customers/999999'] as $uri) {
            $response = $this->getJson($uri, $headers)
                ->assertStatus(404)
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', __('common.record_not_found'));

            $this->assertStringNotContainsString('App\\Models', (string) $response->getContent(), $uri);
            $this->assertStringNotContainsString('No query results', (string) $response->getContent(), $uri);
        }

        // The request locale decides the language (X-Locale; the fixture admin has no saved one).
        $this->getJson('/api/v1/shifts/999999/z-report', $headers + ['X-Locale' => 'en'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'The requested record was not found.');
    }
}
