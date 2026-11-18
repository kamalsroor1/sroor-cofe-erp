<?php

declare(strict_types=1);

namespace App\Services\Entitlements;

use App\Enums\LimitResource;
use App\Models\TenantFeatureOverride;
use App\Models\TenantLimitOverride;
use Illuminate\Support\Carbon;

/**
 * PLAN-12: reads the per-shop overrides that TenantEntitlementService applies last. Central
 * models only, so it answers the same inside a tenant request and in central code. Expired rows
 * are never returned: expiry is decided at read time (no cron needed for correctness).
 */
final class TenantOverrides
{
    /**
     * Active limit overrides of the tenant, keyed by LimitResource value.
     *
     * @return array<string, TenantLimitOverride>
     */
    public function activeLimits(string $tenantId): array
    {
        $rows = [];

        foreach (TenantLimitOverride::query()->where('tenant_id', $tenantId)->active()->get() as $row) {
            if (LimitResource::tryFrom($row->resource) !== null) {
                $rows[$row->resource] = $row;
            }
        }

        return $rows;
    }

    /**
     * Active feature overrides of the tenant, keyed by feature key (rows are written canonical by the override actions).
     *
     * @return array<string, TenantFeatureOverride>
     */
    public function activeFeatures(string $tenantId): array
    {
        $rows = [];

        foreach (TenantFeatureOverride::query()->where('tenant_id', $tenantId)->active()->get() as $row) {
            $rows[$row->feature_key] = $row;
        }

        return $rows;
    }

    /** The earliest future expiry among the tenant's overrides; null when none can expire. */
    public function nextExpiry(string $tenantId): ?Carbon
    {
        $candidates = array_filter([
            TenantLimitOverride::query()->where('tenant_id', $tenantId)->where('expires_at', '>', now())->min('expires_at'),
            TenantFeatureOverride::query()->where('tenant_id', $tenantId)->where('expires_at', '>', now())->min('expires_at'),
        ], static fn (mixed $value): bool => $value !== null);

        if ($candidates === []) {
            return null;
        }

        $earliest = null;
        foreach ($candidates as $value) {
            $at = Carbon::parse((string) $value);
            if ($earliest === null || $at->lessThan($earliest)) {
                $earliest = $at;
            }
        }

        return $earliest;
    }
}
