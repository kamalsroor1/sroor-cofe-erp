<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Tenants\RunTenantMigrationsAction;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * OPS-25: the asynchronous half of POST /super-admin/tenants/{id}/run-migrations
 * (QueueTenantMigrationsAction dispatches it after the central commit). `tenants:migrate` on a
 * large shop takes minutes, far past the 120 s HTTP limit that used to kill it mid-ALTER.
 *
 * Central only: it never initialises tenancy itself (`tenants:migrate --tenants=<id>` does,
 * for that tenant alone). Runs on the archive lane (`long` on the VPS, retry_after above
 * the 900 s timeout). One attempt: a half-applied migration is never retried blindly; the operator
 * re-queues once the audit line shows the failure. Concurrent runs for the same tenant are
 * refused by RunTenantMigrationsAction's per-tenant lock (audited as `skipped`).
 */
final class RunTenantMigrationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const TIMEOUT_SECONDS = 900;

    public int $tries = 1;

    public int $timeout = self::TIMEOUT_SECONDS;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $tenantId,
        public readonly ?int $centralUserId,
        public readonly string $runId,
    ) {
        // The `long` lane of ArchiveTenantJob (retry_after 2000 s, Horizon supervisor-long), not
        // the single provisioning worker: a 15-minute migrate there would hold up self-signups.
        $connection = config('tenant_lifecycle.archive.queue_connection');
        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }

        $queue = config('tenant_lifecycle.archive.queue');
        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    public function handle(RunTenantMigrationsAction $action): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant instanceof Tenant) {
            // Deleted between the request and the run: nothing to migrate, but the run is closed.
            app(CentralAuditLogger::class)->recordAttempt(
                CentralAuditEvent::TenantMigrationsRun,
                ['status' => 'skipped', 'exit_code' => null, 'run_id' => $this->runId, 'reason' => 'tenant_missing'],
                actor: $this->operator(),
                tenantId: $this->tenantId,
            );

            return;
        }

        $action->execute($tenant, $this->operator(), $this->runId);
    }

    /** Timeout or killed worker: the run is audited as failed (the outcome line must always exist). */
    public function failed(?Throwable $exception): void
    {
        try {
            // A timed-out ALTER may still be running on the MySQL server: check the process
            // list before re-queueing (the per-tenant lock expires 60 s after the timeout).
            app(CentralAuditLogger::class)->recordAttempt(
                CentralAuditEvent::TenantMigrationsRun,
                ['status' => 'failed', 'exit_code' => null, 'run_id' => $this->runId, 'reason' => 'job_failed'],
                actor: $this->operator(),
                tenantId: $this->tenantId,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function operator(): ?CentralUser
    {
        if ($this->centralUserId === null) {
            return null;
        }
        $operator = CentralUser::query()->find($this->centralUserId);

        return $operator instanceof CentralUser ? $operator : null;
    }
}
