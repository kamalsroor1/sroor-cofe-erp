<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\CentralAuditEvent;
use App\Enums\TenantArchiveState;
use App\Enums\TenantStatus;
use App\Exceptions\TenantLifecycleException;
use App\Exceptions\TenantProvisioningException;
use App\Jobs\ArchiveTenantJob;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Services\CentralAuditLogger;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * DELETE /api/v1/super-admin/tenants/{id} (OPS-9, CTO Q1): "delete" = ASYNC ARCHIVE.
 *
 * Synchronous preconditions, in one central transaction with the tenant row locked:
 *   purged / purging              → 409 subscription.tenant_purged
 *   workspace not provisioned     → 409 provisioning.workspace_not_ready
 *   already archived              → 409 subscription.already_archived
 *   archive queued / running      → 409 subscription.archive_in_progress (unless stale)
 *   not suspended / cancelled     → 409 subscription.archive_not_allowed + TenantArchiveRefused
 *   not the status the operator saw (expected_status) → 409 subscription.status_conflict
 * Then archive_state = queued, audit TenantArchiveRequested, and ArchiveTenantJob is
 * dispatched AFTER the commit. The status itself only changes in the job, once a final
 * backup is proven (TransitionTenantStatusAction guard). Nothing is ever dropped.
 */
final class RequestTenantArchiveAction
{
    public function __construct(
        private readonly CentralAuditLogger $audit,
    ) {}

    public function execute(string $tenantId, CentralUser $operator, TenantStatus $expectedStatus, ?string $note): Tenant
    {
        $note = $note === null || trim($note) === '' ? null : trim($note);
        $connection = $this->centralConnection();

        $tenant = $connection->transaction(function () use ($tenantId, $operator, $expectedStatus, $note, $connection): Tenant {
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->first();
            if (! $tenant instanceof Tenant) {
                throw (new ModelNotFoundException)->setModel(Tenant::class, [$tenantId]);
            }

            $this->assertArchivable($tenant, $operator, $expectedStatus);

            $tenant->forceFill([
                'archive_state' => TenantArchiveState::Queued,
                'archive_requested_at' => now(),
                'archive_error_code' => null,
            ])->save();

            $this->audit->record(
                CentralAuditEvent::TenantArchiveRequested,
                ['expected_status' => $expectedStatus->value, 'note' => $note],
                $operator,
                $tenant,
            );

            $key = (string) $tenant->getKey();
            $operatorId = (int) $operator->getKey();
            $connection->afterCommit(static function () use ($key, $operatorId, $expectedStatus, $note): void {
                ArchiveTenantJob::dispatch($key, $operatorId, $expectedStatus->value, $note);
            });

            return $tenant;
        });

        return $tenant->refresh();
    }

    private function assertArchivable(Tenant $tenant, CentralUser $operator, TenantStatus $expectedStatus): void
    {
        if ($tenant->isPurged()) {
            throw TenantLifecycleException::tenantPurged();
        }

        if (! $tenant->provisioningStatus()->isReady()) {
            throw TenantProvisioningException::requiresReadyWorkspace($tenant->provisioningStatus());
        }

        $fromRaw = (string) $tenant->status;
        $from = $tenant->lifecycleStatus();

        if ($from === TenantStatus::Archived) {
            throw TenantLifecycleException::alreadyArchived();
        }

        $state = $tenant->archive_state;
        if ($state !== null && $state->isInProgress() && ! $this->isStale($tenant)) {
            throw TenantLifecycleException::archiveInProgress($state);
        }

        if ($from === null || ! $from->canBeArchived()) {
            // Survives the rollback of this transaction (recordAttempt semantics).
            $this->audit->recordAttempt(
                CentralAuditEvent::TenantArchiveRefused,
                ['from' => $fromRaw, 'actor' => 'super_admin', 'reason' => 'archive_not_allowed'],
                $operator,
                $tenant,
            );

            throw TenantLifecycleException::archiveNotAllowed($fromRaw);
        }

        if ($from !== $expectedStatus) {
            throw TenantLifecycleException::statusConflict([$expectedStatus], $fromRaw);
        }
    }

    /** A queued / running request with no progress for archive.stale_after_minutes (lost dispatch, dead worker). */
    private function isStale(Tenant $tenant): bool
    {
        $requested = $tenant->archive_requested_at;
        $minutes = max(1, (int) config('tenant_lifecycle.archive.stale_after_minutes', 60));

        return $requested === null || $requested->lt(now()->subMinutes($minutes));
    }

    private function centralConnection(): Connection
    {
        return DB::connection((new TenantLifecycleEvent)->getConnectionName());
    }
}
