<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plan;
use App\Models\PlanAddon;
use App\Models\PlanFeature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Money is a "0.000" string (nullable founder / transition / compare-at prices = none); `promotion`
 * is the offer running right now ({compare_at_monthly, compare_at_yearly, ends_at}) or null.
 * `addons` / `included_addons` are present only when the `addons` relation is loaded. Every limit is int|null where null = unlimited
 * (PLAN-ENF: max_invoices_per_month and max_storage_mb included; PLAN-11: max_customers, max_suppliers).
 *
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->resource->localizedName(),
            'name_key' => $this->name_key,
            'slug' => $this->slug,
            'description' => $this->resource->localizedDescription(),
            'price_monthly' => $this->price_monthly,
            'price_yearly' => $this->price_yearly,
            'founder_price_monthly' => $this->founder_price_monthly,
            'founder_price_yearly' => $this->founder_price_yearly,
            'founder_transition_price_monthly' => $this->founder_transition_price_monthly,
            'founder_transition_price_yearly' => $this->founder_transition_price_yearly,
            // PRICE-2026-11: the stored offer (editable) and whether it is running right now.
            'compare_at_price_monthly' => $this->compare_at_price_monthly,
            'compare_at_price_yearly' => $this->compare_at_price_yearly,
            // ISO 8601 with the app timezone offset (e.g. 2026-12-01T02:00:00+02:00).
            'promo_starts_at' => $this->promo_starts_at?->copy()->setTimezone((string) config('app.timezone'))->toIso8601String(),
            'promo_ends_at' => $this->promo_ends_at?->copy()->setTimezone((string) config('app.timezone'))->toIso8601String(),
            'promotion' => $this->resource->activePromotion()?->toArray(),
            'max_users' => $this->max_users,
            'max_stores' => $this->max_stores,
            'max_warehouses' => $this->max_warehouses,
            'max_vans' => $this->max_vans,
            'max_items' => $this->max_items,
            'max_invoices_per_month' => $this->max_invoices_per_month,
            'max_storage_mb' => $this->max_storage_mb,
            'max_customers' => $this->max_customers,
            'max_suppliers' => $this->max_suppliers,
            'trial_days' => $this->trial_days,
            // PLAN-10: permanent free plan (never billed, no trial; its tenants are always active).
            'is_free' => (bool) $this->is_free,
            'is_active' => $this->is_active,
            'is_public' => $this->is_public,
            'is_popular' => $this->is_popular,
            'sort_order' => $this->sort_order,
            'features' => empty($this->features) ? [] : array_diff_key($this->features, array_flip(PlanFeature::hiddenKeys())),
            'tenants_count' => $this->whenCounted('tenants'),
            // PLAN-EDIT: per-plan add-on availability (only when `addons` is loaded).
            'addons' => $this->whenLoaded('addons', fn (): array => $this->addonRows()),
            'included_addons' => $this->whenLoaded('addons', fn (): array => self::includedAddons($this->resource)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function addonRows(): array
    {
        $rows = [];

        foreach ($this->resource->addons as $addon) {
            /** @var PlanAddon $pivot */
            $pivot = $addon->getRelation('pivot');
            $rows[] = [
                'addon_id' => (int) $addon->getKey(),
                'key' => $addon->key,
                'name_key' => $addon->name_key,
                'is_available' => $pivot->is_available,
                'included_quantity' => $pivot->included_quantity,
                'included_billing_cycle' => $pivot->included_billing_cycle?->value,
                'unit_price_override' => $pivot->unit_price_override,
                'yearly_price_override' => $pivot->yearly_price_override,
            ];
        }

        return $rows;
    }

    /**
     * Add-ons the plan INCLUDES (plan_addon.included_quantity > 0) among active ones; with
     * $publicOnly, hidden add-ons are left out (public pricing page).
     *
     * @return list<array{key: string, name: string, quantity: int, billing_cycle: string|null}>
     */
    public static function includedAddons(Plan $plan, bool $publicOnly = false): array
    {
        $rows = [];

        foreach ($plan->addons as $addon) {
            /** @var PlanAddon $pivot */
            $pivot = $addon->getRelation('pivot');
            if (! $pivot->isIncluded() || ! $addon->is_active || ($publicOnly && ! $addon->is_public)) {
                continue;
            }

            $rows[] = [
                'key' => $addon->key,
                'name' => (string) __($addon->name_key),
                'quantity' => $pivot->included_quantity,
                'billing_cycle' => $pivot->included_billing_cycle?->value,
            ];
        }

        return $rows;
    }
}
