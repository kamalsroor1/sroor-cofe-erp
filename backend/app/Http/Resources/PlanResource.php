<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Money is a "0.000" string; every limit is int|null where null = unlimited. Limits nothing
 * enforces yet (Plan::HIDDEN_LIMIT_COLUMNS) are not part of the payload; they stay stored.
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
            'name' => $this->name,
            'name_key' => $this->name_key,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_monthly' => $this->price_monthly,
            'price_yearly' => $this->price_yearly,
            'founder_price_monthly' => $this->founder_price_monthly,
            'founder_price_yearly' => $this->founder_price_yearly,
            'max_users' => $this->max_users,
            'max_stores' => $this->max_stores,
            'max_warehouses' => $this->max_warehouses,
            'max_vans' => $this->max_vans,
            'max_items' => $this->max_items,
            'trial_days' => $this->trial_days,
            'is_active' => $this->is_active,
            'is_public' => $this->is_public,
            'is_popular' => $this->is_popular,
            'features' => empty($this->features) ? [] : array_diff_key($this->features, array_flip(PlanFeature::hiddenKeys())),
            'tenants_count' => $this->whenCounted('tenants'),
        ];
    }
}
