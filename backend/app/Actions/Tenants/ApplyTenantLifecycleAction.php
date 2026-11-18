<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\Billing\PlanChangeRequestKind;
use App\Enums\Billing\PlanChangeRequestStatus;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Services\Signup\FreeShopGuard;
use App\Services\Signup\TrialFallbackPlan;
use App\Support\Tenancy\LifecycleSweepResult;
use App\Support\Tenancy\LifecycleTransitionStep;
use App\Support\Tenancy\TenantLifecyclePolicy;
use App\Support\Tenancy\TenantStatusTransition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The daily lifecycle sweep (IDEN-3.6 / BE-05): persists the time-driven moves
 * TenantLifecyclePolicy derived for each tenant (trial → read_only after the grace days,
 * active → past_due → read_only, read_only → suspended), as the System actor.
 *
 * - Every move goes through TransitionTenantStatusAction (row lock, lifecycle event row,
 *   central audit, TenantStatusTransitioned after commit, token revocation when blocked).
 * - Idempotent: a second run finds nothing pending. A stale decision is refused by
 *   `expectFrom` (409) and counted as failed, never applied twice.
 * - `suspended → archived` is NOT applied here: archiving needs a verified final backup
 *   (OPS-9), so it is reported as `archiveDue` for `tenants:archive`.
 * - One tenant failing never stops the others.
 *
 * PLAN-10 (free plan):
 * - a SCHEDULED downgrade to a free plan (plan_change_requests `scheduled`, filed by the shop) is
 *   applied first, once no paid term is running any more (subscription_ends_at passed or empty; a
 *   trial has none), through MoveTenantToFreePlanAction (source `downgrade`); it wins over the
 *   active -> past_due move of the same day;
 * - a tenant on a permanent free plan (Tenant::isOnFreePlan(), the same lookup EnsureTenantActive
 *   uses) whose stored status is trial / past_due / read_only (rows from before PLAN-10, e.g. an old
 *   free trial that ended in read-only) is put back to `active` on that plan. An operator's
 *   read-only (the last move into read_only was made by a super-admin) is left alone;
 * - an ACTIVE free tenant with a leftover subscription_ends_at / grace_ends_at gets them cleared
 *   (a free plan has no paid term; nothing else changes, no lifecycle event);
 * - a SELF-SIGNUP trial that ended unpaid (policy move trial → active, see
 *   TenantLifecyclePolicy) is moved to the platform's fallback plan as `active` (PLAN-11:
 *   TrialFallbackPlan, the `trial_fallback_plan_id` setting, else the catalog `free` plan).
 *   Both go through MoveTenantToFreePlanAction (lifecycle event, audit, entitlements flush).
 *   When the fallback plan is no longer an active free plan the trial takes the normal
 *   read_only move instead (logged), so a tenant is never left without a valid plan.
 *   Security review: neither when the tenant's owner email (canonical) or phone already belongs to
 *   ANOTHER free / self-signup shop (FreeShopGuard): the trial takes the normal read_only move and
 *   the lifecycle event records `fallback_refused: free_shop_exists`.
 *
 * Access enforcement does not depend on this sweep (EnsureTenantActive reads the derived
 * status); the sweep makes the stored status, the audit trail and the console list match.
 */
final class ApplyTenantLifecycleAction
{
    public function __construct(
        private readonly TenantLifecyclePolicy $policy,
        private readonly TransitionTenantStatusAction $transition,
        private readonly MoveTenantToFreePlanAction $moveToFreePlan,
        private readonly TrialFallbackPlan $trialFallback,
        private readonly FreeShopGuard $freeShops,
    ) {}

    /** Lifecycle-event context of a trial that was refused the free fallback (security review). */
    public const FALLBACK_REFUSED_FREE_SHOP_EXISTS = 'free_shop_exists';

    /** The fallback plan of this run (resolved once): false = not resolved yet. */
    private Plan|false|null $fallbackPlan = false;

