<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Subscription summary shown above a tenant's invoices in the console
 * (GET /super-admin/tenants/{id}/billing-invoices).
 *
 * @property array{tenant: Tenant, subscription: Subscription|null} $resource
 */
final class TenantSubscriptionSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tenant = $this->resource['tenant'];
        $subscription = $this->resource['subscription'];
        $plan = $subscription?->plan;

        return [
            'tenant' => ['id' => (string) $tenant->getKey(), 'name' => $tenant->name, 'status' => $tenant->status],
            'plan' => $plan === null ? null : ['id' => (int) $plan->getKey(), 'name' => $plan->name, 'code' => $plan->slug],
            'status' => $subscription === null ? null : ['value' => $subscription->status->value, 'label' => $subscription->status->label()],
            'billing_cycle' => $subscription === null ? null : ['value' => $subscription->billing_cycle->value, 'label' => $subscription->billing_cycle->label()],
            'starts_at' => $subscription?->starts_at?->toIso8601String(),
            'ends_at' => $subscription?->ends_at?->toIso8601String(),
            'subscription_ends_at' => $tenant->subscription_ends_at?->toIso8601String(),
            'is_founder' => $subscription !== null && $subscription->is_founder,
        ];
    }
}
