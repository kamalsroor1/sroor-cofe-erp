<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\Enums\TenantStatus;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Tenancy\TenantLifecyclePolicy;
use Carbon\CarbonImmutable;
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
        $now = CarbonImmutable::now();
        $decision = app(TenantLifecyclePolicy::class)->evaluate($tenant->lifecycleSnapshot(), $now);
        $effective = $decision->effectiveStatus;
        $endsAt = $this->periodEnd($tenant, $effective);

        return [
            'tenant' => [
                'id' => (string) $tenant->getKey(),
                'name' => $tenant->name,
                'status' => $tenant->status,
                // SELL-06: the status the shop is really in now (the stored one may lag the daily sweep).
                'effective_status' => ['value' => $effective->value, 'label' => $effective->label(), 'access' => $decision->access()->value],
            ],
            'plan' => $plan === null ? null : ['id' => (int) $plan->getKey(), 'name' => $plan->name, 'code' => $plan->slug],
            'status' => $subscription === null ? null : ['value' => $subscription->status->value, 'label' => $subscription->status->label()],
            'billing_cycle' => $subscription === null ? null : ['value' => $subscription->billing_cycle->value, 'label' => $subscription->billing_cycle->label()],
            'starts_at' => $subscription?->starts_at?->toIso8601String(),
            // SELL-06: the end of what the shop actually has (trial end while on trial, else the PAID
            // end), never the provisioned subscription row's placeholder end (a never-paid shop has none).
            'ends_at' => $endsAt?->toIso8601String(),
            'subscription_ends_at' => $tenant->subscription_ends_at?->toIso8601String(),
            'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
            // Whole days left of a running trial / paid period; null when nothing is running
            // (read_only, suspended, cancelled, archived) or the end has passed.
            'days_left' => $this->daysLeft($effective, $endsAt, $now),
            'is_founder' => $subscription !== null && $subscription->is_founder,
        ];
    }

    private function periodEnd(Tenant $tenant, TenantStatus $effective): ?CarbonImmutable
    {
        $end = $effective === TenantStatus::Trial ? $tenant->trial_ends_at : $tenant->subscription_ends_at;

        return $end === null ? null : CarbonImmutable::instance($end);
    }

    private function daysLeft(TenantStatus $effective, ?CarbonImmutable $end, CarbonImmutable $now): ?int
    {
        $running = in_array($effective, [TenantStatus::Trial, TenantStatus::Active, TenantStatus::PastDue], true);

        if (! $running || $end === null || ! $end->isAfter($now)) {
            return null;
        }

        // Rounded up, as TenantLifecyclePolicy counts days.
        return intdiv($end->getTimestamp() - $now->getTimestamp() + 86399, 86400);
    }
}