    /** @var array<string, PlanChangeRequest> scheduled downgrades of this run, by tenant id */
    private array $scheduledDowngrades = [];

    /** Stored statuses a scheduled downgrade is applied from. */
    private const DOWNGRADABLE = [TenantStatus::Trial, TenantStatus::Active, TenantStatus::PastDue, TenantStatus::ReadOnly];

    /** Stored statuses the free-plan branch puts back to `active`. */
    private const FREE_PLAN_REACTIVATE = [TenantStatus::Trial, TenantStatus::PastDue, TenantStatus::ReadOnly];

    public function execute(bool $dryRun = false, ?CarbonImmutable $now = null): LifecycleSweepResult
    {
        $now ??= CarbonImmutable::now();
        $chunk = max(1, (int) config('tenant_lifecycle.sweep.chunk', 100));

        $checked = 0;
        $applied = [];
        $archiveDue = [];
        $failed = [];
        $needsAttention = [];
        $this->fallbackPlan = false;
        $this->scheduledDowngrades = PlanChangeRequest::query()
            ->with('requestedPlan')
            ->where('kind', PlanChangeRequestKind::PlanChange->value)
            ->where('status', PlanChangeRequestStatus::Scheduled->value)
            ->orderBy('id')
            ->get()
            ->keyBy(static fn (PlanChangeRequest $request): string => (string) $request->tenant_id)
            ->all();

        Tenant::query()
            ->with('plan')
            ->whereNotIn('status', [TenantStatus::Archived->value])
            ->orderBy('id')
            ->chunkById($chunk, function ($tenants) use ($now, $dryRun, &$checked, &$applied, &$archiveDue, &$failed, &$needsAttention): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    $checked++;

                    if ($tenant->isPurged() || $tenant->lifecycleStatus() === null) {
                        continue;
                    }

                    $this->sweepTenant($tenant, $now, $dryRun, $applied, $archiveDue, $failed, $needsAttention);
                }
            });

        return new LifecycleSweepResult($checked, $applied, $archiveDue, $failed, $needsAttention);
    }

    /**
     * @param  list<array{tenant_id: string, from: string, to: string}>  $applied
     * @param  list<string>  $archiveDue
     * @param  list<array{tenant_id: string, error: string}>  $failed
     * @param  list<array{tenant_id: string, status: string}>  $needsAttention
     */
    private function sweepTenant(Tenant $tenant, CarbonImmutable $now, bool $dryRun, array &$applied, array &$archiveDue, array &$failed, array &$needsAttention): void
    {
        $id = (string) $tenant->getKey();
        $stored = $tenant->lifecycleStatus();

        // PLAN-10 review: the shop's scheduled downgrade to a free plan, once no paid term runs.
        $downgrade = $this->scheduledDowngrades[$id] ?? null;
        if ($downgrade instanceof PlanChangeRequest && in_array($stored, self::DOWNGRADABLE, true) && ! $this->paidTermRunning($tenant, $stored, $now)) {
            $target = $downgrade->requestedPlan;
            if ($target instanceof Plan && $target->is_free) {
                $this->apply($id, $stored, TenantStatus::Active, $now, $dryRun, $applied, $failed, function () use ($id, $target, $stored, $downgrade): void {
                    $this->moveToFreePlan->execute($id, $target, $stored, MoveTenantToFreePlanAction::SOURCE_DOWNGRADE, (int) $downgrade->getKey());
                });

                return;
            }

            Log::warning('Tenant lifecycle sweep: scheduled downgrade target is no longer a free plan', ['tenant_id' => $id, 'request_id' => (int) $downgrade->getKey()]);
        }

        // PLAN-10: a free-plan tenant is always active (same lookup as EnsureTenantActive).
        if ($tenant->isOnFreePlan() && $this->sweepFreePlanTenant($tenant, $stored, $now, $dryRun, $applied, $failed)) {
            return;
        }

        $decision = $this->policy->evaluate($tenant->lifecycleSnapshot(), $now);

        // A trial / past_due tenant with no deadline never leaves that status by itself
        // (null dates are NOT treated as expired: that could lock real tenants out).
        if ($decision->nextTransitionAt === null
            && $decision->pendingTransitions === []
            && in_array($decision->effectiveStatus, [TenantStatus::Trial, TenantStatus::PastDue], true)) {
            $needsAttention[] = ['tenant_id' => $id, 'status' => $decision->effectiveStatus->value];
            Log::warning('Tenant lifecycle sweep: tenant has no lifecycle deadline', ['tenant_id' => $id, 'status' => $decision->effectiveStatus->value]);
        }

        foreach ($decision->pendingTransitions as $step) {
            if ($step->to === TenantStatus::Archived) {
                $archiveDue[] = $id;

                return;
            }

            $context = ['source' => 'lifecycle_sweep', 'due_at' => $step->at->toIso8601String()];

            // PLAN-10: the self-signup trial ended unpaid → the platform's free plan, active.
            if ($step->from === TenantStatus::Trial && $step->to === TenantStatus::Active) {
                $fallback = $this->fallbackPlan();
                $ownerHasAnotherShop = $fallback instanceof Plan
                    && $this->freeShops->alreadyHasShop($tenant->email, $tenant->phone, exceptTenantId: $id);

                if ($fallback instanceof Plan && ! $ownerHasAnotherShop) {
                    $this->apply($id, $step->from, $step->to, $step->at, $dryRun, $applied, $failed, function () use ($id, $fallback): void {
                        $this->moveToFreePlan->execute($id, $fallback, TenantStatus::Trial, MoveTenantToFreePlanAction::SOURCE_TRIAL_FALLBACK);
                    });

                    return;
                }

                if ($ownerHasAnotherShop) {
                    Log::warning('Tenant lifecycle sweep: the owner already has another free / self-signup shop, the trial goes read-only', ['tenant_id' => $id]);
                    $context['fallback_refused'] = self::FALLBACK_REFUSED_FREE_SHOP_EXISTS;
                } else {
                    Log::warning('Tenant lifecycle sweep: trial fallback plan is not an active free plan, the trial goes read-only', ['tenant_id' => $id]);
                }
                $step = new LifecycleTransitionStep($step->from, TenantStatus::ReadOnly, $step->at);
            }

            $ok = $this->apply($id, $step->from, $step->to, $step->at, $dryRun, $applied, $failed, function () use ($id, $step, $context): void {
                $this->transition->execute(TenantStatusTransition::to(
                    $id,
                    $step->to,
                    TenantLifecycleActor::System,
                    expectFrom: $step->from,
                    note: null,
                    context: $context,
                ));
            });

            if (! $ok) {
                return;
            }
        }
    }

    /**
     * The free-plan branch. True when the tenant was handled here (the policy never moves a free
     * trial / active / past_due / read_only tenant); suspended / cancelled ones fall through to the
     * policy (retention, archive).
     *
     * @param  list<array{tenant_id: string, from: string, to: string}>  $applied
     * @param  list<array{tenant_id: string, error: string}>  $failed
     */
    private function sweepFreePlanTenant(Tenant $tenant, ?TenantStatus $stored, CarbonImmutable $now, bool $dryRun, array &$applied, array &$failed): bool
    {
        $id = (string) $tenant->getKey();
        $plan = $tenant->plan;

        if ($stored instanceof TenantStatus && in_array($stored, self::FREE_PLAN_REACTIVATE, true)) {
            if ($stored === TenantStatus::ReadOnly && $this->operatorMadeReadOnly($id)) {
                return true;
            }

            if ($plan instanceof Plan) {
                $this->apply($id, $stored, TenantStatus::Active, $now, $dryRun, $applied, $failed, function () use ($id, $plan, $stored): void {
                    $this->moveToFreePlan->execute($id, $plan, $stored, MoveTenantToFreePlanAction::SOURCE_FREE_PLAN);
                });
            }

            return true;
        }

        if ($stored === TenantStatus::Active) {
            if (! $dryRun && ($tenant->subscription_ends_at !== null || $tenant->grace_ends_at !== null)) {
                $this->clearPaidTerm($id);
            }

            return true;
        }

        return false;
    }

    /** A free plan has no paid term: drop leftover dates of an ACTIVE free tenant (locked, re-checked). */
    private function clearPaidTerm(string $id): void
    {
        try {
            DB::connection((string) (new Tenant)->getConnectionName())->transaction(function () use ($id): void {
                /** @var Tenant|null $locked */
                $locked = Tenant::query()->whereKey($id)->lockForUpdate()->first();
                if (! $locked instanceof Tenant
                    || $locked->lifecycleStatus() !== TenantStatus::Active
                    || ! $locked->isOnFreePlan()
                    || ($locked->subscription_ends_at === null && $locked->grace_ends_at === null)) {
                    return;
                }

                $locked->subscription_ends_at = null;
                $locked->grace_ends_at = null;
                $locked->save();
            });

            Log::info('Tenant lifecycle sweep: cleared the paid term of a free-plan tenant', ['tenant_id' => $id]);
        } catch (Throwable $e) {
            Log::warning('Tenant lifecycle sweep: could not clear the paid term of a free-plan tenant', ['tenant_id' => $id, 'error' => $e->getMessage()]);
        }
    }

    /** The last move into read_only was a super-admin decision (not the lifecycle). */
    private function operatorMadeReadOnly(string $id): bool
    {
        $event = TenantLifecycleEvent::query()
            ->where('tenant_id', $id)
            ->where('to_status', TenantStatus::ReadOnly->value)
            ->orderByDesc('id')
            ->first();

        return $event instanceof TenantLifecycleEvent && $event->actor === TenantLifecycleActor::SuperAdmin;
    }

    /** A trial has no paid term; otherwise the term runs while subscription_ends_at is after $now. */
    private function paidTermRunning(Tenant $tenant, ?TenantStatus $stored, CarbonImmutable $now): bool
    {
        return $stored !== TenantStatus::Trial
            && $tenant->subscription_ends_at !== null
            && $tenant->subscription_ends_at->greaterThan($now);
    }

    /**
     * Run one move (unless $dryRun) and record it as applied or failed.
     *
     * @param  list<array{tenant_id: string, from: string, to: string}>  $applied
     * @param  list<array{tenant_id: string, error: string}>  $failed
     * @param  callable(): void  $move
     */
    private function apply(string $id, TenantStatus $from, TenantStatus $to, CarbonImmutable $at, bool $dryRun, array &$applied, array &$failed, callable $move): bool
    {
        if (! $dryRun) {
            try {
                $move();
            } catch (Throwable $e) {
                $failed[] = ['tenant_id' => $id, 'error' => $e->getMessage()];
                Log::warning('Tenant lifecycle sweep: transition failed', [
                    'tenant_id' => $id,
                    'from' => $from->value,
                    'to' => $to->value,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }

            Log::info('Tenant lifecycle sweep: status changed', [
                'tenant_id' => $id,
                'from' => $from->value,
                'to' => $to->value,
                'due_at' => $at->toIso8601String(),
            ]);
        }

        $applied[] = ['tenant_id' => $id, 'from' => $from->value, 'to' => $to->value];

        return true;
    }

    /** PLAN-10/11: the effective trial fallback plan while it is an active permanent free plan. */
    private function fallbackPlan(): ?Plan
    {
        if ($this->fallbackPlan === false) {
            $this->fallbackPlan = $this->trialFallback->plan();
        }

        return $this->fallbackPlan;
    }
}
