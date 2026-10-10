<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Changes a tenant's database coordinates (CENTRAL `tenants.data`) and audits it in the
 * central audit log. The audit row lists which keys changed and the new database name;
 * the username and password values are never recorded.
 */
class UpdateTenantDatabaseConfigAction
{
    private const KEYS = ['tenancy_db_name', 'tenancy_db_username', 'tenancy_db_password'];

    public function __construct(private readonly CentralAuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Tenant $tenant, array $data, ?CentralUser $operator = null): Tenant
    {
        $update = array_intersect_key($data, array_flip(self::KEYS));

        DB::connection($tenant->getConnectionName())->transaction(function () use ($tenant, $update, $operator): void {
            $tenant->update($update);

            $properties = ['changed' => array_keys($update)];
            if (array_key_exists('tenancy_db_name', $update)) {
                $properties['database'] = $update['tenancy_db_name'];
            }

            $this->auditLogger->record(
                CentralAuditEvent::TenantDbConfigUpdated,
                $properties,
                actor: $operator,
                subject: $tenant,
            );
        });

        return $tenant;
    }
}
