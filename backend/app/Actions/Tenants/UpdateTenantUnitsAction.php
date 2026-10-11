<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\CentralAuditEvent;
use App\Exceptions\TenantProvisioningException;
use App\Models\CentralUser;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use App\Services\Platform\PlatformUnitsCatalog;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sets the units a tenant may use: stored centrally on the tenant as the virtual
 * attribute `allowed_units` (kept by stancl in the `data` JSON column) and written into the
 * tenant's own `inventory_units` setting, which is what the tenant actually enforces (SETG-10).
 *
 * BRND-2 fixes:
 *  - the central value was assigned as `$tenant->data = [...]`, which stancl's VirtualColumn
 *    drops on save (encodeAttributes() unsets the `data` attribute), so it was never stored;
 *  - the tenant write was logged-and-ignored on failure, so the console reported success
 *    while the tenant kept its old list. Now everything runs in ONE central
 * transaction: lock the tenant row → update `data` → central audit → write the tenant
 * setting LAST (inside $tenant->run(), so the tenant context always ends): every central
 * write that can still fail comes before the one write the central rollback cannot undo.
 * A failing tenant write rolls the central change back and is rethrown (the controller
 * answers a translated 422).
 *
 * Only on a `ready` tenant (409 provisioning.workspace_not_ready otherwise): before that
 * there is no tenant database to write.
 */
final class UpdateTenantUnitsAction
{
    public function __construct(private readonly CentralAuditLogger $auditLogger) {}

    /**
     * @param  list<string>  $units
     * @return list<string>
     *
     * @throws Throwable when the tenant database cannot be written (nothing is saved)
     */
    public function execute(Tenant $tenant, array $units, CentralUser $operator): array
    {
        $units = PlatformUnitsCatalog::parse(implode(',', $units));
        $connection = (string) $tenant->getConnectionName();

        return DB::connection($connection)->transaction(function () use ($tenant, $units, $operator): array {
            /** @var Tenant $locked */
            $locked = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isProvisioned()) {
                throw TenantProvisioningException::requiresReadyWorkspace($locked->provisioningStatus());
            }

            // Virtual attribute: encoded into the `data` JSON column on save.
            $locked->setAttribute('allowed_units', $units);
            $locked->save();

            $this->auditLogger->record(
                CentralAuditEvent::TenantUnitsUpdated,
                ['units' => $units],
                actor: $operator,
                subject: $locked,
            );

            // LAST: the only write the central rollback cannot undo.
            $locked->run(static function () use ($units): void {
                Setting::set('inventory_units', implode(',', $units));
                Setting::clearCache();
            });

            return $units;
        });
    }
}
