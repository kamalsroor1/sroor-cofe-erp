<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

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
 *  - PERF-02: the viewer scope. null = the shared view of everyone with the same store and
 *    permission flags; a per-user / per-shift view passes its own token (e.g. "u12",
 *    "u12.shift34") and gets a key no shared view and no other viewer can ever produce;
 *  - the tenant business date (a new day never reuses yesterday's figures);
 *  - the locale (labels are translated inside the cached arrays);
 *  - a namespace version, bumped by invalidate() when the tenant settings change (currency
 *    label, low-stock threshold, cutoff).
 *
 * PERF-02 (owner decision: up to ~1 minute of staleness is acceptable, no invalidation on
 * sale): TTL_SECONDS = 60. A sale shows up within a minute; the live blocks show it at once.
 * remember() lets ONE request compute a cold key while the concurrent ones wait for its result
 * (a cache lock), instead of every request recomputing the same aggregates at the same time.
 */
final class DashboardCache
{
    public const TTL_SECONDS = 60;

    /** Upper bound of one computation; the lock frees itself after this even if a worker dies. */
    public const LOCK_SECONDS = 15;

    /**
     * How long a request waits for another request's computation before computing it itself.
     * Short on purpose: a waiter holds a PHP-FPM worker. A cold computation takes ~0.1-0.2 s on
     * a 50k-invoice tenant. The wait sleeps through Illuminate\Support\Sleep, so tests fake it.
     */
    public const LOCK_WAIT_SECONDS = 2;

    private const NAMESPACE = 'dashboard';

    private const VIEWER_PATTERN = '/^[A-Za-z0-9_.-]{1,64}$/';

    public static function key(?int $storeId, bool $canViewFinancials, bool $canViewCost, string $businessDate, string $locale, ?string $viewer = null): string
    {
        if ($viewer !== null && preg_match(self::VIEWER_PATTERN, $viewer) !== 1) {
            throw new InvalidArgumentException('DashboardCache viewer scope must match '.self::VIEWER_PATTERN.'.');
        }

        return TenantCache::key(implode(':', [
            self::NAMESPACE,
            'v'.TenantCache::version(self::NAMESPACE),
            's'.($storeId ?? 'all'),
            'f'.($canViewFinancials ? '1' : '0'),
            'c'.($canViewCost ? '1' : '0'),
            // '*' is outside VIEWER_PATTERN: no viewer token can ever equal the shared scope.
            'w'.($viewer ?? '*'),
            $businessDate,
            $locale,
        ]));
    }

    /**
     * Cache::remember() with single-flight computation of a cold key.
     *
     * @template TValue of array
     *
     * @param  Closure(): TValue  $compute
     * @return TValue
     */
    public static function remember(string $key, Closure $compute): array
    {
        $cached = Cache::get($key);
        if (is_array($cached)) {
            /** @var TValue $cached */
            return $cached;
        }

        if (! Cache::getStore() instanceof LockProvider) {
            return Cache::remember($key, self::TTL_SECONDS, $compute);
        }

        try {
            // The winner computes and stores; the others wake up and read the stored value. If
            // the computation throws, block() releases the lock and nothing is stored.
            return Cache::lock($key.':lock', self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, fn (): array => Cache::remember($key, self::TTL_SECONDS, $compute));
        } catch (LockTimeoutException) {
            // Never fail or stall the dashboard because another worker is slow: compute it here.
            return $compute();
        }
    }

    /**
     * Drop every cached dashboard of the current tenant (all stores, users, days, locales).
     */
    public static function invalidate(): void
    {
        TenantCache::bump(self::NAMESPACE);
    }
}
