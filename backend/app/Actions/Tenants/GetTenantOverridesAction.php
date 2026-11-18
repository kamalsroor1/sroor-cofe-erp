<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\LimitResource;
use App\Http\Resources\Central\TenantOverrideResource;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Models\TenantLimitOverride;
use App\Services\Entitlements\TenantEntitlementService;

/**
 * PLAN-12: what the console shows for one shop: every limit (plan value, override, effective) and
 * every catalog feature (from the plan?, override, effective). `effective` always comes from
 * TenantEntitlementService (plan + add-ons + active overrides), so it is exactly what is enforced.
 * An expired override is still listed (`is_expired: true`) but no longer counts.
 */
final class GetTenantOverridesAction
{
    public function __construct(private readonly TenantEntitlementService $entitlements) {}

    /**
     * @return array{limits: list<array<string, mixed>>, features: list<array<string, mixed>>}
     */
    public function execute(Tenant $tenant): array
    {
        $tenantId = (string) $tenant->getKey();
        $plan = $tenant->plan_id === null ? null : Plan::query()->find($tenant->plan_id);
        $effective = $this->entitlements->entitlements($tenantId);

        $limitOverrides = TenantLimitOverride::query()->with('creator:id,name')->where('tenant_id', $tenantId)->get()->keyBy('resource');
        $featureOverrides = TenantFeatureOverride::query()->with('creator:id,name')->where('tenant_id', $tenantId)->get()
            ->keyBy(fn (TenantFeatureOverride $row): string => $this->entitlements->canonicalFeature($row->feature_key));

        $limits = [];
        foreach (LimitResource::cases() as $resource) {
            $override = $limitOverrides->get($resource->value);

            $limits[] = [
                'resource' => $resource->value,
                'label' => (string) __('billing.limit_resources.'.$resource->value),
                'unit' => $resource === LimitResource::StorageMb ? 'mb' : 'count',
                'plan_value' => $plan instanceof Plan ? $resource->fromPlan($plan) : 0,
                'override' => $override instanceof TenantLimitOverride ? TenantOverrideResource::limit($override) : null,
                'effective' => $effective->limit($resource),
                'overridden' => $override instanceof TenantLimitOverride && ! $override->isExpired(),
            ];
        }

        $planFeatures = $plan instanceof Plan ? (array) ($plan->features ?? []) : [];
        $planOn = [];
        foreach ($planFeatures as $key => $enabled) {
            if ($enabled === true) {
                $planOn[$this->entitlements->canonicalFeature((string) $key)] = true;
            }
        }

        $features = [];
        foreach (PlanFeature::query()->orderBy('sort_order')->orderBy('key')->get(['key', 'name', 'name_key', 'module']) as $feature) {
            $key = $this->entitlements->canonicalFeature($feature->key);
            $override = $featureOverrides->get($key);

            $features[] = [
                'key' => $key,
                'label' => $feature->name_key !== null && $feature->name_key !== '' ? (string) __($feature->name_key) : $feature->name,
                'module' => $feature->module,
                'from_plan' => isset($planOn[$key]),
                'override' => $override instanceof TenantFeatureOverride ? TenantOverrideResource::feature($override) : null,
                'effective' => $effective->hasFeature($key),
            ];
        }

        return ['limits' => $limits, 'features' => $features];
    }
}
