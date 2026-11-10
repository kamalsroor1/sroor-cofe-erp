<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Health\Channels\PlatformTelegramChannel;
use App\Health\Notifications\PlatformCheckFailedNotification;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Http;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Result;
use Tests\TestCase;

/**
 * OPS-7: /up for external uptime probes, a scheduled heartbeat ping (dead-man switch) and a
 * Telegram text alert for the platform operator when a health check fails.
 */
final class UptimeAlertingTest extends TestCase
{
    public function test_up_endpoint_answers_200_without_authentication_or_tenant(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_heartbeat_is_a_noop_without_a_url(): void
    {
        Http::fake();
        config(['health.heartbeat.url' => null]);

        $this->artisan('ops:heartbeat')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_heartbeat_pings_the_configured_url(): void
    {
        Http::fake(['hb.example.test/*' => Http::response('ok', 200)]);
        config(['health.heartbeat.url' => 'https://hb.example.test/ping/abc']);

        $this->artisan('ops:heartbeat')->assertSuccessful();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://hb.example.test/ping/abc' && $request->method() === 'GET');
    }

    public function test_heartbeat_fails_without_leaking_the_url_when_the_monitor_is_down(): void
    {
        Http::fake(['hb.example.test/*' => Http::response('no', 500)]);
        config(['health.heartbeat.url' => 'https://hb.example.test/ping/secret-key']);

        $this->artisan('ops:heartbeat')->assertFailed()->doesntExpectOutputToContain('secret-key');
    }

    public function test_heartbeat_runs_every_minute(): void
    {
        $this->app->make(ConsoleKernel::class)->bootstrap();
        $events = array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            static fn (ScheduledEvent $e): bool => str_contains((string) $e->command, 'ops:heartbeat'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events[0]->expression);
    }

    public function test_failed_check_alert_goes_to_mail_and_platform_telegram(): void
    {
        config(['health.telegram.bot_token' => '123:TEST', 'health.telegram.chat_id' => '-100']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $notification = new PlatformCheckFailedNotification([
            Result::make('Disk is 95% full')->check(CacheCheck::new())->failed('Disk is 95% full'),
        ]);

        $this->assertSame(['mail', PlatformTelegramChannel::class], $notification->via());

        (new PlatformTelegramChannel)->send(new \stdClass, $notification);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'api.telegram.org/bot123:TEST/sendMessage')
            && $request['chat_id'] === '-100'
            && str_contains($request['text'], 'Disk is 95% full'));
    }

    public function test_telegram_channel_is_skipped_when_unconfigured(): void
    {
        config(['health.telegram.bot_token' => null, 'health.telegram.chat_id' => null]);
        Http::fake();

        $notification = new PlatformCheckFailedNotification([]);

        $this->assertSame(['mail'], $notification->via());
        (new PlatformTelegramChannel)->send(new \stdClass, $notification);
        Http::assertNothingSent();
    }

    public function test_telegram_outage_never_breaks_the_health_run(): void
    {
        config(['health.telegram.bot_token' => '123:TEST', 'health.telegram.chat_id' => '-100']);
        Http::fake(['api.telegram.org/*' => Http::response('', 500)]);

        (new PlatformTelegramChannel)->send(new \stdClass, new PlatformCheckFailedNotification([]));

        $this->addToAssertionCount(1);
    }

    public function test_health_config_notifies_with_the_platform_notification(): void
    {
        $this->assertSame(PlatformCheckFailedNotification::class, array_key_first(config('health.notifications.notifications')));
    }
}
