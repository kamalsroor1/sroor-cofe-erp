<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\DTOs\Central\SetTenantLimitOverrideDTO;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\TenantLimitOverride;
use App\Services\CentralAuditLogger;
use App\Services\Entitlements\TenantEntitlementService;
use Illuminate\Support\Facades\DB;

/**
 * PLAN-12: give one shop a limit different from its plan (create or replace the row of
 * (tenant, resource)). One CENTRAL transaction with the tenant row locked, so two operators editing
 * the same shop are serialised. The entitlement cache is bumped by the model observer and again
 * after the commit; the change is audited with the old and new value.
 */
final class SetTenantLimitOverrideAction
{
    public function __construct(
        private readonly CentralAuditLogger $audit,
        private readonly TenantEntitlementService $entitlements,
    ) {}

    public function execute(SetTenantLimitOverrideDTO $dto, CentralUser $operator): TenantLimitOverride
    {
        $connection = DB::connection((new TenantLimitOverride)->getConnectionName());

        return $connection->transaction(function () use ($dto, $operator, $connection): TenantLimitOverride {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->whereKey($dto->tenantId)->lockForUpdate()->firstOrFail();
            $tenantId = (string) $tenant->getKey();

            $override = TenantLimitOverride::query()
                ->where('tenant_id', $tenantId)->where('resource', $dto->resource->value)->first();

            $old = $override === null ? null : $this->snapshot($override);

            $override ??= new TenantLimitOverride;
            $override->fill([
                'tenant_id' => $tenantId,
                'resource' => $dto->resource->value,
                'value' => $dto->value,
                'reason' => $dto->reason,
                'expires_at' => $dto->expiresAt,
                'created_by' => (int) $operator->getKey(),
            ]);
            $override->save();

            $this->audit->record(
                CentralAuditEvent::TenantLimitOverrideSet,
                [
                    'resource' => $dto->resource->value,
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
     * @return array{value: int|null, reason: string, expires_at: string|null}
     */
    private function snapshot(TenantLimitOverride $override): array
    {
        return [
            'value' => $override->value,
            'reason' => $override->reason,
            'expires_at' => $override->expires_at?->toIso8601String(),
        ];
    }
}
