<?php

declare(strict_types=1);

namespace App\Services\Signup;

use App\Models\Plan;
use App\Services\Branding\PlatformBranding;
use App\Support\PublicCatalogCache;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * PLAN-11 (fixes a PLAN-10 gap): the plan a SELF-SIGNUP trial falls back to when it ends unpaid.
 *
 *  - the platform setting `trial_fallback_plan_id` when it is set (the operator's choice wins; the
 *    plan must still be an active permanent free plan at use time, else there is no fallback and
 *    the trial goes read-only like before);
 *  - otherwise the catalog plan with slug `free` when it is active and `is_free`, so the owner
 *    decision (an unpaid self-signup trial keeps working on the free plan) holds out of the box;
 *  - otherwise none.
 *
 * planId() is read on every tenant request (Tenant::lifecycleSnapshot), so the catalog default is
 * cached under the central public-catalog version (PublicCatalogObserver bumps it on any plan
 * change; the short TTL covers query-builder writes of the catalog seeders).
 */
final class TrialFallbackPlan
{
    public const CATALOG_SLUG = 'free';

    private const CACHE_SUFFIX = 'trial_fallback_default';

    public function __construct(private readonly PlatformBranding $platform) {}

    /** The effective fallback plan id (setting, else the catalog free plan), or null = none. */
    public function planId(): ?int
    {
        return $this->platform->get()->trialFallbackPlanId ?? $this->catalogDefaultId();
    }

    /** The effective fallback plan, only while it is an active permanent free plan. */
    public function plan(): ?Plan
    {
        $id = $this->planId();

        return $id === null ? null : Plan::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->where('is_free', true)
            ->first();
    }

    private function catalogDefaultId(): ?int
    {
        try {
            $key = TenantCache::centralKey(PublicCatalogCache::NAMESPACE.':'.TenantCache::centralVersion(PublicCatalogCache::NAMESPACE).':'.self::CACHE_SUFFIX);
            $cached = Cache::get($key);
            if (is_int($cached)) {
                return $cached > 0 ? $cached : null;
            }
        } catch (Throwable) {
            return $this->queryCatalogDefaultId();
        }

        $id = $this->queryCatalogDefaultId();

        try {
            // 0 = "no catalog free plan" (a cached miss, so it is not queried on every request).
            Cache::put($key, $id ?? 0, PublicCatalogCache::TTL_SECONDS);
        } catch (Throwable) {
            // A cache write failure only costs a query on the next read.
        }

        return $id;
    }

    private function queryCatalogDefaultId(): ?int
    {
        try {
            $id = Plan::query()
                ->where('slug', self::CATALOG_SLUG)
                ->where('is_active', true)
                ->where('is_free', true)
                ->value('id');
        } catch (Throwable) {
            // Central DB / table not reachable (fresh install): no fallback.
            return null;
        }

        return $id === null ? null : (int) $id;
    }
}
