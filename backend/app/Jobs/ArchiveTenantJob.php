<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Tenants\ArchiveTenantAction;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Exceptions\TenantLifecycleException;
use App\Models\CentralUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OPS-9: the asynchronous half of DELETE /super-admin/tenants/{id} (RequestTenantArchiveAction
 * dispatches it after the central commit). Runs ArchiveTenantAction: final backup proven,
 * then `archived`. Central only: it never initialises tenancy itself (the backup engine
 * dumps the tenant inside $tenant->run()).
 *
 * One attempt (a backup is not retried blindly), unique per tenant, 30 min timeout. A
 * failure (including a timeout / killed worker) leaves the status untouched and sets
 * archive_state = failed (ArchiveTenantAction::markFailed); the operator may request again.
 */
final class ArchiveTenantJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $tenantId,
        public readonly ?int $centralUserId,
        public readonly string $expectedStatus,
        public readonly ?string $note = null,
    ) {
        $this->timeout = max(60, (int) config('tenant_lifecycle.archive.job_timeout_seconds', 1800));

        $connection = config('tenant_lifecycle.archive.queue_connection');
        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }

        $queue = config('tenant_lifecycle.archive.queue');
        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    public function uniqueId(): string
    {
        return $this->tenantId;
    }

    public function uniqueFor(): int
    {
        return $this->timeout + 60;
    }

    public function handle(ArchiveTenantAction $action): void
    {
        $causer = $this->centralUserId === null ? null : CentralUser::query()->find($this->centralUserId);
        $expected = TenantStatus::tryFrom($this->expectedStatus);

        try {
            $action->execute(
                $this->tenantId,
                $causer instanceof CentralUser ? TenantLifecycleActor::SuperAdmin : TenantLifecycleActor::System,
                $expected === null ? [] : [$expected],
                $causer instanceof CentralUser ? $causer : null,
                $this->note,
                ['central_user_id' => $this->centralUserId],
            );
        } catch (TenantLifecycleException $e) {
            if ($e->errorCode() === 'subscription.archive_in_progress') {
                // Another archive / a purge holds the tenant-lifecycle lock: its own state wins.
                Log::warning('ArchiveTenantJob skipped: the tenant lifecycle lock is held', ['tenant_id' => $this->tenantId]);

                return;
            }

            throw $e;
        }
    }

    /** Timeout, killed worker or an unexpected error: never leave the request `running`. */
    public function failed(?Throwable $exception): void
    {
        try {
            ArchiveTenantAction::markFailed($this->tenantId, ArchiveTenantAction::UNKNOWN);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
