<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Exceptions\TenantLifecycleException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use App\Services\Entitlements\TenantEntitlementService;
use App\Support\Tenancy\TenantStatusTransition;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * PLAN-10: put a tenant on a permanent free plan (`plans.is_free`) as `active`, with no paid
 * period and no deadline, so the lifecycle never moves it again.
 *
 * Used by the daily lifecycle sweep (ApplyTenantLifecycleAction) for two cases:
 *  - `trial_fallback`: a SELF-SIGNUP trial ended unpaid and the platform setting
 *    `trial_fallback_plan_id` names a free plan: the shop keeps working on that plan instead of
 *    going read-only (no data is touched);
 *  - `free_plan`: the tenant's own plan is free but its stored status is trial / past_due
 *    (a row from before PLAN-10, when `free` was a 14-day trial).
 *
 * One CENTRAL transaction, tenant row locked first, then the latest subscription row:
 *  - the stored status must still be $expectFrom (else 409 status conflict, nothing written);
 *  - tenant: plan_id = the free plan, subscription_ends_at / grace_ends_at cleared;
 *  - subscriptions: the open (trialing / pending_payment / past_due) row is closed as `expired`
 *    and a new `active` row at 0.000 starts on the free plan (ends_at = starts_at: the column is
 *    required, and a free plan has no paid term);
 *  - status -> active through TransitionTenantStatusAction (actor Billing, the only actor the
 *    state machine lets make a tenant active; context.source says it was the free plan), which
 *    writes the tenant_lifecycle_events row and the TenantStatusChanged audit;
 *  - CentralAuditEvent::TenantMovedToFreePlan with the plan ids;
 *  - the tenant's entitlements cache is flushed after commit.
 */
final class MoveTenantToFreePlanAction
{
    public const SOURCE_TRIAL_FALLBACK = 'trial_fallback';

    public const SOURCE_FREE_PLAN = 'free_plan';

    public function __construct(
        private readonly TransitionTenantStatusAction $transition,
        private readonly CentralAuditLogger $audit,
        private readonly TenantEntitlementService $entitlements,
    ) {}

    public function execute(string $tenantId, Plan $plan, TenantStatus $expectFrom, string $source): Tenant
    {
        if (! in_array($source, [self::SOURCE_TRIAL_FALLBACK, self::SOURCE_FREE_PLAN], true)) {
            throw new InvalidArgumentException("Unknown free-plan move source [{$source}].");
        }

        $connection = DB::connection((string) (new Subscription)->getConnectionName());

        return $connection->transaction(function () use ($connection, $tenantId, $plan, $expectFrom, $source): Tenant {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();

            if ($tenant->isPurged()) {
                throw TenantLifecycleException::tenantPurged();
            }

            if ((string) $tenant->status !== $expectFrom->value) {
                throw TenantLifecycleException::statusConflict([$expectFrom], (string) $tenant->status);
            }

            /** @var Plan $freePlan */
            $freePlan = Plan::query()->whereKey($plan->getKey())->firstOrFail();
            if (! $freePlan->is_free) {
                throw new InvalidArgumentException("Plan [{$freePlan->getKey()}] is not a free plan.");
            }

            $fromPlanId = $tenant->plan_id;
            $now = now();

            $tenant->plan_id = (int) $freePlan->getKey();
            $tenant->subscription_ends_at = null;
            $tenant->grace_ends_at = null;
            $tenant->save();

            $this->replaceSubscription($tenantId, $freePlan);

            $this->transition->execute(TenantStatusTransition::to(
                $tenantId,
                TenantStatus::Active,
                TenantLifecycleActor::Billing,
                expectFrom: $expectFrom,
                context: [
                    'source' => $source,
                    'from_plan_id' => $fromPlanId,
                    'to_plan_id' => (int) $freePlan->getKey(),
                ],
            ));

            $this->audit->record(
                CentralAuditEvent::TenantMovedToFreePlan,
                [
                    'source' => $source,
                    'from_status' => $expectFrom->value,
                    'from_plan_id' => $fromPlanId,
                    'to_plan_id' => (int) $freePlan->getKey(),
                    'at' => $now->toIso8601String(),
                ],
                subject: $tenant,
            );

            $connection->afterCommit(fn () => $this->entitlements->forget($tenantId));

            return $tenant->refresh();
        });
    }

    private function replaceSubscription(string $tenantId, Plan $freePlan): void
    {
        $latest = Subscription::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $open = [SubscriptionStatus::Trialing, SubscriptionStatus::PendingPayment, SubscriptionStatus::PastDue, SubscriptionStatus::Active];
        if ($latest instanceof Subscription && in_array($latest->status, $open, true)) {
            $latest->status = SubscriptionStatus::Expired;
            $latest->save();
        }

        $now = now();

        Subscription::query()->create([
            'tenant_id' => $tenantId,
            'plan_id' => (int) $freePlan->getKey(),
            'billing_cycle' => BillingCycle::Monthly,
            'status' => SubscriptionStatus::Active,
            'amount' => '0.000',
            'currency' => Subscription::DEFAULT_CURRENCY,
            'price_locked' => false,
            'is_founder' => false,
            'founder_price_until' => null,
            'starts_at' => $now,
            'ends_at' => $now,
            'payment_method' => null,
        ]);
    }
}
