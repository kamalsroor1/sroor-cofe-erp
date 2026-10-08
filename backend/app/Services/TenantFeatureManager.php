<?php

namespace App\Services;

use App\Contracts\TenantFeatureManagerInterface;
use App\Models\Tenant;

class TenantFeatureManager implements TenantFeatureManagerInterface
{
    /**
     * التحقق من توفر ميزة معينة للمستأجر
     */
    public function isFeatureEnabled(Tenant $tenant, string $featureKey): bool
    {
        // 1. استثناء يدوي مفعل
        $manualFeatures = $tenant->enabled_features ?? [];
        if (in_array($featureKey, $manualFeatures, true)) {
            return true;
        }

        // 2. فحص الباقة
        $plan = $tenant->plan;
        if (! $plan) {
            return false;
        }

        return (bool) ($plan->features[$featureKey] ?? false);
    }

    /**
     * تبديل استثناء يدوي لميزة معينة للمستأجر
     */
    public function toggleFeatureOverride(Tenant $tenant, string $featureKey): array
    {
        $enabledFeatures = $tenant->enabled_features ?? [];

        if (in_array($featureKey, $enabledFeatures, true)) {
            $enabledFeatures = array_values(array_filter($enabledFeatures, fn ($f) => $f !== $featureKey));
        } else {
            $enabledFeatures[] = $featureKey;
        }

        $tenant->update(['enabled_features' => $enabledFeatures]);

        return $enabledFeatures;
    }

    /**
     * جلب قائمة كافة الفيتشرز المتاحة للمستأجر
     */
    public function resolveAllFeatures(Tenant $tenant): array
    {
        $planFeatures = $tenant->plan ? ($tenant->plan->features ?? []) : [];
        $manualFeatures = $tenant->enabled_features ?? [];

        foreach ($manualFeatures as $key) {
            $planFeatures[$key] = true;
        }

        return $planFeatures;
    }
}
