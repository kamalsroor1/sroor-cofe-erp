<?php

declare(strict_types=1);

namespace App\Services\Entitlements;

use App\Contracts\TenantFeatureManagerInterface;
use App\DTOs\Entitlements\EntitlementsDTO;
use App\Enums\LimitResource;
use App\Enums\TenantAccessLevel;
use App\Models\Plan;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for what a tenant may use (ENTI-2.2). Pennant is only an API
 * layer on top of it (EntitlementFeatures, Q-E1).
 *
 *  features = plan features (value === true) ∪ features of active add-on lines ∪ the
 *             super-admin overrides in tenants.enabled_features;
 *  limits   = plan limit + Σ (addons.bundled_limits[resource] × line quantity);
 *             a NULL plan limit = unlimited and stays NULL whatever the add-ons say.
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

    public function entitlements(Tenant|string $tenant): EntitlementsDTO
    {
        $tenantId = $this->tenantId($tenant);
        $key = TenantCache::keyFor($tenantId, self::CACHE_NAMESPACE.':'.TenantCache::versionFor($tenantId, self::CACHE_NAMESPACE));

        /** @var array{tenant_id: string, plan_id: int|null, features: list<string>, limits: array<string, int|null>, addons: list<array{key: string, quantity: int}>} $cached */
        $cached = Cache::remember(
            $key,
            max(1, (int) config('entitlements.cache_ttl', 3600)),
            fn (): array => $this->resolve($tenantId)->toArray(),
        );

        return EntitlementsDTO::fromArray($cached);
    }

    /**
     * Uncached resolution straight from the central database.
     */
    public function resolve(string $tenantId): EntitlementsDTO
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

            if ($addon->feature_key !== null && $addon->feature_key !== '') {
                $features[$this->canonicalFeature($addon->feature_key)] = true;
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
     * Same toggle as the legacy TenantFeatureManager, on the canonical key: an override
     * stored under a legacy alias counts as the canonical one and is removed with it.
     *
     * @return list<string>
     */
    public function toggleFeatureOverride(Tenant $tenant, string $featureKey): array
    {
        $canonical = $this->canonicalFeature($featureKey);
        $current = $this->overrides($tenant);
        $enabled = in_array($canonical, array_map(fn (string $key): string => $this->canonicalFeature($key), $current), true);

        $next = array_values(array_filter(
            $current,
            fn (string $key): bool => $this->canonicalFeature($key) !== $canonical,
        ));

        if (! $enabled) {
            $next[] = $canonical;
        }

        $tenant->update(['enabled_features' => $next]);

        // The Tenant observer bumps too; bumping here keeps the toggle correct even when
        // observers are muted (Model::withoutEvents) by the caller.
        $this->forget((string) $tenant->getKey());

        return $next;
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
