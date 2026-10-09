<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\Entitlements\EntitlementsDTO;
use App\Enums\LimitResource;
use App\Models\Tenant;

/**
 * Backend entry point for plan/feature gating (bound to
 * App\Services\Entitlements\TenantEntitlementService, ENTI-2.2).
 */
interface TenantFeatureManagerInterface
{
    /**
     * Is the feature ON for the tenant (plan ∪ active add-ons ∪ overrides)? Legacy alias keys are accepted.
     */
    public function isFeatureEnabled(Tenant $tenant, string $featureKey): bool;

    /**
     * Toggle a super-admin override of one feature for the tenant.
     *
     * @return list<string> the tenant's override list after the toggle
     */
    public function toggleFeatureOverride(Tenant $tenant, string $featureKey): array;

    /**
     * Every feature the tenant's plan knows about plus every granted one, keyed by canonical key.
     *
     * @return array<string, bool>
     */
    public function resolveAllFeatures(Tenant $tenant): array;

    /**
     * The tenant's full resolved entitlements (cached, invalidated by version bump).
     */
    public function entitlements(Tenant|string $tenant): EntitlementsDTO;

    /**
     * The tenant's effective limit for one resource; null = unlimited.
     */
    public function limit(Tenant|string $tenant, LimitResource $resource): ?int;
}
