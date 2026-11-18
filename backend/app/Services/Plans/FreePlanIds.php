<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Models\Plan;
use App\Support\PublicCatalogCache;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * PLAN-10 review: the ids of the permanent free plans (`plans.is_free`), the ONE answer to "is this
 * tenant on a free plan?" for every lifecycle reader: Tenant::lifecycleSnapshot() (EnsureTenantActive,
 * login, /me, the billing screen, the subscription timeline) and the daily sweep. Before, the request
 * path read the flag only when the `plan` relation happened to be loaded, so a free tenant could be
 * derived past_due / read-only on requests while the sweep treated it as free.
 *
 * Read on every tenant request, so the list is cached in the CENTRAL scope under the public-catalog
 * version: PublicCatalogObserver bumps it whenever a Plan is saved or deleted, and the short TTL
 * covers query-builder writes (catalog seeders, data migrations). A cache failure only costs a query.
 */
final class FreePlanIds
{
    private const CACHE_SUFFIX = 'free_plan_ids';

    public function contains(?int $planId): bool
    {
        return $planId !== null && in_array($planId, $this->all(), true);
    }

    /**
     * @return list<int>
     */
    public function all(): array
    {
        try {
            $key = TenantCache::centralKey(PublicCatalogCache::NAMESPACE.':'.TenantCache::centralVersion(PublicCatalogCache::NAMESPACE).':'.self::CACHE_SUFFIX);
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return array_values(array_map('intval', $cached));
            }
        } catch (Throwable) {
            return $this->query();
        }

        $ids = $this->query();

        try {
            Cache::put($key, $ids, PublicCatalogCache::TTL_SECONDS);
        } catch (Throwable) {
            // A cache write failure only costs a query on the next read.
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function query(): array
    {
        try {
            return Plan::query()
                ->where('is_free', true)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
        } catch (Throwable) {
            // Central DB / column not reachable (fresh install before PLAN-10): nothing is free.
            return [];
        }
    }
}
