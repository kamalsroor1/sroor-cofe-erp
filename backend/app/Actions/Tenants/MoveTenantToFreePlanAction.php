<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\PlanChangeRequestKind;
use App\Enums\Billing\PlanChangeRequestStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Exceptions\TenantLifecycleException;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Services\CentralAuditLogger;
use App\Services\Entitlements\TenantEntitlementService;
use App\Support\Tenancy\TenantLifecyclePolicy;
use App\Support\Tenancy\TenantStatusTransition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * PLAN-10: put a tenant on a permanent free plan (`plans.is_free`) as `active`, with no paid
 * period and no deadline, so the lifecycle never moves it again.
 *
 * Sources:
 *  - `trial_fallback` (daily sweep): a SELF-SIGNUP trial ended unpaid and the platform has a
 *    fallback free plan: the shop keeps working on it instead of going read-only;
 *  - `free_plan` (daily sweep): the tenant's own plan is free but its stored status is trial /
 *    past_due / read_only (a row from before PLAN-10, when `free` was a 14-day trial);
 *  - `downgrade` (PLAN-10 review): the shop's scheduled downgrade to a free plan
 *    (plan_change_requests row `scheduled`), applied by the sweep at the end of the paid term, or at
 *    once by RequestPlanChangeAction when no paid term is running. The request becomes `completed`.
 *
 * One CENTRAL transaction, tenant row locked first, then the request row (downgrade) and the latest
 * subscription row. Everything is re-checked UNDER the lock (the caller decided on a stale read):
 *  - the stored status must still be $expectFrom (else 409 status_conflict: e.g. a payment won);
 *  - the move must still be due (else 409 free_plan_move_not_due): trial_fallback = still a
 *    self-signup trial whose deadline has passed under the lifecycle policy; free_plan = the tenant is
 *    still on that free plan; downgrade = the request is still `scheduled` for this plan and no paid
 *    term is running any more (a trial has none);
 *  - tenant: plan_id = the free plan, subscription_ends_at / grace_ends_at cleared;
 *  - subscriptions: the open row is closed as `expired` (its end clamped to now, so the history
 *    shows when it really ended) and a new `active` row at 0.000 starts on the free plan
 *    (ends_at = starts_at: the column is required and a free plan has no paid term; the resources
 *    mark it with `is_free`);
 *  - status -> active through TransitionTenantStatusAction (actor Billing). A tenant that is
 *    already `active` (a downgrade at the end of its term) keeps its status: a self-transition is
 *    not a state-machine move, so the plan change is recorded as an active -> active
 *    tenant_lifecycle_events row (actor Billing, same context) for the history;
 *  - CentralAuditEvent::TenantMovedToFreePlan with the plan ids (and the request id);
 *  - the tenant's entitlements cache is flushed after commit.
 */
final class MoveTenantToFreePlanAction
{
    public const SOURCE_TRIAL_FALLBACK = 'trial_fallback';

    public const SOURCE_FREE_PLAN = 'free_plan';

    public const SOURCE_DOWNGRADE = 'downgrade';

    private const SOURCES = [self::SOURCE_TRIAL_FALLBACK, self::SOURCE_FREE_PLAN, self::SOURCE_DOWNGRADE];

    public function __construct(
        private readonly TransitionTenantStatusAction $transition,
        private readonly CentralAuditLogger $audit,
        private readonly TenantEntitlementService $entitlements,
        private readonly TenantLifecyclePolicy $policy,
    ) {}

