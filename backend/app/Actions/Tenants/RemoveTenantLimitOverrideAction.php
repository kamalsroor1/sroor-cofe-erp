<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\CentralAuditEvent;
use App\Enums\LimitResource;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\TenantLimitOverride;
use App\Services\CentralAuditLogger;
use App\Services\Entitlements\TenantEntitlementService;
use Illuminate\Support\Facades\DB;

/**
 * PLAN-12: remove a shop's limit override so the plan (+ add-ons) figure applies again.
 * Returns false when the shop had no override for the resource. Audited with the old value.
 */
final class RemoveTenantLimitOverrideAction
{
    public function __construct(
        private readonly CentralAuditLogger $audit,
        private readonly TenantEntitlementService $entitlements,
    ) {}

    public function execute(string $tenantId, LimitResource $resource, CentralUser $operator): bool
    {
        $connection = DB::connection((new TenantLimitOverride)->getConnectionName());

        return $connection->transaction(function () use ($tenantId, $resource, $operator, $connection): bool {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();

            $override = TenantLimitOverride::query()
                ->where('tenant_id', (string) $tenant->getKey())->where('resource', $resource->value)->first();

            if ($override === null) {
                return false;
            }

            $old = [
                'value' => $override->value,
                'reason' => $override->reason,
                'expires_at' => $override->expires_at?->toIso8601String(),
            ];
            $override->delete();

            $this->audit->record(
                CentralAuditEvent::TenantLimitOverrideRemoved,
                ['resource' => $resource->value, 'old' => $old, 'new' => null],
                $operator,
                $tenant,
            );

            $connection->afterCommit(fn () => $this->entitlements->forget((string) $tenant->getKey()));

            return true;
        });
    }
}
