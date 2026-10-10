<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * P0-OPS-2 — central Telegram scheduler jobs stay registered but only run when
 * `services.telegram.scheduled_jobs_enabled` is true (default off).
 *
 * OPS-5 — the unencrypted `backup:telegram` dump is gone for good; the encrypted
 * `backup:tenants` backup and the health checks are scheduled and never gated by the
 * Telegram flag.
 */
final class ScheduledTelegramJobsGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(ConsoleKernel::class)->bootstrap();
    }

    /** @return array<string, array{string}> */
    public static function telegramCommands(): array
    {
        return [
            'daily summary' => ['notify:daily-summary'],
            'low stock' => ['notify:low-stock'],
            'overdue shifts' => ['notify:overdue-shifts'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function nonTelegramCommands(): array
    {
        return [
            'queue work' => ['queue:work'],
            'queue restart' => ['queue:restart'],
            'pulse clear' => ['pulse:clear'],
            'tenant backups' => ['backup:tenants'],
            'file backups' => ['backup:run --only-files'],
            'health check' => ['health:check'],
            'schedule heartbeat' => ['health:schedule-check-heartbeat'],
        ];
    }

    #[DataProvider('telegramCommands')]
    public function test_telegram_job_is_still_registered(string $command): void
    {
        $this->assertCount(1, $this->eventsFor($command));
    }

    #[DataProvider('telegramCommands')]
    public function test_telegram_job_does_not_run_when_flag_is_false(string $command): void
    {
        config(['services.telegram.scheduled_jobs_enabled' => false]);

        $this->assertFalse($this->singleEvent($command)->filtersPass($this->app));
    }

    #[DataProvider('telegramCommands')]
    public function test_telegram_job_does_not_run_when_flag_is_missing(string $command): void
    {
        $telegram = config('services.telegram', []);
        unset($telegram['scheduled_jobs_enabled']);
        config(['services.telegram' => $telegram]);

        $this->assertFalse($this->singleEvent($command)->filtersPass($this->app));
    }

    #[DataProvider('telegramCommands')]
    public function test_telegram_job_runs_when_flag_is_true(string $command): void
    {
        config(['services.telegram.scheduled_jobs_enabled' => true]);

        $this->assertTrue($this->singleEvent($command)->filtersPass($this->app));
    }

    #[DataProvider('nonTelegramCommands')]
    public function test_non_telegram_jobs_are_unaffected_by_the_flag(string $command): void
    {
        config(['services.telegram.scheduled_jobs_enabled' => false]);

        $this->assertTrue($this->singleEvent($command)->filtersPass($this->app));
    }

    public function test_telegram_database_backup_is_removed(): void
    {
        $this->assertSame([], $this->eventsFor('backup:telegram'));
        $this->assertArrayNotHasKey('backup:telegram', $this->app->make(ConsoleKernel::class)->all());
        $this->assertFalse(class_exists('App\Console\Commands\SendTelegramDatabaseBackupCommand'));
        $this->assertFalse(class_exists('App\Jobs\SendTelegramDatabaseBackupJob'));
    }

    public function test_tenant_backups_run_daily_without_overlapping(): void
    {
        $event = $this->singleEvent('backup:tenants');

        $this->assertSame('30 1 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_config_default_is_off(): void
    {
        $this->assertFalse(
            (bool) config('services.telegram.scheduled_jobs_enabled', false),
            'services.telegram.scheduled_jobs_enabled must default to false (check TELEGRAM_SCHEDULED_JOBS_ENABLED in .env if this fails locally).'
        );
    }

    /** @return list<ScheduledEvent> */
    private function eventsFor(string $command): array
    {
        $events = $this->app->make(Schedule::class)->events();

        return array_values(array_filter(
            $events,
            static fn (ScheduledEvent $event): bool => str_contains((string) $event->command, $command),
        ));
    }

    private function singleEvent(string $command): ScheduledEvent
    {
        $events = $this->eventsFor($command);
        $this->assertCount(1, $events, "Expected exactly one scheduled event for [{$command}].");

        return $events[0];
    }
}
