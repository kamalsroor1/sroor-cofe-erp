<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ShiftService;
use Tests\TenantTestCase;

/**
 * BUG-03: ShiftPolicy is bound to CashShift, so a non-admin can print the Z/X report of a shift
 * and close a shift by id from the daily journal.
 *
 * z-report: store readable AND (admin OR reports.view OR the shift's own cashier); a user
 * with none of pos.access / daily_journal.view gets 403 from the controller; a store or
 * ownership denial is 404.
 * close: daily_journal.close_shift (route) AND access to the shift's store, no ownership rule (a
 * supervisor closes a cashier's shift); a shift outside the active store is 404.
 */
final class ShiftReportAuthorizationApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeA;

    private int $storeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeA = (int) $this->tenantStore($this->tenant)->id;
        $this->storeB = $this->inTenant($this->tenant, fn (): int => (int) Store::query()->create([
            'name' => 'فرع ب', 'code' => 'BR-B-AUTH', 'type' => 'retail', 'is_main' => false, 'is_active' => true,
        ])->id);
    }

    public function test_the_seeded_cashier_gets_the_provisional_report_of_their_own_open_shift(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $shiftId = $this->openShiftFor($cashier, $this->storeA);

        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $cashier))
            ->assertOk()
            ->assertJsonPath('report.id', $shiftId)
            ->assertJsonPath('report.is_provisional', true)
            ->assertJsonPath('report.closed_at', null);
    }

    public function test_a_pos_only_cashier_gets_their_own_report_open_and_closed_but_not_another_cashiers(): void
    {
        $own = $this->createTenantUser($this->tenant, null, ['pos.access']);
        $other = $this->createTenantUser($this->tenant, null, ['pos.access']);
        $shiftId = $this->openShiftFor($own, $this->storeA);

        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $own))
            ->assertOk()->assertJsonPath('report.is_provisional', true);
        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $other))
            ->assertNotFound();

        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '100.000'], $this->tenantHeaders($this->tenant))
            ->assertOk();

        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $own))
            ->assertOk()
            ->assertJsonPath('report.is_provisional', false)
            ->assertJsonPath('report.actual_cash_balance', '100.000');
        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $other))
            ->assertNotFound();
    }

    public function test_a_reports_viewer_reads_any_shift_of_a_readable_store_only(): void
    {
        $cashier = $this->createTenantUser($this->tenant, null, ['pos.access']);
        $viewer = $this->createTenantUser($this->tenant, null, ['daily_journal.view', 'reports.view']);
        $shiftA = $this->openShiftFor($cashier, $this->storeA);
        $shiftB = $this->openShiftFor($this->tenantAdmin($this->tenant), $this->storeB);

        $this->getJson('/api/v1/shifts/'.$shiftA.'/z-report', $this->tenantHeaders($this->tenant, $viewer))
            ->assertOk()->assertJsonPath('report.id', $shiftA);

        // Store B is not readable for either of them (even the shift's store is all that differs).
        $this->getJson('/api/v1/shifts/'.$shiftB.'/z-report', $this->tenantHeaders($this->tenant, $viewer))->assertNotFound();
        $this->getJson('/api/v1/shifts/'.$shiftB.'/z-report', $this->tenantHeaders($this->tenant, $cashier))->assertNotFound();
    }

    public function test_the_own_cashier_of_another_stores_shift_still_needs_that_store(): void
    {
        $cashier = $this->createTenantUser($this->tenant, null, ['pos.access']);
        // Opened for the cashier in store B, which the cashier cannot read (default store A only).
        $shiftB = $this->openShiftFor($cashier, $this->storeB);

        $this->getJson('/api/v1/shifts/'.$shiftB.'/z-report', $this->tenantHeaders($this->tenant, $cashier))->assertNotFound();
    }

    public function test_a_user_without_any_shift_permission_gets_403_and_a_guest_401(): void
    {
        $shiftId = $this->openShiftFor($this->tenantAdmin($this->tenant), $this->storeA);
        $nobody = $this->createTenantUser($this->tenant, null, ['items.view']);

        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $nobody))->assertForbidden();
        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantGuestHeaders($this->tenant))->assertUnauthorized();
    }

    public function test_another_tenants_shift_is_not_found(): void
    {
        $shiftId = $this->openShiftFor($this->tenantAdmin($this->tenant), $this->storeA);
        $other = $this->createTenant();
        $otherCashier = $this->createTenantUser($other, 'cashier');

        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($other, $otherCashier))->assertNotFound();
    }

    public function test_a_supervisor_with_close_shift_closes_a_cashiers_shift_by_id(): void
    {
        $cashier = $this->createTenantUser($this->tenant, null, ['pos.access']);
        $supervisor = $this->createTenantUser($this->tenant, null, ['daily_journal.close_shift']);
        $shiftId = $this->openShiftFor($cashier, $this->storeA);

        // Exactly what useDailyJournal.js submitCloseShift() posts.
        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '100.000', 'notes' => ''], $this->tenantHeaders($this->tenant, $supervisor))
            ->assertOk()
            ->assertJsonPath('data.id', $shiftId)
            ->assertJsonPath('data.status', 'closed');

        $this->assertSame('closed', $this->inTenant($this->tenant, fn (): string => (string) CashShift::query()->whereKey($shiftId)->value('status')));
    }

    public function test_closing_by_id_a_shift_of_a_store_without_access_is_not_found_and_leaves_it_open(): void
    {
        $supervisor = $this->createTenantUser($this->tenant, null, ['daily_journal.close_shift']);
        $shiftB = $this->openShiftFor($this->tenantAdmin($this->tenant), $this->storeB);

        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftB, 'actual_cash_balance' => '100.000'], $this->tenantHeaders($this->tenant, $supervisor))
            ->assertNotFound();

        $this->assertSame('open', $this->inTenant($this->tenant, fn (): string => (string) CashShift::query()->whereKey($shiftB)->value('status')));
    }

    public function test_close_without_the_close_permission_is_forbidden(): void
    {
        $cashier = $this->createTenantUser($this->tenant, null, ['pos.access']);
        $shiftId = $this->openShiftFor($cashier, $this->storeA);

        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '100.000'], $this->tenantHeaders($this->tenant, $cashier))
            ->assertForbidden();
    }

    public function test_closing_by_id_a_shift_of_another_accessible_store_than_the_active_one_is_not_found(): void
    {
        // Access to A (default) and B, but the request runs in store A.
        $supervisor = $this->createTenantUser($this->tenant, null, ['daily_journal.close_shift']);
        $this->inTenant($this->tenant, fn () => User::query()->findOrFail($supervisor->getKey())->stores()->attach($this->storeB));
        $shiftB = $this->openShiftFor($this->tenantAdmin($this->tenant), $this->storeB);

        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftB, 'actual_cash_balance' => '100.000'], $this->tenantHeaders($this->tenant, $supervisor, $this->storeA))
            ->assertNotFound();

        $this->assertSame('open', $this->shiftStatus($shiftB));
    }

    public function test_a_seeded_cashier_cannot_read_another_seeded_cashiers_shift_of_the_same_store(): void
    {
        // Owner decision + ROLE-01: without reports.view only the shift's own cashier reads it.
        $cashierA = $this->createTenantUser($this->tenant, 'cashier');
        $cashierB = $this->createTenantUser($this->tenant, 'cashier');
        $shiftId = $this->openShiftFor($cashierA, $this->storeA);

        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $cashierB))
            ->assertNotFound();
        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $cashierA))
            ->assertOk()->assertJsonPath('report.id', $shiftId);
    }

    public function test_a_shift_without_a_user_is_never_own_but_readable_by_a_reports_viewer(): void
    {
        // user_id is nullable since 2026_10_10_310200_make_document_user_id_nullable.
        $posOnly = $this->createTenantUser($this->tenant, null, ['pos.access']);
        $viewer = $this->createTenantUser($this->tenant, null, ['daily_journal.view', 'reports.view']);
        $shiftId = $this->openShiftFor($this->tenantAdmin($this->tenant), $this->storeA);
        $this->inTenant($this->tenant, fn () => CashShift::query()->whereKey($shiftId)->update(['user_id' => null]));

        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $posOnly))->assertNotFound();
        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $viewer))
            ->assertOk()->assertJsonPath('report.id', $shiftId);
    }

    public function test_a_soft_deleted_shift_report_is_not_found(): void
    {
        $shiftId = $this->openShiftFor($this->tenantAdmin($this->tenant), $this->storeA);
        $this->inTenant($this->tenant, fn () => CashShift::query()->findOrFail($shiftId)->delete());

        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant))->assertNotFound();
    }

    public function test_closing_by_id_an_already_closed_shift_is_not_found_and_keeps_its_figures(): void
    {
        $shiftId = $this->openShiftFor($this->tenantAdmin($this->tenant), $this->storeA);
        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '100.000'], $this->tenantHeaders($this->tenant))
            ->assertOk();
        $before = $this->shiftFigures($shiftId);

        $this->postJson('/api/v1/shifts/close', ['shift_id' => $shiftId, 'actual_cash_balance' => '999.000', 'notes' => 'again'], $this->tenantHeaders($this->tenant))
            ->assertNotFound();

        $this->assertSame($before, $this->shiftFigures($shiftId));
        $this->assertSame('100.000', $this->inTenant($this->tenant, fn (): string => CashShift::query()->findOrFail($shiftId)->actual_cash_balance));
    }

    public function test_a_malformed_shift_id_is_rejected_and_closes_nothing(): void
    {
        $shiftId = $this->openShiftFor($this->tenantAdmin($this->tenant), $this->storeA);

        foreach (['abc', 0, -1, '1.5'] as $bad) {
            $this->postJson('/api/v1/shifts/close', ['shift_id' => $bad, 'actual_cash_balance' => '100.000'], $this->tenantHeaders($this->tenant))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['shift_id']);
        }

        $this->assertSame('open', $this->shiftStatus($shiftId));
    }

    public function test_seeded_storekeeper_is_forbidden_and_seeded_accountant_reads_the_report(): void
    {
        $shiftId = $this->openShiftFor($this->tenantAdmin($this->tenant), $this->storeA);
        $storekeeper = $this->createTenantUser($this->tenant, 'storekeeper');
        $accountant = $this->createTenantUser($this->tenant, 'accountant');

        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $storekeeper))->assertForbidden();
        $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->tenantHeaders($this->tenant, $accountant))
            ->assertOk()->assertJsonPath('report.id', $shiftId);
    }

    private function shiftStatus(int $shiftId): string
    {
        return $this->inTenant($this->tenant, fn (): string => (string) CashShift::query()->whereKey($shiftId)->value('status'));
    }

    /** @return array<string, mixed> */
    private function shiftFigures(int $shiftId): array
    {
        // Raw stored values (no casts) so the snapshot compares by value.
        return $this->inTenant($this->tenant, fn (): array => (array) CashShift::query()->whereKey($shiftId)->toBase()
            ->first(['status', 'closed_at', 'expected_cash_balance', 'actual_cash_balance', 'cash_difference', 'notes']));
    }

    private function openShiftFor(User $user, int $storeId): int
    {
        return $this->inTenant($this->tenant, function () use ($user, $storeId): int {
            $this->actingAs(User::query()->findOrFail($user->id));

            return (int) app(ShiftService::class)->openShift('100.000', null, $storeId, (int) $user->id)->id;
        });
    }
}
