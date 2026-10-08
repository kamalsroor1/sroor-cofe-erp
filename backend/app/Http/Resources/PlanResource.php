<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_monthly' => (float) $this->price_monthly,
            'price_yearly' => (float) $this->price_yearly,
            'max_users' => (int) $this->max_users,
            'max_stores' => (int) $this->max_stores,
            'max_items' => (int) $this->max_items,
            'max_invoices_per_month' => (int) $this->max_invoices_per_month,
            'is_active' => (bool) $this->is_active,
            'is_popular' => (bool) $this->is_popular,
            'features' => $this->features ?? [],
            'tenants_count' => $this->whenCounted('tenants'),
        ];
    }
}
