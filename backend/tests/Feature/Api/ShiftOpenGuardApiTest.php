<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\StorePosSetting;
use App\Models\Tenant;
use App\Services\ShiftService;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TenantTestCase;

/**
 * SHOP-06 / BE-19: one open shift per branch (store-row lock + UNIQUE open_guard; SHIFT-01: per branch in shared mode, per cashier and branch by default) and an
 * idempotent close. The real parallel race runs on MySQL (MysqlShiftAndTransferRaceTest).
 */
final class ShiftOpenGuardApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
    }

    public function test_opening_a_shift_sets_the_guard_and_a_second_open_is_a_409(): void
    {
        $headers = $this->tenantHeaders($this->tenant);

        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '100'], $headers)->assertStatus(201);
        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '100'], $headers)->assertStatus(409);

        $this->inTenant($this->tenant, function (): void {
            $shift = CashShift::query()->sole();
            $this->assertSame('store:'.$this->storeId.':user:'.$shift->user_id, $shift->open_guard);
        });
    }

    public function test_the_database_refuses_a_second_open_shift_for_the_same_branch(): void
    {
        $this->openViaService();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->inTenant($this->tenant, fn () => CashShift::query()->create([
            'user_id' => $this->tenantAdmin($this->tenant)->id,
            'store_id' => $this->storeId,
            'shift_number' => 'SHIFT-RACE-2',
            'status' => 'open',
            'shift_mode' => 'per_cashier',
            'open_guard' => 'store:'.$this->storeId.':user:'.$this->tenantAdmin($this->tenant)->id,
            'opened_at' => now(),
            'opening_cash_balance' => '0.000',
        ]));
    }

    public function test_in_shared_mode_the_database_refuses_a_second_open_shift_for_the_branch_whoever_opens_it(): void
    {
        $this->inTenant($this->tenant, fn () => StorePosSetting::query()->updateOrCreate(['store_id' => $this->storeId], ['shift_mode' => 'shared']));
        $first = $this->openViaService();
        $this->inTenant($this->tenant, fn () => $this->assertSame('store:'.$this->storeId, CashShift::query()->findOrFail($first)->open_guard));

        $this->expectException(UniqueConstraintViolationException::class);

        $other = $this->createTenantUser($this->tenant, 'cashier');
        $this->inTenant($this->tenant, fn () => CashShift::query()->create([
            'user_id' => $other->id,
            'store_id' => $this->storeId,
            'shift_number' => 'SHIFT-RACE-SHARED',
            'status' => 'open',
            'shift_mode' => 'shared',
            'open_guard' => 'store:'.$this->storeId,
            'opened_at' => now(),
            'opening_cash_balance' => '0.000',
        ]));
    }

    public function test_closing_clears_the_guard_so_the_next_shift_can_open(): void
    {
        $first = $this->openViaService();
        $this->inTenant($this->tenant, fn () => app(ShiftService::class)->closeShift(CashShift::query()->findOrFail($first), '10.000'));

        $this->inTenant($this->tenant, fn () => $this->assertNull(CashShift::query()->findOrFail($first)->open_guard));

        $second = $this->openViaService();
        $this->assertNotSame($first, $second);
    }

    public function test_close_is_idempotent(): void
    {
        $id = $this->openViaService('50.000');

        $this->inTenant($this->tenant, function () use ($id): void {
            $service = app(ShiftService::class);
            // Two closes of the same (stale) model, as from two devices.
            $stale = CashShift::query()->findOrFail($id);
            $service->closeShift(CashShift::query()->findOrFail($id), '50.000', 'first');
            $again = $service->closeShift($stale, '999.000', 'second');

            $this->assertSame('closed', $again->status);
            $row = CashShift::query()->findOrFail($id);
            $this->assertSame('50.000', (string) $row->actual_cash_balance);
            $this->assertSame('first', $row->notes);
            $this->assertSame('0.000', (string) $row->cash_difference);
        });
    }

    public function test_deleting_an_open_shift_frees_the_branch(): void
    {
        $id = $this->openViaService();
        $this->inTenant($this->tenant, fn () => CashShift::query()->findOrFail($id)->delete());

        $this->assertNotSame($id, $this->openViaService());
    }

    private function openViaService(string $float = '0.000'): int
    {
        return $this->inTenant($this->tenant, function () use ($float): int {
            $this->actingAs($this->tenantAdmin($this->tenant));

            return (int) app(ShiftService::class)->openShift($float, null, $this->storeId)->id;
        });
    }
}
