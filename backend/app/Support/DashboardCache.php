<?php

declare(strict_types=1);

namespace App\Support;

/**
 * PERF-01: short-lived cache of the dashboard's aggregate blocks (metrics, analytics, top
 * items, low stock). The live blocks (active shift, recent invoices) are never cached.
 *
 * The key holds EVERY dimension that changes the cached figures, so a cached value can never
 * be served outside the scope it was computed for:
 *  - the tenant (TenantCache::key prefix "t:{tenantId}:"; the cache store is shared by every
 *    tenant because CacheTenancyBootstrapper is disabled);
 *  - the resolved store filter (a store id, or "all" for a verified all-stores view);
 *  - whether the user may see financial figures (reports.view: profit, margin, debts) and cost
 *    (items.view_cost);
 *  - the tenant business date (a new day never reuses yesterday's figures);
 *  - the locale (labels are translated inside the cached arrays);
 *  - a namespace version, bumped by invalidate() when the tenant settings change (currency
 *    label, low-stock threshold, cutoff).
 * Sales posted meanwhile show up within TTL_SECONDS.
 */
final class DashboardCache
{
    public const TTL_SECONDS = 30;

    private const NAMESPACE = 'dashboard';

    public static function key(?int $storeId, bool $canViewFinancials, bool $canViewCost, string $businessDate, string $locale): string
    {
        return TenantCache::key(implode(':', [
            self::NAMESPACE,
            'v'.TenantCache::version(self::NAMESPACE),
            's'.($storeId ?? 'all'),
            'f'.($canViewFinancials ? '1' : '0'),
            'c'.($canViewCost ? '1' : '0'),
            $businessDate,
            $locale,
        ]));
    }

    /**
     * Drop every cached dashboard of the current tenant (all stores, users, days, locales).
     */
    public static function invalidate(): void
    {
        TenantCache::bump(self::NAMESPACE);
    }
}
