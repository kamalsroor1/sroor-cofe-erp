<?php

namespace App\Http\Resources;

use App\Models\Tenant;
use App\Services\Branding\TenantBranding;
use App\Support\PlatformHosts;
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
            'domain' => $this->domains->first()?->domain ?? PlatformHosts::tenantHost((string) $this->slug),
            'plan' => $this->plan ? new PlanResource($this->plan) : null,
            'status' => $this->status,
            'trial_ends_at' => $this->trial_ends_at?->toDateString(),
            'subscription_ends_at' => $this->subscription_ends_at?->toDateString(),
            'enabled_features' => $this->enabled_features ?? [],
            // OPS-2: queued provisioning state (codes only, never the raw exception).
            'provisioning_status' => $this->resource->provisioningStatus()->value,
            'provisioning_error_code' => $this->provisioning_error_code,
            'provisioned_at' => $this->provisioned_at?->toIso8601String(),
            'created_at' => $this->created_at?->toDateString(),
            'created_at_human' => $this->created_at?->diffForHumans(),
            // BRND-5: the shop's legal info (tenant settings), only when this resource is the
            // tenant of the current request (/system/context). Central screens (super-admin
            // lists) never open every tenant DB to fill it.
            'legal' => $this->when($this->isCurrentTenant(), fn (): array => $this->legal()),
        ];
    }

    private function isCurrentTenant(): bool
    {
        $current = tenancy()->initialized ? tenancy()->tenant : null;

        return $current !== null && (string) $current->getTenantKey() === (string) $this->resource->getKey();
    }

    /**
     * @return array{commercial_register: string, tax_registration_no: string}
     */
    private function legal(): array
    {
        $branding = app(TenantBranding::class)->get();

        return [
            'commercial_register' => $branding->commercialRegister,
            'tax_registration_no' => $branding->taxRegistrationNo,
        ];
    }
}
