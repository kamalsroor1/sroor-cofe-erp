<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Jobs\ArchiveTenantJob;
use Tests\TestCase;

/**
 * OPS-9: ArchiveTenantJob (timeout 1800 s, one attempt) must run on a connection whose
 * retry_after exceeds its timeout, consumed by a Horizon supervisor that is really started in
 * production. Otherwise the 90 s default re-releases the reserved job and a second archive
 * (final backup) starts while the first is still running.
 */
final class ArchiveQueueRetryAfterTest extends TestCase
{
    public function test_long_connection_retry_after_exceeds_the_archive_timeout(): void
    {
        $timeout = (int) config('tenant_lifecycle.archive.job_timeout_seconds');

        $this->assertGreaterThan($timeout, (int) config('queue.connections.long.retry_after'));
        $this->assertSame('redis', config('queue.connections.long.driver'));
    }

    public function test_stale_window_stays_above_the_job_timeout(): void
    {
        $this->assertGreaterThan(
            (int) config('tenant_lifecycle.archive.job_timeout_seconds'),
            (int) config('tenant_lifecycle.archive.stale_after_minutes') * 60,
        );
    }

    public function test_job_uses_the_configured_connection_and_queue(): void
    {
        config([
            'tenant_lifecycle.archive.queue_connection' => 'long',
            'tenant_lifecycle.archive.queue' => 'long',
        ]);

        $job = new ArchiveTenantJob('t-1', null, 'suspended');

        $this->assertSame('long', $job->connection);
        $this->assertSame('long', $job->queue);
    }

    public function test_horizon_supervisor_consumes_the_long_queue_with_a_matching_timeout(): void
    {
        $supervisor = config('horizon.defaults.supervisor-long');

        $this->assertSame('long', $supervisor['connection']);
        $this->assertContains(config('queue.connections.long.queue'), $supervisor['queue']);
        $this->assertGreaterThan((int) config('tenant_lifecycle.archive.job_timeout_seconds'), $supervisor['timeout']);
        $this->assertLessThan((int) config('queue.connections.long.retry_after'), $supervisor['timeout']);
        $this->assertSame(1, $supervisor['tries']);
    }
}
