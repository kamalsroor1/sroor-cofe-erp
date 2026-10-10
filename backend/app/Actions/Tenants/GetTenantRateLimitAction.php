<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Models\TenantRateLimitOverride;

/**
 * GET /api/v1/super-admin/tenants/{id}/rate-limits (IDEN-4.6 ext): the configured default
 * limits plus the tenant's current active raise, read fresh from the central DB (not the
 * limiter cache of TenantRateLimitOverrides, so the console sees a raise immediately).
 */
final class GetTenantRateLimitAction
{
    /**
     * @return array{defaults: array<string, int>, override: TenantRateLimitOverride|null}
     */
    public function execute(Tenant $tenant): array
    {
        $override = TenantRateLimitOverride::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->active()
            ->latest('id')
            ->first();

        return [
            'defaults' => $this->defaults(),
            'override' => $override,
        ];
    }

    /**
     * Default limit per override column, from config (never below 1).
     *
     * @return array<string, int>
     */
    private function defaults(): array
    {
        $defaults = [];

        foreach (TenantRateLimitOverride::LIMITS as $column => $configKey) {
            $defaults[$column] = max(1, (int) config($configKey, 1));
        }

        return $defaults;
    }
}
