<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\CentralAuditEvent;
use App\Exceptions\TenantProvisioningException;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

/**
 * Changes a tenant's database coordinates (CENTRAL `tenants.data`) and audits it in the
 * central audit log. The audit row lists which keys changed and the new database name;
 * the username and password values are never recorded.
 *
 * Only on a `ready` tenant (409 provisioning.workspace_not_ready otherwise): changing the
 * database name or username under a pending/running/failed provisioning would point the
 * job (and its cleanup) at another database or account. The row is locked while checked
 * and updated. The password is stored encrypted (Tenant::sealDatabasePassword()).
 */
class UpdateTenantDatabaseConfigAction
{
    private const KEYS = ['tenancy_db_name', 'tenancy_db_username', 'tenancy_db_password'];

    public function __construct(private readonly CentralAuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Tenant $tenant, #[SensitiveParameter] array $data, ?CentralUser $operator = null): Tenant
    {
        $update = array_intersect_key($data, array_flip(self::KEYS));

        if (array_key_exists('tenancy_db_password', $update)) {
            $password = $update['tenancy_db_password'];
            $update['tenancy_db_password'] = Tenant::sealDatabasePassword(is_string($password) ? $password : null);
        }

        return DB::connection($tenant->getConnectionName())->transaction(function () use ($tenant, $update, $operator): Tenant {
            $locked = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isProvisioned()) {
                throw TenantProvisioningException::requiresReadyWorkspace($locked->provisioningStatus());
            }

            $locked->update($update);

            $properties = ['changed' => array_keys($update)];
            if (array_key_exists('tenancy_db_name', $update)) {
                $properties['database'] = $update['tenancy_db_name'];
            }

            $this->auditLogger->record(
                CentralAuditEvent::TenantDbConfigUpdated,
                $properties,
                actor: $operator,
                subject: $locked,
            );

            return $locked;
        });
    }
}