    /**
     * @param  int|null  $planChangeRequestId  required for the `downgrade` source
     */
    public function execute(string $tenantId, Plan $plan, TenantStatus $expectFrom, string $source, ?int $planChangeRequestId = null): Tenant
    {
        if (! in_array($source, self::SOURCES, true)) {
            throw new InvalidArgumentException("Unknown free-plan move source [{$source}].");
        }

        if ($source === self::SOURCE_DOWNGRADE && $planChangeRequestId === null) {
            throw new InvalidArgumentException('A downgrade to a free plan needs its plan change request.');
        }

        $connection = DB::connection((string) (new Subscription)->getConnectionName());

        return $connection->transaction(function () use ($connection, $tenantId, $plan, $expectFrom, $source, $planChangeRequestId): Tenant {
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

            $now = CarbonImmutable::now();
            $request = $this->assertDue($tenant, $freePlan, $expectFrom, $source, $planChangeRequestId, $now);

            $fromPlanId = $tenant->plan_id;
            $context = array_filter([
                'source' => $source,
                'from_plan_id' => $fromPlanId,
                'to_plan_id' => (int) $freePlan->getKey(),
                'plan_change_request_id' => $request === null ? null : (int) $request->getKey(),
            ], static fn (mixed $value): bool => $value !== null);

            $tenant->plan_id = (int) $freePlan->getKey();
            $tenant->subscription_ends_at = null;
            $tenant->grace_ends_at = null;
            $tenant->save();

            $this->replaceSubscription($tenantId, $freePlan, $now);

            if ($expectFrom === TenantStatus::Active) {
                // No status change: keep the plan change in the lifecycle history anyway.
                TenantLifecycleEvent::query()->create([
                    'tenant_id' => $tenantId,
                    'from_status' => TenantStatus::Active->value,
                    'to_status' => TenantStatus::Active->value,
                    'actor' => TenantLifecycleActor::Billing->value,
                    'properties' => $context,
                ]);
            } else {
                $this->transition->execute(TenantStatusTransition::to(
                    $tenantId,
                    TenantStatus::Active,
                    TenantLifecycleActor::Billing,
                    expectFrom: $expectFrom,
                    context: $context,
                ));
            }

            if ($request instanceof PlanChangeRequest) {
                $request->status = PlanChangeRequestStatus::Completed;
                $request->reviewed_at = Carbon::instance($now);
                $request->save();
            }

            $this->audit->record(
                CentralAuditEvent::TenantMovedToFreePlan,
                $context + [
                    'from_status' => $expectFrom->value,
                    'at' => $now->toIso8601String(),
                ],
                subject: $tenant,
            );

            $connection->afterCommit(fn () => $this->entitlements->forget($tenantId));

            return $tenant->refresh();
        });
    }

    /**
     * The move must still be due on the LOCKED row (409 free_plan_move_not_due otherwise).
     * Returns the locked plan change request for the `downgrade` source.
     */
    private function assertDue(Tenant $tenant, Plan $freePlan, TenantStatus $expectFrom, string $source, ?int $planChangeRequestId, CarbonImmutable $now): ?PlanChangeRequest
    {
        if ($source === self::SOURCE_TRIAL_FALLBACK) {
            $snapshot = $tenant->lifecycleSnapshot();
            $steps = $this->policy->evaluate($snapshot, $now)->pendingTransitions;
            $due = $expectFrom === TenantStatus::Trial
                && $tenant->isSelfSignup()
                && $snapshot->trialFallsBackToFreePlan
                && $steps !== []
                && $steps[0]->from === TenantStatus::Trial
                && $steps[0]->to === TenantStatus::Active;

            if (! $due) {
                throw TenantLifecycleException::freePlanMoveNotDue();
            }

            return null;
        }

        if ($source === self::SOURCE_FREE_PLAN) {
            if ((int) $tenant->plan_id !== (int) $freePlan->getKey()) {
                throw TenantLifecycleException::freePlanMoveNotDue();
            }

            return null;
        }

        /** @var PlanChangeRequest|null $request */
        $request = PlanChangeRequest::query()
            ->whereKey($planChangeRequestId)
            ->where('tenant_id', (string) $tenant->getKey())
            ->lockForUpdate()
            ->first();

        $paidTermRunning = $expectFrom !== TenantStatus::Trial
            && $tenant->subscription_ends_at !== null
            && $tenant->subscription_ends_at->isAfter($now);

        if (! $request instanceof PlanChangeRequest
            || $request->kind !== PlanChangeRequestKind::PlanChange
            || $request->status !== PlanChangeRequestStatus::Scheduled
            || (int) $request->requested_plan_id !== (int) $freePlan->getKey()
            || $paidTermRunning) {
            throw TenantLifecycleException::freePlanMoveNotDue();
        }

        return $request;
    }

    private function replaceSubscription(string $tenantId, Plan $freePlan, CarbonImmutable $now): void
    {
        $latest = Subscription::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $open = [SubscriptionStatus::Trialing, SubscriptionStatus::PendingPayment, SubscriptionStatus::PastDue, SubscriptionStatus::Active];
        if ($latest instanceof Subscription && in_array($latest->status, $open, true)) {
            $latest->status = SubscriptionStatus::Expired;
            if ($latest->ends_at->isAfter($now)) {
                $latest->ends_at = Carbon::instance($now);
            }
            $latest->save();
        }

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
