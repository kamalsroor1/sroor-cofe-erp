<?php

namespace App\Http\Resources;

use App\Enums\FeatureOverrideMode;
use App\Http\Requests\Central\SetTenantFeatureOverrideRequest;
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
            'plan_name' => $this->plan?->localizedName(),
            'status' => $this->status,
            'trial_ends_at' => $this->trial_ends_at?->toDateString(),
            'subscription_ends_at' => $this->subscription_ends_at?->toDateString(),
            'enabled_features' => $this->grantedFeatureKeys(),
            // OPS-2: queued provisioning state (codes only, never the raw exception).
            'provisioning_status' => $this->resource->provisioningStatus()->value,
            'provisioning_error_code' => $this->provisioning_error_code,
            'provisioned_at' => $this->provisioned_at?->toIso8601String(),
            // OPS-9: archive / purge state from the row's own columns (no extra query); the full
            // block (final backup, purge eligibility) is on the tenant page (TenantArchiveResource).
            'archive_state' => $this->archive_state?->value,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'purged_at' => $this->purged_at?->toIso8601String(),
            // FREE-15: dormant free shop clock (null = never stamped, dormant_days counts from created_at); dormant_days only for free-plan shops.
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'dormant_days' => $this->resource->isOnFreePlan() ? $this->resource->dormantDays() : null,
            'created_at' => $this->created_at?->toDateString(),
            'created_at_human' => $this->created_at?->diffForHumans(),
            // BRND-5: the shop's legal info (tenant settings), only when this resource is the
            // tenant of the current request (/system/context). Central screens (super-admin
            // lists) never open every tenant DB to fill it.
            'legal' => $this->when($this->isCurrentTenant(), fn (): array => $this->legal()),
        ];
    }

    /**
     * Compatibility key: the shop's active GRANT override keys (canonical, non-expired) plus any
     * remaining legacy list entries. The legacy column alone is empty since PLAN-12.
     *
     * @return list<string>
     */
    private function grantedFeatureKeys(): array
    {
        $keys = [];

        foreach ($this->resource->featureOverrides as $override) {
            if ($override->mode === FeatureOverrideMode::Grant && ! $override->isExpired()) {
                $keys[] = $override->feature_key;
            }
        }

        foreach ((array) ($this->enabled_features ?? []) as $legacy) {
            if (is_string($legacy) && $legacy !== '') {
                $keys[] = SetTenantFeatureOverrideRequest::canonical($legacy);
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
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
