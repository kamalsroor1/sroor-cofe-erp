<?php

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'domain' => $this->domains->first()?->domain ?? ($this->slug.'.'.env('CENTRAL_DOMAIN', 'localhost')),
            'plan' => $this->plan ? new PlanResource($this->plan) : null,
            'status' => $this->status,
            'trial_ends_at' => $this->trial_ends_at?->toDateString(),
            'subscription_ends_at' => $this->subscription_ends_at?->toDateString(),
            'enabled_features' => $this->enabled_features ?? [],
            'created_at' => $this->created_at?->toDateString(),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }
}
