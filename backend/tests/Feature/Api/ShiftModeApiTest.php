<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ShiftMode;
use App\Models\ActivityLog;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StorePosSetting;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ShiftService;
use App\Support\TenantClock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TenantTestCase;

/**
 * SHIFT-01 (owner decision 2026-11-21): shifts are per cashier by default; a branch setting
 * (store_pos_settings.shift_mode) switches it to ONE shared shift.
 *
 *  - per_cashier: each cashier opens their own shift (at most one open per cashier and branch,
 *    enforced by the UNIQUE cash_shifts.open_guard key); a sale / voucher / expense lands on the
 *    seller's own shift and requires it; closing without an id closes the caller's own shift.
 *  - shared: one open shift per branch, every cashier sells in it (SHOP-06 behaviour).
 *  - the mode cannot change while the branch has any open shift (422 settings.shift_mode_open_shift).
 *  - the setting endpoints: 401 guest, 403 without settings.manage / stores.manage, 422 invalid,
 *    isolated per tenant.
 */
final class ShiftModeApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeId;

    private int $customerId;

    private int $itemId;

    private User $cashierA;

    private User $cashierB;

    protected function setUp(): void
    {
        parent::setUp();

        // The suite default is OFF (phpunit.xml); the per-cashier rule is exercised with the requirement on.
        config(['pos.require_shift_by_default' => true]);

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;

        [$this->customerId, $this->itemId] = $this->inTenant($this->tenant, function (): array {
            $customer = Customer::create(['name' => 'عميل الورديات', 'phone' => '01000007701', 'price_tier' => 'retail', 'is_active' => true, 'current_balance' => '0.000']);
            $item = Item::create([
                'name' => 'بن وردية', 'code' => 'SM-1', 'category' => 'coffee_beans', 'unit' => 'كجم',
                'cost_price' => '10.000', 'weighted_avg_cost' => '10.000', 'selling_price' => '20.000',
                'price_retail' => '20.000', 'price_wholesale' => '20.000',
                'current_stock' => '100.000', 'min_stock_level' => '1.000', 'is_active' => true,
            ]);
            StoreStock::create(['store_id' => $this->storeId, 'item_id' => $item->id, 'quantity' => '100.000']);

            return [(int) $customer->id, (int) $item->id];
        });

        $this->cashierA = $this->createTenantUser($this->tenant, 'cashier');
        $this->cashierB = $this->createTenantUser($this->tenant, 'cashier');
    }

    // ------------------------------------------------------------------ per_cashier (default)

    public function test_the_default_mode_is_per_cashier(): void
    {
        $this->getJson($this->settingsUrl(), $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('data.shift_mode', 'per_cashier');
        $this->getJson($this->shiftRuleUrl(), $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('data.shift_mode', 'per_cashier');
    }

    public function test_two_cashiers_each_open_their_own_shift_and_their_sales_land_on_it(): void
    {
        $shiftA = (int) $this->open($this->cashierA, '100')->assertCreated()->assertJsonPath('data.shift_mode', 'per_cashier')->json('data.id');
        $shiftB = (int) $this->open($this->cashierB, '50')->assertCreated()->json('data.id');
        $this->assertNotSame($shiftA, $shiftB);

        $this->sale($this->cashierA, '2.000')->assertCreated();
        $this->sale($this->cashierA, '0.250')->assertCreated();
        $this->sale($this->cashierB, '1.000')->assertCreated();

        $this->inTenant($this->tenant, function () use ($shiftA, $shiftB): void {
            $this->assertSame(2, Invoice::query()->where('cash_shift_id', $shiftA)->count());
            $this->assertSame(1, Invoice::query()->where('cash_shift_id', $shiftB)->count());
            // The sale vouchers follow their seller too.
            $this->assertSame((int) $this->cashierA->id, (int) Payment::query()->where('cash_shift_id', $shiftA)->value('user_id'));
            $this->assertSame((int) $this->cashierB->id, (int) Payment::query()->where('cash_shift_id', $shiftB)->value('user_id'));

            $service = app(ShiftService::class);
            $totalsA = $service->calculateShiftTotals(CashShift::query()->findOrFail($shiftA));
            $totalsB = $service->calculateShiftTotals(CashShift::query()->findOrFail($shiftB));
            $this->assertSame('45.000', $totalsA['total_cash_sales']);
            $this->assertSame('145.000', $totalsA['expected_cash_balance']);
            $this->assertSame('20.000', $totalsB['total_cash_sales']);
            $this->assertSame('70.000', $totalsB['expected_cash_balance']);

            $guards = CashShift::query()->whereKey([$shiftA, $shiftB])->orderBy('id')->pluck('open_guard')->all();
            $this->assertSame([
                'store:'.$this->storeId.':user:'.$this->cashierA->id,
                'store:'.$this->storeId.':user:'.$this->cashierB->id,
            ], $guards);
        });

        // Each cashier's current shift is their own.
        $this->getJson('/api/v1/shifts/current', $this->tenantHeaders($this->tenant, $this->cashierA))->assertOk()
            ->assertJsonPath('shift_mode', 'per_cashier')
            ->assertJsonPath('active_shift.id', $shiftA);
        $this->getJson('/api/v1/shifts/current', $this->tenantHeaders($this->tenant, $this->cashierB))->assertOk()
            ->assertJsonPath('active_shift.id', $shiftB);
        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($this->tenant, $this->cashierB))->assertOk()
            ->assertJsonPath('data.active_shift.id', $shiftB);
    }

    public function test_the_same_cashier_cannot_open_a_second_shift(): void
    {
        $number = (string) $this->open($this->cashierA)->assertCreated()->json('data.shift_number');

        $this->open($this->cashierA)->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'treasury.shift_already_open_own')
            ->assertJsonPath('message', __('treasury.shift_already_open_own', ['number' => $number]));

        $this->inTenant($this->tenant, fn () => $this->assertSame(1, CashShift::query()->where('status', 'open')->count()));
    }

    public function test_a_cashier_cannot_sell_inside_another_cashiers_shift(): void
    {
        $this->open($this->cashierA)->assertCreated();

        $this->sale($this->cashierB, '1.000')->assertStatus(422)
            ->assertJsonValidationErrors(['shift'])
            ->assertJsonPath('errors.shift.0', __('pos.shift_required_own'));

        $this->inTenant($this->tenant, fn () => $this->assertSame(0, Invoice::query()->count()));
    }

    public function test_an_expense_is_stamped_with_its_writers_own_shift(): void
    {
        $shiftA = (int) $this->open($this->cashierA)->json('data.id');
        $shiftB = (int) $this->open($this->cashierB)->json('data.id');

        $this->inTenant($this->tenant, function () use ($shiftA, $shiftB): void {
            $expense = fn (User $user): Expense => Expense::query()->create([
                'expense_number' => 'EXP-SM-'.uniqid(), 'category' => 'packaging', 'title' => 'أكياس', 'amount' => '7.500',
                'expense_date' => app(TenantClock::class)->businessDate(), 'payment_method' => 'cash',
                'user_id' => $user->id, 'store_id' => $this->storeId,
            ]);

            $this->assertSame($shiftA, (int) $expense($this->cashierA)->cash_shift_id);
            $this->assertSame($shiftB, (int) $expense($this->cashierB)->cash_shift_id);

            $totals = app(ShiftService::class)->calculateShiftTotals(CashShift::query()->findOrFail($shiftB));
            $this->assertSame('7.500', $totals['total_expenses']);
        });
    }

    public function test_unstamped_rows_in_the_window_count_only_in_their_writers_per_cashier_shift(): void
    {
        $shiftA = (int) $this->open($this->cashierA)->json('data.id');
        $shiftB = (int) $this->open($this->cashierB)->json('data.id');

        $this->inTenant($this->tenant, function () use ($shiftA, $shiftB): void {
            // History-style rows (no cash_shift_id) written inside both overlapping windows.
            $stamp = now()->addMinute()->format('Y-m-d H:i:s');
            foreach ([[$this->cashierA, '4.000'], [null, '100.000']] as [$user, $amount]) {
                DB::table('expenses')->insert([
                    'expense_number' => 'EXP-LEG-'.uniqid(), 'category' => 'packaging', 'title' => 'أكياس', 'amount' => $amount,
                    'expense_date' => app(TenantClock::class)->businessDate(), 'payment_method' => 'cash',
                    'user_id' => $user?->id, 'store_id' => $this->storeId, 'created_at' => $stamp, 'updated_at' => $stamp,
                ]);
            }

            $service = app(ShiftService::class);
            $this->assertSame('4.000', $service->calculateShiftTotals(CashShift::query()->findOrFail($shiftA), now()->addHour())['total_expenses']);
            $this->assertSame('0.000', $service->calculateShiftTotals(CashShift::query()->findOrFail($shiftB), now()->addHour())['total_expenses']);
        });
    }

    public function test_closing_without_an_id_closes_only_the_callers_own_shift(): void
    {
        $shiftA = (int) $this->open($this->cashierA)->json('data.id');
        $shiftB = (int) $this->open($this->cashierB)->json('data.id');

        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '0'], $this->tenantHeaders($this->tenant, $this->cashierB))
            ->assertOk()->assertJsonPath('data.id', $shiftB);

        $this->inTenant($this->tenant, function () use ($shiftA, $shiftB): void {
            $this->assertSame('open', CashShift::query()->whereKey($shiftA)->value('status'));
            $this->assertSame('closed', CashShift::query()->whereKey($shiftB)->value('status'));
            $this->assertNull(CashShift::query()->whereKey($shiftB)->value('open_guard'));
        });

        // Cashier B opens the next shift after closing; cashier A is still refused a second one.
        $this->open($this->cashierB)->assertCreated();
        $this->open($this->cashierA)->assertStatus(409);
    }

    public function test_the_database_refuses_a_second_open_shift_for_the_same_cashier(): void
    {
        $this->open($this->cashierA)->assertCreated();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->inTenant($this->tenant, fn () => CashShift::query()->create([
            'user_id' => $this->cashierA->id,
            'store_id' => $this->storeId,
            'shift_number' => 'SHIFT-RACE-PC',
            'status' => 'open',
            'shift_mode' => 'per_cashier',
            'open_guard' => 'store:'.$this->storeId.':user:'.$this->cashierA->id,
            'opened_at' => now(),
            'opening_cash_balance' => '0.000',
        ]));
    }

    // ------------------------------------------------------------------ shared

    public function test_shared_mode_keeps_one_shift_per_branch_for_every_cashier(): void
    {
        $this->setMode(ShiftMode::Shared);

        $shiftId = (int) $this->open($this->cashierA)->assertCreated()->assertJsonPath('data.shift_mode', 'shared')->json('data.id');
        $this->open($this->cashierB)->assertStatus(409)->assertJsonPath('error_code', 'treasury.shift_already_open');

        $this->sale($this->cashierA, '1.000')->assertCreated();
        $this->sale($this->cashierB, '1.000')->assertCreated();

        $this->inTenant($this->tenant, function () use ($shiftId): void {
            $this->assertSame(2, Invoice::query()->where('cash_shift_id', $shiftId)->count());
            $this->assertSame('store:'.$this->storeId, CashShift::query()->whereKey($shiftId)->value('open_guard'));
        });

        $this->getJson('/api/v1/shifts/current', $this->tenantHeaders($this->tenant, $this->cashierB))->assertOk()
            ->assertJsonPath('shift_mode', 'shared')
            ->assertJsonPath('active_shift.id', $shiftId);
    }

    // ------------------------------------------------------------------ switching the setting

    public function test_switching_the_mode_is_refused_while_the_branch_has_an_open_shift(): void
    {
        $this->open($this->cashierA)->assertCreated();

        foreach ([$this->settingsUrl(), $this->shiftRuleUrl()] as $url) {
            $this->putJson($url, ['shift_mode' => 'shared'], $this->tenantHeaders($this->tenant))
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'settings.shift_mode_open_shift')
                ->assertJsonPath('message', __('settings.shift_mode_open_shift'));
        }

        $this->inTenant($this->tenant, fn () => $this->assertSame(ShiftMode::PerCashier, StorePosSetting::forStore($this->storeId)->shiftMode()));

        // Re-sending the current mode is not a switch, so it is not refused.
        $this->putJson($this->settingsUrl(), ['shift_mode' => 'per_cashier'], $this->tenantHeaders($this->tenant))->assertOk();
    }

    public function test_switching_the_mode_without_an_open_shift_is_saved_and_logged(): void
    {
        $id = (int) $this->open($this->cashierA)->json('data.id');
        $this->inTenant($this->tenant, fn () => app(ShiftService::class)->closeShift(CashShift::query()->findOrFail($id), '0.000'));

        $this->putJson($this->settingsUrl(), ['shift_mode' => 'shared'], $this->tenantHeaders($this->tenant))
            ->assertOk()->assertJsonPath('data.shift_mode', 'shared');

        $this->inTenant($this->tenant, function (): void {
            $log = ActivityLog::query()->where('properties->setting', 'shift_mode')->latest('id')->firstOrFail();
            $this->assertSame('per_cashier', $log->properties['old']);
            $this->assertSame('shared', $log->properties['new']);
            $this->assertSame($this->storeId, (int) $log->properties['store_id']);
        });

        // A branch owner (stores.manage) switches it back through the shift-rule endpoint.
        $owner = $this->createTenantUser($this->tenant, null, ['stores.manage']);
        $this->putJson($this->shiftRuleUrl(), ['shift_mode' => 'per_cashier'], $this->tenantHeaders($this->tenant, $owner))
            ->assertOk()
            ->assertJsonPath('data.shift_mode', 'per_cashier')
            ->assertJsonPath('data.require_shift_for_sales', true);
    }

    public function test_an_open_shift_in_another_branch_does_not_block_the_switch(): void
    {
        $other = $this->inTenant($this->tenant, fn (): int => (int) Store::query()->create([
            'name' => 'فرع تاني', 'code' => 'SM-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true,
        ])->id);
        $this->inTenant($this->tenant, fn () => CashShift::query()->create([
            'user_id' => $this->cashierA->id, 'store_id' => $other, 'shift_number' => 'SH-OTHER', 'status' => 'open',
            'opened_at' => now(), 'opening_cash_balance' => '0.000',
        ]));

        $this->putJson($this->settingsUrl(), ['shift_mode' => 'shared'], $this->tenantHeaders($this->tenant))
            ->assertOk()->assertJsonPath('data.shift_mode', 'shared');
    }

    // ------------------------------------------------------------------ auth / validation / isolation

    public function test_setting_endpoints_reject_guests_unauthorised_users_and_invalid_values(): void
    {
        $guest = $this->tenantGuestHeaders($this->tenant);
        $this->putJson($this->settingsUrl(), ['shift_mode' => 'shared'], $guest)->assertUnauthorized();
        $this->putJson($this->shiftRuleUrl(), ['shift_mode' => 'shared'], $guest)->assertUnauthorized();

        $cashier = $this->tenantHeaders($this->tenant, $this->cashierA);
        $this->putJson($this->settingsUrl(), ['shift_mode' => 'shared'], $cashier)->assertForbidden();
        $this->putJson($this->shiftRuleUrl(), ['shift_mode' => 'shared'], $cashier)->assertForbidden();

        $admin = $this->tenantHeaders($this->tenant);
        $this->putJson($this->settingsUrl(), ['shift_mode' => 'per_store'], $admin)->assertStatus(422)->assertJsonValidationErrors(['shift_mode']);
        $this->putJson($this->shiftRuleUrl(), ['shift_mode' => 'per_store'], $admin)->assertStatus(422)->assertJsonValidationErrors(['shift_mode']);
        // The shift-rule endpoint needs at least one of its two keys.
        $this->putJson($this->shiftRuleUrl(), [], $admin)->assertStatus(422)->assertJsonValidationErrors(['require_shift_for_sales', 'shift_mode']);

        $this->inTenant($this->tenant, fn () => $this->assertSame(ShiftMode::PerCashier, StorePosSetting::forStore($this->storeId)->shiftMode()));
    }

    public function test_the_mode_is_isolated_between_tenants(): void
    {
        $this->putJson($this->settingsUrl(), ['shift_mode' => 'shared'], $this->tenantHeaders($this->tenant))->assertOk();

        $other = $this->createTenant();
        $otherStore = (int) $this->tenantStore($other)->id;
        $this->inTenant($other, fn () => $this->assertSame(ShiftMode::PerCashier, StorePosSetting::forStore($otherStore)->shiftMode()));

        // Tenant A's token cannot change tenant B's branch setting.
        $headers = array_merge($this->tenantHeaders($this->tenant), ['X-Tenant' => (string) $other->getTenantKey(), 'X-Store-Id' => (string) $otherStore]);
        $this->assertContains(
            $this->putJson('/api/v1/stores/'.$otherStore.'/pos-settings', ['shift_mode' => 'shared'], $headers)->status(),
            [401, 403, 404],
        );
        $this->inTenant($other, fn () => $this->assertSame(ShiftMode::PerCashier, StorePosSetting::forStore($otherStore)->shiftMode()));
    }

    // ------------------------------------------------------------------ helpers

    private function settingsUrl(): string
    {
        return '/api/v1/stores/'.$this->storeId.'/pos-settings';
    }

    private function shiftRuleUrl(): string
    {
        return $this->settingsUrl().'/shift-rule';
    }

    private function setMode(ShiftMode $mode): void
    {
        $this->inTenant($this->tenant, fn () => StorePosSetting::query()->updateOrCreate(['store_id' => $this->storeId], ['shift_mode' => $mode->value]));
    }

    private function open(User $user, string $float = '0'): TestResponse
    {
        return $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => $float], $this->tenantHeaders($this->tenant, $user));
    }

    private function sale(User $user, string $quantity): TestResponse
    {
        return $this->postJson('/api/v1/pos/checkout', [
            'customer_id' => $this->customerId, 'payment_type' => 'cash', 'payment_method' => 'cash',
            'items' => [['item_id' => $this->itemId, 'quantity' => $quantity, 'unit_price' => '20.000']],
        ], $this->tenantHeaders($this->tenant, $user));
    }
}
