<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Signup\TrialFallbackPlan;
use App\Support\Tenancy\LifecycleSweepResult;
use App\Support\Tenancy\LifecycleTransitionStep;
use App\Support\Tenancy\TenantLifecyclePolicy;
use App\Support\Tenancy\TenantStatusTransition;
use Carbon\CarbonImmutable;
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
 * - a tenant whose plan is a permanent free plan (`plans.is_free`) but whose stored status is
 *   trial / past_due (a row from before PLAN-10) is put back to `active` on that plan;
 * - a SELF-SIGNUP trial that ended unpaid (policy move trial → active, see
 *   TenantLifecyclePolicy) is moved to the platform's fallback plan as `active` (PLAN-11:
 *   TrialFallbackPlan, the `trial_fallback_plan_id` setting, else the catalog `free` plan).
 *   Both go through MoveTenantToFreePlanAction (lifecycle event, audit, entitlements flush).
 *   When the fallback plan is no longer an active free plan the trial takes the normal
 *   read_only move instead (logged), so a tenant is never left without a valid plan.
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
    ) {}

    /** The fallback plan of this run (resolved once): false = not resolved yet. */
    private Plan|false|null $fallbackPlan = false;

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

        // PLAN-10: a free-plan tenant is always active (legacy trial / past_due rows).
        $plan = $tenant->plan;
        if ($plan instanceof Plan && $plan->is_free
            && ($stored === TenantStatus::Trial || $stored === TenantStatus::PastDue)) {
            $this->apply($id, $stored, TenantStatus::Active, $now, $dryRun, $applied, $failed, function () use ($id, $plan, $stored): void {
                $this->moveToFreePlan->execute($id, $plan, $stored, MoveTenantToFreePlanAction::SOURCE_FREE_PLAN);
            });

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

            // PLAN-10: the self-signup trial ended unpaid → the platform's free plan, active.
            if ($step->from === TenantStatus::Trial && $step->to === TenantStatus::Active) {
                $fallback = $this->fallbackPlan();

                if ($fallback instanceof Plan) {
                    $this->apply($id, $step->from, $step->to, $step->at, $dryRun, $applied, $failed, function () use ($id, $fallback): void {
                        $this->moveToFreePlan->execute($id, $fallback, TenantStatus::Trial, MoveTenantToFreePlanAction::SOURCE_TRIAL_FALLBACK);
                    });

                    return;
                }

                Log::warning('Tenant lifecycle sweep: trial fallback plan is not an active free plan, the trial goes read-only', ['tenant_id' => $id]);
                $step = new LifecycleTransitionStep($step->from, TenantStatus::ReadOnly, $step->at);
            }

            $ok = $this->apply($id, $step->from, $step->to, $step->at, $dryRun, $applied, $failed, function () use ($id, $step): void {
                $this->transition->execute(TenantStatusTransition::to(
                    $id,
                    $step->to,
                    TenantLifecycleActor::System,
                    expectFrom: $step->from,
                    note: null,
                    context: ['source' => 'lifecycle_sweep', 'due_at' => $step->at->toIso8601String()],
                ));
            });

            if (! $ok) {
                return;
            }
        }
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
