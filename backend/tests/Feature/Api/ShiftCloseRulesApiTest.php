<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Store;
use App\Models\StorePosSetting;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\TenantTestCase;

/**
 * DEC-SHIFT (owner decisions):
 *  1. a shift is closed only by the user who opened it, or by a manager (reports.view / admin):
 *     anyone else with daily_journal.close_shift gets 403 and the shift stays open; a shift
 *     outside the caller's store stays 404.
 *  2. blind count: before the close, a user without reports.view never receives the open shift's
 *     expected cash (/shifts/current, its provisional Z report); the close response and the
 *     closed shift's Z report show expected and difference to the opener / manager.
 */
final class ShiftCloseRulesApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeA;

    private int $storeB;

    private int $customerId;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeA = (int) $this->tenantStore($this->tenant)->id;
        $this->inTenant($this->tenant, function (): void {
            $this->storeB = (int) Store::query()->create([
                'name' => 'فرع ب', 'code' => 'BR-B-CLR', 'type' => 'retail', 'is_main' => false, 'is_active' => true,
            ])->id;
            $this->customerId = (int) Customer::create(['name' => 'عميل', 'phone' => '01000008201', 'price_tier' => 'retail', 'is_active' => true])->id;
            $item = Item::create([
                'name' => 'بن', 'code' => 'CLR-1', 'category' => 'coffee_beans', 'unit' => 'كجم',
                'cost_price' => '10.000', 'weighted_avg_cost' => '10.000', 'selling_price' => '20.000',
                'price_retail' => '20.000', 'price_wholesale' => '20.000',
                'current_stock' => '100.000', 'min_stock_level' => '1.000', 'is_active' => true,
            ]);
            $this->itemId = (int) $item->id;
            StoreStock::create(['store_id' => $this->storeA, 'item_id' => $item->id, 'quantity' => '100.000']);
        });
    }

    // ------------------------------------------------------------------ 1. who closes

    public function test_the_opener_closes_their_own_shift(): void
    {
        $opener = $this->createTenantUser($this->tenant, 'cashier');
        $shiftId = $this->openShiftFor($opener, $this->storeA);

        $this->close($opener, $shiftId, '100.000')->assertOk()
            ->assertJsonPath('data.id', $shiftId)
            ->assertJsonPath('data.status', 'closed');
        $this->assertSame('closed', $this->shiftStatus($shiftId));
    }

    public function test_another_cashier_with_close_shift_gets_403_by_id_and_without_id_and_the_shift_stays_open(): void
    {
        $opener = $this->createTenantUser($this->tenant, 'cashier');
        $other = $this->createTenantUser($this->tenant, 'cashier');
        // SHIFT-01: "without id" closes the branch's shift only in a shared branch (per cashier, the
        // caller's own shift: ShiftModeApiTest).
        $this->sharedBranch();
        $shiftId = $this->openShiftFor($opener, $this->storeA);

        $this->close($other, $shiftId, '100.000')->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('treasury.shift_close_not_owner'));
        // Omitting shift_id closes "the store's open shift": the same rule applies.
        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '100.000'], $this->tenantHeaders($this->tenant, $other))
            ->assertForbidden();

        $this->assertSame('open', $this->shiftStatus($shiftId));
    }

    public function test_a_manager_with_reports_view_and_the_admin_close_another_users_shift(): void
    {
        $opener = $this->createTenantUser($this->tenant, 'cashier');
        $manager = $this->createTenantUser($this->tenant, null, ['daily_journal.close_shift', 'reports.view']);

        $first = $this->openShiftFor($opener, $this->storeA);
        $this->close($manager, $first, '100.000')->assertOk()->assertJsonPath('data.status', 'closed');

        $second = $this->openShiftFor($opener, $this->storeA);
        $this->close(null, $second, '100.000')->assertOk()->assertJsonPath('data.status', 'closed');
    }

    public function test_reports_view_alone_does_not_close_a_shift(): void
    {
        $opener = $this->createTenantUser($this->tenant, 'cashier');
        $reader = $this->createTenantUser($this->tenant, null, ['daily_journal.view', 'reports.view']);
        $shiftId = $this->openShiftFor($opener, $this->storeA);

        $this->close($reader, $shiftId, '100.000')->assertForbidden();
        $this->assertSame('open', $this->shiftStatus($shiftId));
    }

    public function test_a_shift_without_an_opener_is_closed_by_a_manager_only(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $shiftId = $this->inTenant($this->tenant, fn (): int => (int) CashShift::query()->create([
            'user_id' => null, 'store_id' => $this->storeA, 'shift_number' => 'SHIFT-SYS-1', 'status' => 'open',
            'opened_at' => now(), 'opening_cash_balance' => '0.000',
        ])->id);

        $this->close($cashier, $shiftId, '0.000')->assertForbidden();
        $this->close(null, $shiftId, '0.000')->assertOk();
    }

    public function test_a_shift_of_a_store_the_closer_cannot_access_stays_404_not_403(): void
    {
        $other = $this->createTenantUser($this->tenant, 'cashier');
        $shiftB = $this->openShiftFor($this->tenantAdmin($this->tenant), $this->storeB);

        $this->close($other, $shiftB, '100.000')->assertNotFound();
        $this->assertSame('open', $this->shiftStatus($shiftB));
    }

    public function test_validation_guest_and_tenant_isolation(): void
    {
        $opener = $this->createTenantUser($this->tenant, 'cashier');
        $shiftId = $this->openShiftFor($opener, $this->storeA);

        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId], $this->tenantHeaders($this->tenant, $opener))
            ->assertStatus(422)->assertJsonValidationErrors(['actual_cash_balance']);
        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '1.000'], $this->tenantGuestHeaders($this->tenant))
            ->assertUnauthorized();

        // The same id in another tenant is that tenant's business: here it does not exist (404).
        $otherTenant = $this->createTenant();
        $otherManager = $this->tenantAdmin($otherTenant);
        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '1.000'], $this->tenantHeaders($otherTenant, $otherManager))
            ->assertNotFound();
        $this->assertSame('open', $this->shiftStatus($shiftId));
    }

    // ------------------------------------------------------------------ 2. blind count

    public function test_the_opener_without_reports_view_gets_no_expected_cash_before_closing(): void
    {
        $opener = $this->createTenantUser($this->tenant, 'cashier');
        $shiftId = $this->openShiftFor($opener, $this->storeA);
        $this->sale($opener, '5.000'); // 100.000 cash

        $current = $this->getJson('/api/v1/shifts/current', $this->tenantHeaders($this->tenant, $opener))->assertOk()
            ->assertJsonPath('active_shift.id', $shiftId)
            ->assertJsonPath('active_shift.expected_cash_balance', null)
            ->assertJsonPath('active_shift.cash_difference', null)
            ->assertJsonPath('metrics.expected_cash_balance', null)
            // The other lines of the opener's own shift stay (the X-report), as before.
            ->assertJsonPath('metrics.opening_cash_balance', '100.000')
            ->assertJsonPath('metrics.total_cash_sales', '100.000');
        $this->assertArrayHasKey('expected_cash_balance', (array) $current->json('metrics'));

        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $opener))->assertOk()
            ->assertJsonPath('report.is_provisional', true)
            ->assertJsonPath('report.expected_cash_balance', null)
            ->assertJsonPath('report.cash_difference', null);
    }

    public function test_a_manager_still_sees_the_expected_cash_of_the_open_shift(): void
    {
        $opener = $this->createTenantUser($this->tenant, 'cashier');
        $manager = $this->createTenantUser($this->tenant, null, ['daily_journal.view', 'reports.view']);
        // SHIFT-01: /shifts/current shows the branch's shift to a manager in a shared branch (per
        // cashier it is the caller's own shift); the z-report by id below works in both modes.
        $this->sharedBranch();
        $shiftId = $this->openShiftFor($opener, $this->storeA);
        $this->sale($opener, '5.000');

        foreach ([$manager, null] as $reader) {
            $this->getJson('/api/v1/shifts/current', $this->tenantHeaders($this->tenant, $reader))->assertOk()
                ->assertJsonPath('metrics.expected_cash_balance', '200.000')
                ->assertJsonPath('active_shift.id', $shiftId);
            $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $reader))->assertOk()
                ->assertJsonPath('report.expected_cash_balance', '200.000');
        }
    }

    public function test_after_the_count_is_submitted_the_opener_sees_expected_and_difference(): void
    {
        $opener = $this->createTenantUser($this->tenant, 'cashier');
        $shiftId = $this->openShiftFor($opener, $this->storeA);
        $this->sale($opener, '0.250'); // 5.000 cash

        $this->close($opener, $shiftId, '100.500')->assertOk()
            ->assertJsonPath('data.expected_cash_balance', '105.000')
            ->assertJsonPath('data.actual_cash_balance', '100.500')
            ->assertJsonPath('data.cash_difference', '-4.500');

        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $opener))->assertOk()
            ->assertJsonPath('report.is_provisional', false)
            ->assertJsonPath('report.expected_cash_balance', '105.000')
            ->assertJsonPath('report.cash_difference', '-4.500');
    }

    public function test_closing_an_already_closed_shift_is_idempotent_without_duplicate_activity(): void
    {
        $opener = $this->createTenantUser($this->tenant, 'cashier');
        $otherCashier = $this->createTenantUser($this->tenant, 'cashier');
        $shiftId = $this->openShiftFor($opener, $this->storeA);
        $this->sale($opener, '0.250'); // 5.000 cash -> expected 105.000

        $first = $this->close($opener, $shiftId, '105.000')->assertOk()
            ->assertJsonPath('data.id', $shiftId)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_cash_balance', '105.000')
            ->assertJsonPath('data.actual_cash_balance', '105.000')
            ->assertJsonPath('data.cash_difference', '0.000');

        // Second device / double-click sends close again (even with different counted cash):
        // must return 200 with the original closed shift without mutating balances or logging twice.
        $second = $this->close($opener, $shiftId, '999.000')->assertOk()
            ->assertJsonPath('data.id', $shiftId)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_cash_balance', '105.000')
            ->assertJsonPath('data.actual_cash_balance', '105.000')
            ->assertJsonPath('data.cash_difference', '0.000');

        $this->assertSame($first->json('data.closed_at'), $second->json('data.closed_at'));

        // Unauthorized cashier still receives 403, and other tenant/nonexistent shifts still receive 404.
        $this->close($otherCashier, $shiftId, '105.000')->assertForbidden();
        $this->close(null, 999999, '105.000')->assertNotFound();

        $otherTenant = $this->createTenant();
        $this->postJson(
            '/api/v1/shifts/close',
            ['shift_id' => $shiftId, 'actual_cash_balance' => '105.000'],
            $this->tenantHeaders($otherTenant, $this->tenantAdmin($otherTenant)),
        )->assertNotFound();

        $this->inTenant($this->tenant, function () use ($shiftId): void {
            $closedLogs = ActivityLog::query()
                ->where('module', 'shifts')
                ->where('action', 'shift_closed')
                ->where('subject_id', $shiftId)
                ->count();
            $this->assertSame(1, $closedLogs);
        });
    }

    // ------------------------------------------------------------------ helpers

    private function close(?User $user, int $shiftId, string $counted): TestResponse
    {
        return $this->postJson('/api/v1/shifts/close', [
            'shift_id' => $shiftId, 'actual_cash_balance' => $counted, 'notes' => '',
        ], $this->tenantHeaders($this->tenant, $user));
    }

    private function sale(User $user, string $quantity): void
    {
        $this->postJson('/api/v1/pos/checkout', [
            'customer_id' => $this->customerId, 'payment_type' => 'cash', 'payment_method' => 'cash',
            'items' => [['item_id' => $this->itemId, 'quantity' => $quantity, 'unit_price' => '20.000']],
        ], $this->tenantHeaders($this->tenant, $user))->assertCreated();
    }

    private function openShiftFor(User $user, int $storeId): int
    {
        return $this->inTenant($this->tenant, function () use ($user, $storeId): int {
            $this->actingAs(User::query()->findOrFail($user->id));

            return (int) app(ShiftService::class)->openShift('100.000', null, $storeId, (int) $user->id)->id;
        });
    }

    private function sharedBranch(): void
    {
        $this->inTenant($this->tenant, fn () => StorePosSetting::query()->updateOrCreate(['store_id' => $this->storeA], ['shift_mode' => 'shared']));
    }

    private function shiftStatus(int $shiftId): string
    {
        return $this->inTenant($this->tenant, fn (): string => (string) CashShift::query()->whereKey($shiftId)->value('status'));
    }
}
