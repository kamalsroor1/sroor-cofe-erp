<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\DispatchTenantTelegramAlertsCommand;
use App\Jobs\CheckOverdueShiftsJob;
use App\Jobs\SendDailySummaryReportJob;
use App\Models\CashShift;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\Settings\SettingSecrets;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
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
        $a = $this->createTenant();
        $b = $this->createTenant();
        $this->configureOwnTelegram($a);
        $this->configureOwnTelegram($b);
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$a->id, $b->id]])->assertSuccessful();
        Bus::assertDispatchedTimes(SendDailySummaryReportJob::class, 2);

        // Second run in the same business day: nothing new.
        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$a->id, $b->id]])->assertSuccessful();
        Bus::assertDispatchedTimes(SendDailySummaryReportJob::class, 2);

        $this->assertNotNull($this->inTenant($a, fn () => Setting::get(DispatchTenantTelegramAlertsCommand::KEY_LAST_SUMMARY_DATE)));
    }

    public function test_a_shop_without_its_own_telegram_never_uses_the_platform_bot(): void
    {
        // The platform bot and chat are configured (setUp), as in production for backup alerts.
        // A shop that never set up Telegram must not have its sales posted into the platform chat.
        $tenant = $this->createTenant();
        $this->openShift($tenant, 'SHF-OPS24-0');
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertNothingDispatched();
    }

    public function test_a_shop_with_only_part_of_its_own_telegram_is_skipped(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, function (): void {
            Setting::set('telegram_notifications_enabled', '1');
            Setting::set('telegram_bot_token', SettingSecrets::encrypt('shop-token'));
        });
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertNothingDispatched();
    }

    public function test_summary_is_for_the_finished_business_day(): void
    {
        $tenant = $this->createTenant();
        $this->configureOwnTelegram($tenant);
        $expected = $this->inTenant($tenant, fn (): string => CarbonImmutable::parse(app(TenantClock::class)->today())->subDay()->toDateString());
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertDispatched(SendDailySummaryReportJob::class, fn (SendDailySummaryReportJob $job): bool => $job->date === $expected);
    }

    public function test_overdue_shift_is_alerted_only_once(): void
    {
        $tenant = $this->createTenant();
        $this->configureOwnTelegram($tenant);
        $this->openShift($tenant, 'SHF-OPS24-1');
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();
        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertDispatchedTimes(CheckOverdueShiftsJob::class, 1);
    }

    public function test_shift_below_the_configured_limit_is_not_alerted(): void
    {
        $tenant = $this->createTenant();
        $this->configureOwnTelegram($tenant);
        $this->inTenant($tenant, fn () => Setting::set('overdue_shift_alert_hours', '48'));
        $this->openShift($tenant, 'SHF-OPS24-2');
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertNotDispatched(CheckOverdueShiftsJob::class);
    }

    public function test_a_failing_tenant_does_not_stop_the_others(): void
    {
        $broken = $this->createTenant();
        $good = $this->createTenant();
        $this->configureOwnTelegram($broken);
        $this->configureOwnTelegram($good);
        // Break only the broken tenant's data (the shifts table the overdue check reads), leaving
        // its database file in place so the harness can still clean it up.
        $this->inTenant($broken, fn () => Schema::drop('cash_shifts'));
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$broken->id, $good->id]])->assertFailed();

        // The broken shop fails after its summary was queued; the good shop is still processed.
        Bus::assertDispatchedTimes(SendDailySummaryReportJob::class, 2);
    }

    /** The shop's OWN bot, chat and explicit opt-in (what the settings screen saves). */
    private function configureOwnTelegram(Tenant $tenant): void
    {
        $this->inTenant($tenant, function (): void {
            Setting::set('telegram_notifications_enabled', '1');
            Setting::set('telegram_bot_token', SettingSecrets::encrypt('shop-token'));
            Setting::set('telegram_chat_id', '777');
        });
    }

    private function openShift(Tenant $tenant, string $number): void
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
