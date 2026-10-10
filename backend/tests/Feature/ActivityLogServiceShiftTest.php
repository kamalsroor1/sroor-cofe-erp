<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CashShift;
use App\Models\Tenant;
use App\Services\ActivityLogService;
use Tests\TenantTestCase;

class ActivityLogServiceShiftTest extends TenantTestCase
{
    public function test_log_shift_records_the_stored_cash_difference(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            $shift = $this->closedShift($tenant, 'SHF-ALS-01', '500.000', '1495.000', '-5.000');

            $log = app(ActivityLogService::class)->logShift('shift_closed', $shift->fresh(), 'closed');

            $this->assertSame('500.000', $log->properties['opening_cash_balance']);
            $this->assertSame('1495.000', $log->properties['actual_cash_balance']);
            $this->assertSame('-5.000', $log->properties['difference']);
        });
    }

    public function test_shift_log_is_written_to_the_current_tenant_only(): void
    {
        $tenant = $this->createTenant();
        $other = $this->createTenant();

        $logId = $this->inTenant($tenant, function () use ($tenant): int {
            $shift = $this->closedShift($tenant, 'SHF-ALS-ISO', '100.000', '90.000', '-10.000');

            return (int) app(ActivityLogService::class)->logShift('shift_closed', $shift->fresh(), 'closed')->id;
        });

        $this->inTenant($tenant, fn () => $this->assertDatabaseHas('activity_logs', ['id' => $logId, 'module' => 'shifts']));
        $this->inTenant($other, fn () => $this->assertSame(0, ActivityLog::query()->where('module', 'shifts')->count()));
    }

    private function closedShift(Tenant $tenant, string $number, string $opening, string $actual, string $difference): CashShift
    {
        return CashShift::create([
            'user_id' => $this->tenantAdmin($tenant)->id,
            'store_id' => $this->tenantStore($tenant)->id,
            'shift_number' => $number,
            'status' => 'closed',
            'opened_at' => now()->subHour(),
            'closed_at' => now(),
            'opening_cash_balance' => $opening,
            'expected_cash_balance' => '1500.000',
            'actual_cash_balance' => $actual,
            'cash_difference' => $difference,
        ]);
    }
}
