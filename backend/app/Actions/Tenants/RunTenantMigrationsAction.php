<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\CentralAuditEvent;
use App\Exceptions\TenantProvisioningException;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the pending tenant migrations of ONE tenant on operator request and audits the
 * outcome. The console output (paths, SQL, connection names) goes to the log only; the
 * caller gets a boolean.
 *
 * Only on a `ready` tenant (409 provisioning.workspace_not_ready otherwise): a pending or
 * running tenant is being migrated by ProvisionTenantJob, a failed one has no usable
 * database (its recovery is the provisioning retry).
 */
final class RunTenantMigrationsAction
{
    public function __construct(private readonly CentralAuditLogger $auditLogger) {}

    public function execute(Tenant $tenant, CentralUser $operator): bool
    {
        $tenant = $tenant->fresh() ?? $tenant;
        if (! $tenant->isProvisioned()) {
            throw TenantProvisioningException::requiresReadyWorkspace($tenant->provisioningStatus());
        }

        try {
            $exitCode = Artisan::call('tenants:migrate', ['--tenants' => [(string) $tenant->getKey()]]);
            Log::info('Tenant migrations run by an operator', [
                'tenant' => $tenant->getKey(),
                'exit_code' => $exitCode,
                'output' => Artisan::output(),
            ]);
        } catch (Throwable $e) {
            Log::error('Tenant migrations failed', ['tenant' => $tenant->getKey(), 'exception' => $e]);
            $exitCode = null;
        }

        $succeeded = $exitCode === 0;

        // An attempt: the row must exist whatever happened (a failed run is audited too).
        $this->auditLogger->recordAttempt(
            CentralAuditEvent::TenantMigrationsRun,
            ['status' => $succeeded ? 'succeeded' : 'failed', 'exit_code' => $exitCode],
            actor: $operator,
            subject: $tenant,
        );

        return $succeeded;
    }
}
