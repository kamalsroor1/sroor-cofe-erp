<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Tests\TestCase;

/**
 * OPS-8: the scheduler's `queue:work` runs only where no resident worker exists
 * (config queue.scheduler_worker); with Horizon (redis) it is off, so two consumers never
 * race on the same queue.
 */
final class SchedulerQueueWorkerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(ConsoleKernel::class)->bootstrap();
    }

    public function test_queue_worker_is_skipped_when_horizon_owns_the_queue(): void
    {
        config(['queue.scheduler_worker' => false]);

        $this->assertFalse($this->event('queue:work')->filtersPass($this->app));
        $this->assertFalse($this->event('queue:restart')->filtersPass($this->app));
    }

    public function test_queue_worker_runs_on_hosts_without_a_resident_worker(): void
    {
        config(['queue.scheduler_worker' => true]);

        $this->assertTrue($this->event('queue:work')->filtersPass($this->app));
        $this->assertTrue($this->event('queue:restart')->filtersPass($this->app));
    }

    public function test_default_is_off_for_redis_and_on_for_database_queue(): void
    {
        $default = static function (?string $connection, ?string $override = null): bool {
            $env = static fn (string $k) => $k === 'QUEUE_CONNECTION' ? $connection : $override;

            return $env('SCHEDULER_QUEUE_WORKER') === null
                ? ! in_array($env('QUEUE_CONNECTION') ?? 'database', ['redis', 'sync', 'null'], true)
                : filter_var($env('SCHEDULER_QUEUE_WORKER'), FILTER_VALIDATE_BOOL);
        };

        $this->assertFalse($default('redis'));
        $this->assertTrue($default('database'));
        $this->assertTrue($default('redis', 'true'));
        $this->assertFalse($default('database', 'false'));

        $source = (string) file_get_contents(config_path('queue.php'));
        $this->assertStringContainsString("['redis', 'sync', 'null']", $source);
    }

    private function event(string $command): ScheduledEvent
    {
        $events = array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            static fn (ScheduledEvent $e): bool => str_contains((string) $e->command, $command),
        ));
        $this->assertCount(1, $events);

        return $events[0];
    }
}
