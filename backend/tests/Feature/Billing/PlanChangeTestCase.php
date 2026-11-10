<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\AddonType;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\PlanAddon;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Fixtures of the plan-change / add-on request tests: plans, a subscribed tenant, an add-on offered
 * on a plan and raw request rows.
 */
abstract class PlanChangeTestCase extends BillingTestCase
{
    /** A tenant on $plan with an ACTIVE subscription ending in $days days. */
    protected function subscribedTenant(Plan $plan, int $days = 10, string $status = 'active'): Tenant
    {
        $end = now()->addDays($days)->startOfSecond();
        $tenant = $this->tenantOn($plan, ['status' => $status, 'subscription_ends_at' => $end]);

        Subscription::query()->create([
            'tenant_id' => $tenant->getKey(),
            'plan_id' => $plan->getKey(),
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'amount' => $plan->price_monthly,
            'starts_at' => now()->subDays(20),
            'ends_at' => $end,
        ]);

        return Tenant::query()->findOrFail($tenant->getKey());
    }

    /**
     * @param  array<string, int>  $limits
     */
    protected function addonOn(Plan $plan, string $key = 'extra-users', array $limits = ['users' => 5], string $unit = '100.000', ?string $yearly = '1000.000'): Addon
    {
        $addon = Addon::query()->create([
            'key' => $key.'-'.Str::lower(Str::random(5)),
            'name_key' => 'plans.addons.test.name',
            'type' => AddonType::Recurring,
            'bundled_limits' => $limits,
            'unit_price' => $unit,
            'yearly_price' => $yearly,
        ]);
        PlanAddon::query()->create(['plan_id' => $plan->getKey(), 'addon_id' => $addon->getKey()]);

        return $addon;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function requestRow(Tenant $tenant, array $attributes = []): PlanChangeRequest
    {
        $this->endTenancy();

        return PlanChangeRequest::query()->create(array_merge([
            'tenant_id' => $tenant->getKey(),
            'kind' => 'plan_change',
            'current_plan_id' => $tenant->plan_id,
            'billing_cycle' => 'monthly',
            'status' => 'pending',
            'requested_by_name' => 'Owner',
        ], $attributes));
    }
}
