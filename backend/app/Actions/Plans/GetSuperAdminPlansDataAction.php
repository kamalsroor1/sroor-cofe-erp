<?php

declare(strict_types=1);

namespace App\Actions\Plans;

use App\Http\Resources\PlanResource;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Services\Branding\PlatformBranding;

/**
 * GET /super-admin/plans: everything the plan editor needs.
 *
 * - plans: PlanResource with tenants_count, add-on rows and included add-ons;
 * - grouped_features: the PUBLIC features by module (what the editor can switch);
 * - features_registry: every registered feature, hidden ones flagged (`is_public` false: sent
 *   values are accepted but never written);
 * - tax_rate: the VAT percent SaaS invoices add (platform setting billing_tax_rate, "14.000");
 * - addons_catalog: every add-on with its key / name key / prices and whether a plan may INCLUDE
 *   it (`includable`: services without features or limits only).
 */
class GetSuperAdminPlansDataAction
{
    public function __construct(private readonly PlatformBranding $branding) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $plans = Plan::query()->withCount('tenants')->with('addons')->orderBy('sort_order')->orderBy('id')->get();

        return [
            'plans' => PlanResource::collection($plans)->resolve(),
            // Review M4: the VAT rate SaaS invoices use (platform setting billing_tax_rate, percent).
            'tax_rate' => $this->branding->get()->billingTaxRate,
            'grouped_features' => PlanFeature::groupedByModule(),
            'features_registry' => PlanFeature::query()->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (PlanFeature $feature): array => [
                    'key' => $feature->key,
                    'name_key' => $feature->name_key,
                    'name' => $feature->name,
                    'module' => $feature->module,
                    'is_core' => $feature->is_core,
                    'is_public' => $feature->is_public,
                ])->values()->all(),
            'addons_catalog' => Addon::query()->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (Addon $addon): array => [
                    'id' => (int) $addon->getKey(),
                    'key' => $addon->key,
                    'name_key' => $addon->name_key,
                    'name' => (string) __($addon->name_key),
                    'type' => $addon->type->value,
                    'unit_price' => $addon->unit_price,
                    'yearly_price' => $addon->yearly_price,
                    'is_active' => $addon->is_active,
                    'is_public' => $addon->is_public,
                    'includable' => $addon->isIncludable(),
                ])->values()->all(),
        ];
    }
}
