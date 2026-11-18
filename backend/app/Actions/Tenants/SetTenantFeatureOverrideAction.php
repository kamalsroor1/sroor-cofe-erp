<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\DTOs\Central\SetTenantFeatureOverrideDTO;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Services\CentralAuditLogger;
use App\Services\Entitlements\TenantEntitlementService;
use Illuminate\Support\Facades\DB;

/**
 * PLAN-12: grant or revoke one feature for one shop (create or replace the row of
 * (tenant, feature)). A `revoke` removes the feature even when the plan or an add-on gives it.
 * One CENTRAL transaction with the tenant row locked; the cache is bumped by the model observer
 * and again after the commit; audited with the old and new mode.
 */
final class SetTenantFeatureOverrideAction
{
    public function __construct(
        private readonly CentralAuditLogger $audit,
        private readonly TenantEntitlementService $entitlements,
    ) {}

    public function execute(SetTenantFeatureOverrideDTO $dto, CentralUser $operator): TenantFeatureOverride
    {
        $connection = DB::connection((new TenantFeatureOverride)->getConnectionName());

        return $connection->transaction(function () use ($dto, $operator, $connection): TenantFeatureOverride {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->whereKey($dto->tenantId)->lockForUpdate()->firstOrFail();
            $tenantId = (string) $tenant->getKey();

            $override = TenantFeatureOverride::query()
                ->where('tenant_id', $tenantId)->where('feature_key', $dto->featureKey)->first();

            $old = $override === null ? null : $this->snapshot($override);

            $override ??= new TenantFeatureOverride;
            $override->fill([
                'tenant_id' => $tenantId,
                'feature_key' => $dto->featureKey,
                'mode' => $dto->mode->value,
                'reason' => $dto->reason,
                'expires_at' => $dto->expiresAt,
                'created_by' => (int) $operator->getKey(),
            ]);
            $override->save();

            $this->audit->record(
                CentralAuditEvent::TenantFeatureOverrideSet,
                [
                    'feature' => $dto->featureKey,
                    'old' => $old,
                    'new' => $this->snapshot($override),
                    'reason' => $dto->reason,
                ],
                $operator,
                $tenant,
            );

            $connection->afterCommit(fn () => $this->entitlements->forget($tenantId));

            return $override->load('creator');
        });
    }

    /**
     * @return array{mode: string, reason: string, expires_at: string|null}
     */
    private function snapshot(TenantFeatureOverride $override): array
    {
        return [
            'mode' => $override->mode->value,
            'reason' => $override->reason,
            'expires_at' => $override->expires_at?->toIso8601String(),
        ];
    }
}
