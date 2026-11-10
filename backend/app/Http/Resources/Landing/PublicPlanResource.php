<?php

declare(strict_types=1);

namespace App\Http\Resources\Landing;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;

/**
 * PRD-07: one plan of the public pricing page. Allowlist only (no ids, no is_active, no
 * tenant counts). Money is a "0.000" string; every limit is int|null where null = unlimited.
 *
 * @property Plan $resource
 */
final class PublicPlanResource extends JsonResource
{
    /**
     * @param  Collection<string, PlanFeature>  $publicFeatures  public registry rows keyed by feature key
     * @param  bool  $founderOffered  founder pricing still has free slots
     */
    public function __construct(
        Plan $plan,
        private readonly Collection $publicFeatures,
        private readonly bool $founderOffered,
    ) {
        parent::__construct($plan);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $plan = $this->resource;

        return [
            'code' => $plan->slug,
            'name' => self::translate($plan->name_key, $plan->name),
            'description' => self::translate(self::sibling($plan->name_key, 'description'), $plan->description),
            'is_popular' => $plan->is_popular,
            'sort_order' => $plan->sort_order,
            'trial_days' => $plan->trial_days,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'founder_price_monthly' => $this->founderPrice($plan->founder_price_monthly),
            'founder_price_yearly' => $this->founderPrice($plan->founder_price_yearly),
            'limits' => [
                'users' => $plan->max_users,
                'stores' => $plan->max_stores,
                'warehouses' => $plan->max_warehouses,
                'vans' => $plan->max_vans,
                'items' => $plan->max_items,
                'invoices_per_month' => $plan->max_invoices_per_month,
                'storage_mb' => $plan->max_storage_mb,
            ],
            'features' => $this->features($plan),
        ];
    }

    /**
     * @return list<array{key: string, name: string, description: string|null, module: string}>
     */
    private function features(Plan $plan): array
    {
        $enabled = is_array($plan->features) ? $plan->features : [];
        $list = [];

        foreach ($this->publicFeatures as $key => $feature) {
            if (($enabled[$key] ?? false) !== true) {
                continue;
            }

            $list[] = [
                'key' => $key,
                'name' => self::translate($feature->name_key, $feature->name),
                'description' => self::translate(self::sibling($feature->name_key, 'description'), $feature->description),
                'module' => $feature->module,
            ];
        }

        return $list;
    }

    private function founderPrice(?string $price): ?string
    {
        if (! $this->founderOffered || $price === null || bccomp($price, '0', 3) <= 0) {
            return null;
        }

        return $price;
    }

    /** Translated catalog text, falling back to the stored single-language column. */
    public static function translate(?string $key, ?string $fallback): ?string
    {
        if ($key !== null && Lang::has($key)) {
            return (string) __($key);
        }

        return $fallback;
    }

    /** `plans.plans.free.name` -> `plans.plans.free.description`. */
    public static function sibling(?string $nameKey, string $leaf): ?string
    {
        if ($nameKey === null || ! str_ends_with($nameKey, '.name')) {
            return null;
        }

        return substr($nameKey, 0, -strlen('name')).$leaf;
    }
}
