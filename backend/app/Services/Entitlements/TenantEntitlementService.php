<?php

declare(strict_types=1);

namespace App\Services\Entitlements;

use App\Contracts\TenantFeatureManagerInterface;
use App\DTOs\Entitlements\EntitlementsDTO;
use App\Enums\FeatureOverrideMode;
use App\Enums\LimitResource;
use App\Enums\TenantAccessLevel;
use App\Models\Plan;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Support\TenantCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for what a tenant may use (ENTI-2.2). Pennant is only an API
 * layer on top of it (EntitlementFeatures, Q-E1).
 *
 *  features = plan features (value === true) ∪ features of active add-on lines ∪ the legacy
 *             grant list tenants.enabled_features, then the per-shop overrides LAST (PLAN-12):
 *             an active `grant` row adds a feature, an active `revoke` row removes it even
 *             when the plan or an add-on gives it;
 *  limits   = plan limit + Σ (addons.bundled_limits[resource] × line quantity);
 *             a NULL plan limit = unlimited and stays NULL whatever the add-ons say;
 *             then an active per-shop limit override REPLACES the figure (NULL = unlimited).
 *
 * Overrides are central rows (TenantOverrides) and work whatever the tenant's lifecycle status.
 * An expired override is ignored when read, and the cached entry never outlives the nearest
 * expiry (TTL is capped), so no cron is needed for correctness.
 *
 * Reads CENTRAL models only (Tenant, Plan, SubscriptionAddon, Addon all pin the central
 * connection), so it answers the same inside a tenant request and in central code.
 *
 * Add-ons count only while (CTO 2026-10-09):
 *  - the line is in force per SubscriptionAddon::activeFor() (its parent subscription is
 *    `active`, or `past_due` during the grace period), AND
 *  - the tenant's lifecycle status grants full access (trial / active / past_due).
 *    A read_only, suspended, cancelled or archived tenant (or an unknown status) keeps its
 *    plan features and overrides but loses every add-on.
 *
 * Unknown tenant, or tenant without a plan: no plan features and every limit 0 (default
 * deny); overrides still apply to an existing tenant.
 *
 * Cache: one entry per tenant under the EXPLICIT tenant scope
 * TenantCache::keyFor($tenantId, 'entitlements:<version>'), invalidated by
 * TenantCache::bumpFor($tenantId, 'entitlements') from EntitlementsCacheObserver (central
 * Subscription / SubscriptionAddon / Plan / Addon / Tenant changes). The explicit scope is
 * what makes a bump from central code visible to the tenant's next request (never the
 * implicit TenantCache::key()/bump(), which would move the central version instead).
 */
final class TenantEntitlementService implements TenantFeatureManagerInterface
{
    public const CACHE_NAMESPACE = 'entitlements';

    public function __construct(private readonly TenantOverrides $overrides) {}

    public function entitlements(Tenant|string $tenant): EntitlementsDTO
    {
        $tenantId = $this->tenantId($tenant);
        $key = TenantCache::keyFor($tenantId, self::CACHE_NAMESPACE.':'.TenantCache::versionFor($tenantId, self::CACHE_NAMESPACE));

        $cached = Cache::get($key);

        // PLAN-11: an entry written before a LimitResource case existed (or by a not yet restarted
        // worker running older code) lacks that limit key, which EntitlementsDTO would read as 0
        // and refuse every creation of the resource. Such an entry is stale: resolve it again.
        if (! is_array($cached) || ! $this->hasEveryLimit($cached)) {
            $expiries = [];
            $cached = $this->resolveWithExpiries($tenantId, $expiries)->toArray();
            Cache::put($key, $cached, $this->cacheTtl($expiries));
        }

        /** @var array{tenant_id: string, plan_id: int|null, features: list<string>, limits: array<string, int|null>, addons: list<array{key: string, quantity: int}>} $cached */
        return EntitlementsDTO::fromArray($cached);
    }

