<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\PlanFeature;
use Database\Seeders\Catalog\FeatureCatalog;

/**
 * PLAN-EDIT: the plan editor validates feature keys against plan_features. Seeds only the
 * registry (no plans, no add-ons) for tests that write plans through the console API.
 */
trait SeedsFeatureRegistry
{
    protected function seedFeatureRegistry(): void
    {
        foreach (FeatureCatalog::all() as $feature) {
            PlanFeature::query()->updateOrCreate(['key' => $feature['key']], [
                'name' => $feature['key'],
                'name_key' => $feature['name_key'],
                'module' => $feature['module'],
                'type' => 'boolean',
                'default_value' => $feature['is_core'] ? 'true' : 'false',
                'sort_order' => $feature['sort_order'],
                'is_core' => $feature['is_core'],
                'is_public' => $feature['is_public'],
            ]);
        }
    }
}
