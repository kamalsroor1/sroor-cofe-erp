<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Services\CentralAuditLogger;
use App\Services\Entitlements\TenantEntitlementService;
use Illuminate\Support\Facades\DB;

/**
 * PLAN-12: remove a shop's feature override (grant or revoke) so plan and add-ons decide again.
 * `$featureKey` is canonical. Returns false when the shop had no override for it. Audited.
 */
final class RemoveTenantFeatureOverrideAction
{
    public function __construct(
        private readonly CentralAuditLogger $audit,
        private readonly TenantEntitlementService $entitlements,
    ) {}

    public function execute(string $tenantId, string $featureKey, CentralUser $operator): bool
    {
        $connection = DB::connection((new TenantFeatureOverride)->getConnectionName());

        return $connection->transaction(function () use ($tenantId, $featureKey, $operator, $connection): bool {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();

            $override = TenantFeatureOverride::query()
                ->where('tenant_id', (string) $tenant->getKey())->where('feature_key', $featureKey)->first();

            if ($override === null) {
                return false;
            }

            $old = [
                'mode' => $override->mode->value,
                'reason' => $override->reason,
                'expires_at' => $override->expires_at?->toIso8601String(),
            ];
            $override->delete();

            $this->audit->record(
                CentralAuditEvent::TenantFeatureOverrideRemoved,
                ['feature' => $featureKey, 'old' => $old, 'new' => null],
                $operator,
                $tenant,
            );

            $connection->afterCommit(fn () => $this->entitlements->forget((string) $tenant->getKey()));

            return true;
        });
    }
}
