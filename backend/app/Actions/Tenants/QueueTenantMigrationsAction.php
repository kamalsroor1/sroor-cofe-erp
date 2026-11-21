<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\CentralAuditEvent;
use App\Exceptions\TenantProvisioningException;
use App\Jobs\RunTenantMigrationsJob;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * OPS-25: POST /super-admin/tenants/{id}/run-migrations queues the run instead of running it
 * inside the HTTP request. Only on a `ready` tenant (409 provisioning.workspace_not_ready
 * otherwise). Writes the `tenant_migrations_queued` audit line with a run id and dispatches
 * RunTenantMigrationsJob after the central commit; the job writes `tenant_migrations_run`
 * with the same run id and the outcome.
 */
final class QueueTenantMigrationsAction
{
    public function __construct(private readonly CentralAuditLogger $auditLogger) {}

    /** @return string the run id (UUID) returned to the operator */
    public function execute(Tenant $tenant, CentralUser $operator): string
    {
        $tenant = $tenant->fresh() ?? $tenant;
        if (! $tenant->isProvisioned()) {
            throw TenantProvisioningException::requiresReadyWorkspace($tenant->provisioningStatus());
        }

        $runId = (string) Str::uuid();
        $tenantId = (string) $tenant->getKey();
        $operatorId = (int) $operator->getKey();

        DB::connection((string) config('tenancy.database.central_connection'))->transaction(function () use ($tenant, $operator, $runId, $tenantId, $operatorId): void {
            $this->auditLogger->record(
                CentralAuditEvent::TenantMigrationsQueued,
                ['run_id' => $runId],
                actor: $operator,
                subject: $tenant,
            );

            RunTenantMigrationsJob::dispatch($tenantId, $operatorId, $runId)->afterCommit();
        });

        return $runId;
    }
}