    /**
     * Configured TTL, capped so the entry dies at the nearest override expiry of the SAME resolve
     * pass. Floor with a 1s minimum: the entry never outlives the expiry by a fractional second.
     *
     * @param  list<Carbon>  $expiries
     */
    private function cacheTtl(array $expiries): int
    {
        $ttl = max(1, (int) config('entitlements.cache_ttl', 3600));
        $now = now();

        foreach ($expiries as $expiry) {
            $ttl = min($ttl, max(1, (int) floor($now->diffInSeconds($expiry, false))));
        }

        return $ttl;
    }

    /**
     * @param  array<array-key, mixed>  $cached
     */
    private function hasEveryLimit(array $cached): bool
    {
        $limits = $cached['limits'] ?? null;
        if (! is_array($limits)) {
            return false;
        }

        foreach (LimitResource::values() as $resource) {
            if (! array_key_exists($resource, $limits)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Uncached resolution straight from the central database.
     */
    public function resolve(string $tenantId): EntitlementsDTO
    {
        $expiries = [];

        return $this->resolveWithExpiries($tenantId, $expiries);
    }

    /**
     * @param  list<Carbon>  $expiries  filled with the expiry of every active override that has one
     */
    private function resolveWithExpiries(string $tenantId, array &$expiries): EntitlementsDTO
    {
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant) {
            return EntitlementsDTO::none($tenantId);
        }

        $plan = $tenant->plan_id === null ? null : Plan::query()->find($tenant->plan_id);

        $features = [];
        $limits = array_fill_keys(LimitResource::values(), 0);

        if ($plan instanceof Plan) {
            foreach (($plan->features ?? []) as $key => $enabled) {
                if ($enabled === true) {
                    $features[$this->canonicalFeature((string) $key)] = true;
                }
            }

            foreach (LimitResource::cases() as $resource) {
                $limits[$resource->value] = $resource->fromPlan($plan);
            }
        }

        foreach ($this->overrides($tenant) as $key) {
            $features[$this->canonicalFeature($key)] = true;
        }

        $addons = [];
        foreach ($this->activeAddonLines($tenant) as $line) {
            $addon = $line->addon;
            $quantity = max(0, (int) $line->quantity);

            foreach ($addon->allFeatureKeys() as $addonFeature) {
                $features[$this->canonicalFeature($addonFeature)] = true;
            }

            foreach (($addon->bundled_limits ?? []) as $resource => $perUnit) {
                $resourceKey = (string) $resource;

                // Unknown resource keys are ignored; NULL (unlimited) absorbs any increment.
                if (! array_key_exists($resourceKey, $limits) || $limits[$resourceKey] === null) {
                    continue;
                }

                $limits[$resourceKey] += max(0, (int) $perUnit) * $quantity;
            }

            $addons[] = ['key' => $addon->key, 'quantity' => $quantity];
        }

        // PLAN-12: per-shop overrides are applied last, over plan and add-ons.
        foreach ($this->overrides->activeLimits($tenantId) as $resource => $override) {
            $limits[$resource] = $override->value;
            if ($override->expires_at !== null) {
                $expiries[] = $override->expires_at;
            }
        }

        foreach ($this->overrides->activeFeatures($tenantId) as $key => $override) {
            if ($override->expires_at !== null) {
                $expiries[] = $override->expires_at;
            }

            if ($override->mode === FeatureOverrideMode::Grant) {
                $features[$key] = true;
            } else {
                unset($features[$key]);
            }
        }

        $featureKeys = array_keys($features);
        sort($featureKeys);

        return new EntitlementsDTO(
            tenantId: $tenantId,
            planId: $plan?->id,
            features: array_map('strval', $featureKeys),
            limits: $limits,
            addons: $addons,
        );
    }

    public function isFeatureEnabled(Tenant $tenant, string $featureKey): bool
    {
        return $this->entitlements($tenant)->hasFeature($this->canonicalFeature($featureKey));
    }

    public function limit(Tenant|string $tenant, LimitResource $resource): ?int
    {
        return $this->entitlements($tenant)->limit($resource);
    }

    /**
     * @return array<string, bool>
     */
    public function resolveAllFeatures(Tenant $tenant): array
    {
        $all = [];

        $plan = $tenant->plan_id === null ? null : Plan::query()->find($tenant->plan_id);
        foreach (array_keys($plan === null ? [] : ($plan->features ?? [])) as $key) {
            $all[$this->canonicalFeature((string) $key)] = false;
        }

        foreach ($this->entitlements($tenant)->features as $key) {
            $all[$key] = true;
        }

        ksort($all);

        return $all;
    }

    /**
     * Same toggle as the legacy TenantFeatureManager, now backed by `tenant_feature_overrides`:
     * an existing `grant` row (or a legacy list entry) is removed; otherwise a permanent `grant`
     * row replaces any `revoke` row. Keys are canonical: a legacy alias counts as the canonical one.
     *
     * @return list<string> the shop's granted override keys after the toggle
     */
    public function toggleFeatureOverride(Tenant $tenant, string $featureKey): array
    {
        $canonical = $this->canonicalFeature($featureKey);
        $tenantId = (string) $tenant->getKey();

        DB::connection((new TenantFeatureOverride)->getConnectionName())->transaction(function () use ($tenant, $tenantId, $canonical): void {
            // Serialise with the Set/Remove actions (same lock order: tenant row first).
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->first() ?? $tenant;

            $legacy = $this->overrides($tenant);
            $legacyHas = in_array($canonical, array_map(fn (string $key): string => $this->canonicalFeature($key), $legacy), true);

            $row = TenantFeatureOverride::query()
                ->where('tenant_id', $tenantId)->where('feature_key', $canonical)->lockForUpdate()->first();
            $enabled = $legacyHas || ($row !== null && $row->mode === FeatureOverrideMode::Grant && ! $row->isExpired());

            if ($legacyHas) {
                $tenant->update(['enabled_features' => array_values(array_filter(
                    $legacy,
                    fn (string $key): bool => $this->canonicalFeature($key) !== $canonical,
                ))]);
            }

            if ($enabled) {
                $row?->delete();

                return;
            }

            TenantFeatureOverride::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'feature_key' => $canonical],
                [
                    'mode' => FeatureOverrideMode::Grant->value,
                    'reason' => (string) __('plan_overrides.toggle_reason'),
                    'expires_at' => null,
                    'created_by' => null,
                ],
            );
        });

        // The model observer bumps too; bumping here keeps the toggle correct even when
        // observers are muted (Model::withoutEvents) by the caller.
        $this->forget($tenantId);

        $granted = TenantFeatureOverride::query()
            ->where('tenant_id', $tenantId)->where('mode', FeatureOverrideMode::Grant->value)->pluck('feature_key')->all();
        $granted = array_merge($granted, array_map(fn (string $key): string => $this->canonicalFeature($key), $this->overrides($tenant->refresh())));
        $granted = array_values(array_unique(array_map('strval', $granted)));
        sort($granted);

        return $granted;
    }

