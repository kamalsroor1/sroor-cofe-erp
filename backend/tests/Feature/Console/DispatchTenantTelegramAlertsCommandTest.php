<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\DispatchTenantTelegramAlertsCommand;
use App\Jobs\CheckOverdueShiftsJob;
use App\Jobs\SendDailySummaryReportJob;
use App\Models\CashShift;
use App\Models\Setting;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Tests\TenantTestCase;

/** OPS-24: per-tenant daily summary and overdue-shift alert dispatch. */
final class DispatchTenantTelegramAlertsCommandTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.chat_id' => '12345',
        ]);
    }

    public function test_each_tenant_gets_its_own_summary_job_once_per_business_day(): void
    {
        Bus::fake();
        $a = $this->createTenant();
        $b = $this->createTenant();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$a->id, $b->id]])->assertSuccessful();
        Bus::assertDispatchedTimes(SendDailySummaryReportJob::class, 2);

        // Second run in the same business day: nothing new.
        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$a->id, $b->id]])->assertSuccessful();
        Bus::assertDispatchedTimes(SendDailySummaryReportJob::class, 2);

        $this->assertNotNull($this->inTenant($a, fn () => Setting::get(DispatchTenantTelegramAlertsCommand::KEY_LAST_SUMMARY_DATE)));
    }

    public function test_tenant_without_telegram_is_skipped_without_error(): void
    {
        Bus::fake();
        config(['services.telegram.bot_token' => null, 'services.telegram.chat_id' => null]);
        $tenant = $this->createTenant();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertNothingDispatched();
    }

    public function test_summary_is_for_the_finished_business_day(): void
    {
        Bus::fake();
        $tenant = $this->createTenant();
        $expected = $this->inTenant($tenant, fn (): string => CarbonImmutable::parse(app(TenantClock::class)->today())->subDay()->toDateString());

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertDispatched(SendDailySummaryReportJob::class, fn (SendDailySummaryReportJob $job): bool => $job->date === $expected);
    }

    public function test_overdue_shift_is_alerted_only_once(): void
    {
        Bus::fake();
        $tenant = $this->createTenant();
        $this->openShift($tenant, 'SHF-OPS24-1');

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();
        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertDispatchedTimes(CheckOverdueShiftsJob::class, 1);
    }

    public function test_shift_below_the_configured_limit_is_not_alerted(): void
    {
        Bus::fake();
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn () => Setting::set('overdue_shift_alert_hours', '48'));
        $this->openShift($tenant, 'SHF-OPS24-2');

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertNotDispatched(CheckOverdueShiftsJob::class);
    }

    public function test_a_failing_tenant_does_not_stop_the_others(): void
    {
        Bus::fake();
        $broken = $this->createTenant();
        $good = $this->createTenant();
        $broken->update(['tenancy_db_name' => 'missing_db_'.uniqid()]);

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$broken->id, $good->id]])->assertFailed();

        Bus::assertDispatchedTimes(SendDailySummaryReportJob::class, 1);
    }

    private function openShift(\App\Models\Tenant $tenant, string $number): void
    {
        $this->inTenant($tenant, fn () => CashShift::create([
            'user_id' => $this->tenantAdmin($tenant)->id,
            'store_id' => $this->tenantStore($tenant)->id,
            'shift_number' => $number,
            'status' => 'open',
            'opened_at' => now()->subHours(30),
            'opening_cash_balance' => '100.000',
        ]));
    }
}
