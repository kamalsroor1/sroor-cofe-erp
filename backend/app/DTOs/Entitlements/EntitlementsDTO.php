<?php

declare(strict_types=1);

namespace App\DTOs\Entitlements;

use App\Enums\LimitResource;

/**
 * What one tenant may use right now (ENTI-2.2), as resolved by TenantEntitlementService.
 *
 *  - features: canonical feature keys that are ON (plan ∪ active add-ons ∪ overrides),
 *    sorted, aliases already resolved (blender.access → mixes.manage);
 *  - limits: one entry per LimitResource value; null = unlimited, otherwise
 *    plan limit + Σ (add-on bundled limit × quantity);
 *  - addons: the add-on lines that counted (key + quantity), for display and debugging.
 *
 * Cached as toArray() under TenantCache::keyFor($tenantId, 'entitlements:…'), so
 * fromArray(toArray()) must round-trip exactly.
 */
final readonly class EntitlementsDTO
{
    /**
     * @param  list<string>  $features
     * @param  array<string, int|null>  $limits
     * @param  list<array{key: string, quantity: int}>  $addons
     */
    public function __construct(
        public string $tenantId,
        public ?int $planId,
        public array $features,
        public array $limits,
        public array $addons,
    ) {}

    /**
     * Nothing granted: no feature, every limit 0 (unknown tenant, or a tenant without a plan).
     */
    public static function none(string $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            planId: null,
            features: [],
            limits: array_fill_keys(LimitResource::values(), 0),
            addons: [],
        );
    }

    /**
     * @param  array{tenant_id: string, plan_id: int|null, features: list<string>, limits: array<string, int|null>, addons: list<array{key: string, quantity: int}>}  $data
     */
    public static function fromArray(array $data): self
    {
        $limits = [];
        foreach (LimitResource::values() as $resource) {
            // array_key_exists, not ??: a present NULL means unlimited, a missing key means 0.
            $value = array_key_exists($resource, $data['limits']) ? $data['limits'][$resource] : 0;
            $limits[$resource] = $value === null ? null : (int) $value;
        }

        $addons = [];
        foreach ($data['addons'] as $addon) {
            $addons[] = ['key' => (string) $addon['key'], 'quantity' => (int) $addon['quantity']];
        }

        return new self(
            tenantId: (string) $data['tenant_id'],
            planId: $data['plan_id'] === null ? null : (int) $data['plan_id'],
            features: array_map('strval', $data['features']),
            limits: $limits,
            addons: $addons,
        );
    }

    /**
     * @return array{tenant_id: string, plan_id: int|null, features: list<string>, limits: array<string, int|null>, addons: list<array{key: string, quantity: int}>}
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'plan_id' => $this->planId,
            'features' => $this->features,
            'limits' => $this->limits,
            'addons' => $this->addons,
        ];
    }

    /** $feature must already be canonical (TenantEntitlementService resolves aliases). */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features, true);
    }

    /** null = unlimited. */
    public function limit(LimitResource $resource): ?int
    {
        return array_key_exists($resource->value, $this->limits) ? $this->limits[$resource->value] : 0;
    }
}
