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
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/** OPS-24: per-tenant daily summary and overdue-shift alert dispatch. */
final class DispatchTenantTelegramAlertsCommandTest extends TenantTestCase
{
    private const PLATFORM_TOKEN = 'platform-token-ops24';

    protected function setUp(): void
    {
        parent::setUp();

        // The dangerous case: the PLATFORM bot is configured and enabled. A shop must never use it.
        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => self::PLATFORM_TOKEN,
            'services.telegram.chat_id' => '12345',
        ]);
    }

    public function test_each_tenant_gets_its_own_summary_job_once_per_business_day(): void
    {
        // Create the tenants first: provisioning dispatches its own JobPipeline, which Bus::fake would swallow.
        $a = $this->shopWithTelegram();
        $b = $this->shopWithTelegram();
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$a->id, $b->id]])->assertSuccessful();
        Bus::assertDispatchedTimes(SendDailySummaryReportJob::class, 2);

        // Second run in the same business day: nothing new.
        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$a->id, $b->id]])->assertSuccessful();
        Bus::assertDispatchedTimes(SendDailySummaryReportJob::class, 2);

        $this->assertNotNull($this->inTenant($a, fn () => Setting::get(DispatchTenantTelegramAlertsCommand::KEY_LAST_SUMMARY_DATE)));
    }

    public function test_tenant_without_telegram_is_skipped_without_error(): void
    {
        // Create the tenant first: provisioning dispatches its own JobPipeline, which Bus::fake would record.
        $tenant = $this->createTenant();
        $this->openShift($tenant, 'SHF-OPS24-NONE');
        Bus::fake();

        // The platform bot IS configured (setUp): the shop must still get nothing.
        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertNothingDispatched();
    }

    public function test_tenant_with_only_its_own_token_gets_nothing(): void
    {
        $tenant = $this->createTenant();
        $this->configureShop($tenant, 'shop-token-only', null);
        $this->openShift($tenant, 'SHF-OPS24-TOK');
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertNothingDispatched();
    }

    public function test_tenant_with_only_its_own_chat_id_gets_nothing(): void
    {
        $tenant = $this->createTenant();
        $this->configureShop($tenant, null, '-100777');
        $this->openShift($tenant, 'SHF-OPS24-CHAT');
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertNothingDispatched();
    }

    public function test_tenant_that_switched_telegram_off_gets_nothing(): void
    {
        $tenant = $this->shopWithTelegram();
        $this->inTenant($tenant, fn () => Setting::set('telegram_notifications_enabled', '0'));
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertNothingDispatched();
    }

    public function test_suspended_and_archived_tenants_get_nothing(): void
    {
        $suspended = $this->shopWithTelegram();
        $archived = $this->shopWithTelegram();
        $active = $this->shopWithTelegram();
        $suspended->forceFill(['status' => 'suspended'])->save();
        $archived->forceFill(['status' => 'archived'])->save();
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$suspended->id, $archived->id, $active->id]])->assertSuccessful();

        Bus::assertDispatchedTimes(SendDailySummaryReportJob::class, 1);
    }

    public function test_jobs_send_with_the_shop_bot_and_chat_never_the_platform_bot(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $tenant = $this->shopWithTelegram('shop-token-A', '-100555');
        $this->openShift($tenant, 'SHF-OPS24-HTTP');

        $this->inTenant($tenant, function (): void {
            app()->call([new SendDailySummaryReportJob('2026-01-01'), 'handle']);
            app()->call([new CheckOverdueShiftsJob(24, null), 'handle']);
        });

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/botshop-token-A/') && $request['chat_id'] === '-100555');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), self::PLATFORM_TOKEN) || $request['chat_id'] === '12345');
    }

    public function test_jobs_for_a_shop_without_its_own_bot_send_nothing(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $tenant = $this->createTenant();
        $this->configureShop($tenant, 'shop-token-B', null);
        $this->openShift($tenant, 'SHF-OPS24-NOHTTP');

        $this->inTenant($tenant, function (): void {
            app()->call([new SendDailySummaryReportJob('2026-01-01'), 'handle']);
            app()->call([new CheckOverdueShiftsJob(24, null), 'handle']);
        });

        Http::assertNothingSent();
    }

    public function test_summary_is_for_the_finished_business_day(): void
    {
        $tenant = $this->shopWithTelegram();
        Bus::fake();
        $expected = $this->inTenant($tenant, fn (): string => CarbonImmutable::parse(app(TenantClock::class)->today())->subDay()->toDateString());

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertDispatched(SendDailySummaryReportJob::class, fn (SendDailySummaryReportJob $job): bool => $job->date === $expected);
    }

    public function test_overdue_shift_is_alerted_only_once(): void
    {
        $tenant = $this->shopWithTelegram();
        $this->openShift($tenant, 'SHF-OPS24-1');
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();
        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertDispatchedTimes(CheckOverdueShiftsJob::class, 1);
    }

    public function test_shift_below_the_configured_limit_is_not_alerted(): void
    {
        $tenant = $this->shopWithTelegram();
        $this->inTenant($tenant, fn () => Setting::set('overdue_shift_alert_hours', '48'));
        $this->openShift($tenant, 'SHF-OPS24-2');
        Bus::fake();

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$tenant->id]])->assertSuccessful();

        Bus::assertNotDispatched(CheckOverdueShiftsJob::class);
    }

    public function test_a_failing_tenant_does_not_stop_the_others(): void
    {
        $broken = $this->shopWithTelegram();
        $good = $this->shopWithTelegram();
        $this->openShift($good, 'SHF-OPS24-GOOD');
        Bus::fake();
        // Break only the broken tenant's data (the cash_shifts table the overdue check reads; its
        // settings must stay readable or it is simply skipped as "no Telegram"), leaving its
        // database file in place so the harness can still clean it up.
        $this->inTenant($broken, fn () => Schema::drop('cash_shifts'));

        $this->artisan('notify:dispatch-tenant-alerts', ['--tenants' => [$broken->id, $good->id]])->assertFailed();

        // The broken shop fails on its overdue check; the good one still gets both of its jobs.
        Bus::assertDispatchedTimes(CheckOverdueShiftsJob::class, 1);
        Bus::assertDispatched(SendDailySummaryReportJob::class);
    }

    /** A shop with its OWN bot token (stored encrypted, like SettingSecrets writes it) and chat id. */
    private function shopWithTelegram(string $token = 'shop-token-ops24', string $chatId = '-100999'): Tenant
    {
        $tenant = $this->createTenant();
        $this->configureShop($tenant, $token, $chatId);

        return $tenant;
    }

    private function configureShop(Tenant $tenant, ?string $token, ?string $chatId): void
    {
        $this->inTenant($tenant, function () use ($token, $chatId): void {
            if ($token !== null) {
                Setting::set('telegram_bot_token', SettingSecrets::encrypt($token));
            }
            if ($chatId !== null) {
                Setting::set('telegram_chat_id', $chatId);
            }
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
