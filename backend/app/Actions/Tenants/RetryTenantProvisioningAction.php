<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\CentralAuditEvent;
use App\Enums\TenantProvisioningStatus;
use App\Exceptions\TenantProvisioningException;
use App\Jobs\ProvisionTenantJob;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use App\Services\TenantProvisionerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin retry of a tenant provisioning (OPS-2).
 *
 * Under the tenant row lock: allowed for a `failed` tenant, and for a `pending`/`running`
 * one that is STALE (no activity for longer than the job's unique window: lost dispatch,
 * dead worker; Tenant::provisioningIsStale()). Anything else is 409 (in flight, or ready).
 * The stored first-admin seed must still be readable (409 otherwise): the job reads it from
 * the row itself. The row goes back to `pending` (error code and timestamps cleared,
 * attempts kept as history), the retry is audited, the job's unique / overlap locks are
 * released (nothing can still hold them legitimately) and ProvisionTenantJob is dispatched
 * after the commit.
 */
final class RetryTenantProvisioningAction
{
    public function __construct(
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(string $tenantId, ?CentralUser $actor): Tenant
    {
        $tenant = DB::connection(TenantProvisionerService::centralConnection())->transaction(function () use ($tenantId, $actor): Tenant {
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->first();
            if (! $tenant instanceof Tenant) {
                throw (new ModelNotFoundException)->setModel(Tenant::class, [$tenantId]);
            }

            $status = $tenant->provisioningStatus();
            $stale = $tenant->provisioningIsStale(TenantProvisionerService::provisioningWindowSeconds());
            if (! $status->canRetry($stale)) {
                throw TenantProvisioningException::retryNotAllowed($status);
            }

            if (TenantProvisionerService::storedPasswordHash($tenant) === null) {
                throw TenantProvisioningException::retryUnavailable($status);
            }

            $previousError = $tenant->provisioning_error_code;

            $tenant->forceFill([
                'provisioning_status' => TenantProvisioningStatus::Pending,
                'provisioning_error_code' => null,
                'provisioning_started_at' => null,
                'provisioned_at' => null,
            ])->save();

            $this->auditLogger->record(
                CentralAuditEvent::TenantProvisioningStarted,
                [
                    'retry' => true,
                    'previous_status' => $status->value,
                    'previous_error_code' => $previousError,
                    'attempts' => (int) $tenant->provisioning_attempts,
                ],
                actor: $actor,
                subject: $tenant,
            );

            $key = (string) $tenant->getTenantKey();
            DB::connection(TenantProvisionerService::centralConnection())->afterCommit(static function () use ($key): void {
                ProvisionTenantJob::releaseLocks($key);
                ProvisionTenantJob::dispatch($key);
            });

            return $tenant;
        });

        return $tenant->refresh();
    }
}
