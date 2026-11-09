<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\LifecycleSweepResult;
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
 * Access enforcement does not depend on this sweep (EnsureTenantActive reads the derived
 * status); the sweep makes the stored status, the audit trail and the console list match.
 */
final class ApplyTenantLifecycleAction
{
    public function __construct(
        private readonly TenantLifecyclePolicy $policy,
        private readonly TransitionTenantStatusAction $transition,
    ) {}

    public function execute(bool $dryRun = false, ?CarbonImmutable $now = null): LifecycleSweepResult
    {
        $now ??= CarbonImmutable::now();
        $chunk = max(1, (int) config('tenant_lifecycle.sweep.chunk', 100));

        $checked = 0;
        $applied = [];
        $archiveDue = [];
        $failed = [];
        $needsAttention = [];

        Tenant::query()
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

            if (! $dryRun) {
                try {
                    $this->transition->execute(TenantStatusTransition::to(
                        $id,
                        $step->to,
                        TenantLifecycleActor::System,
                        expectFrom: $step->from,
                        note: null,
                        context: ['source' => 'lifecycle_sweep', 'due_at' => $step->at->toIso8601String()],
                    ));
                } catch (Throwable $e) {
                    $failed[] = ['tenant_id' => $id, 'error' => $e->getMessage()];
                    Log::warning('Tenant lifecycle sweep: transition failed', [
                        'tenant_id' => $id,
                        'from' => $step->from->value,
                        'to' => $step->to->value,
                        'error' => $e->getMessage(),
                    ]);

                    return;
                }

                Log::info('Tenant lifecycle sweep: status changed', [
                    'tenant_id' => $id,
                    'from' => $step->from->value,
                    'to' => $step->to->value,
                    'due_at' => $step->at->toIso8601String(),
                ]);
            }

            $applied[] = ['tenant_id' => $id, 'from' => $step->from->value, 'to' => $step->to->value];
        }
    }
}
