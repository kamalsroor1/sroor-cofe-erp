<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sets the units a tenant may use: stored centrally on the tenant (`data.allowed_units`)
 * and mirrored into the tenant's own `inventory_units` setting (inside $tenant->run(), so
 * the tenant context always ends). A failed mirror is logged, not fatal (the central
 * value is the source the super-admin screen reads). Audited in the central log.
 */
final class UpdateTenantUnitsAction
{
    public function __construct(private readonly CentralAuditLogger $auditLogger) {}

    /**
     * @param  list<string>  $units
     * @return list<string>
     */
    public function execute(Tenant $tenant, array $units, CentralUser $operator): array
    {
        $data = $tenant->data ?? [];
        $data['allowed_units'] = $units;
        $tenant->data = $data;
        $tenant->save();

        try {
            $tenant->run(static function () use ($units): void {
                Setting::set('inventory_units', implode(',', $units));
                Setting::clearCache();
            });
        } catch (Throwable $e) {
            Log::warning('Tenant units sync failed', ['tenant' => $tenant->getKey(), 'exception' => $e]);
        }

        $this->auditLogger->record(
            CentralAuditEvent::TenantUnitsUpdated,
            ['units' => $units],
            actor: $operator,
            subject: $tenant,
        );

        return $units;
    }
}