    /** Invalidate the tenant's cached entitlements, from any context. */
    public function forget(string $tenantId): void
    {
        TenantCache::bumpFor($tenantId, self::CACHE_NAMESPACE);
        EntitlementFeatures::flush();
    }

    /** Resolve a legacy alias (config/entitlements.php) to the canonical feature key. */
    public function canonicalFeature(string $featureKey): string
    {
        $aliases = (array) config('entitlements.aliases', []);
        $canonical = $aliases[$featureKey] ?? $featureKey;

        return is_string($canonical) && $canonical !== '' ? $canonical : $featureKey;
    }

    /**
     * @return list<string>
     */
    private function overrides(Tenant $tenant): array
    {
        return array_values(array_filter(
            array_map(static fn (mixed $key): string => is_string($key) ? $key : '', $tenant->enabled_features ?? []),
            static fn (string $key): bool => $key !== '',
        ));
    }

    /**
     * Lines that count right now, with their catalog add-on; none when the tenant's
     * lifecycle status does not grant full access.
     *
     * @return iterable<int, SubscriptionAddon>
     */
    private function activeAddonLines(Tenant $tenant): iterable
    {
        if ($tenant->lifecycleStatus()?->accessLevel() !== TenantAccessLevel::Full) {
            return [];
        }

        return SubscriptionAddon::query()
            ->activeFor($tenant)
            ->with('addon')
            ->orderBy('id')
            ->get();
    }

    private function tenantId(Tenant|string $tenant): string
    {
        return $tenant instanceof Tenant ? (string) $tenant->getKey() : $tenant;
    }
}
