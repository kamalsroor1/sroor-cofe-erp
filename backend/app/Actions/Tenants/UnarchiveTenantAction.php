<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Services\Entitlements\TenantEntitlementService;
use App\Support\Tenancy\TenantStatusTransition;
use Illuminate\Support\Facades\DB;

/**
 * POST /api/v1/super-admin/tenants/{id}/unarchive (OPS-9, CTO W1 Q2): an archived tenant goes
 * back to the status it had before (suspended / cancelled), until it is purged.
 *
 * Everything is enforced by the single status writer under the row lock: not archived →
 * 409 subscription.not_archived; purged / purging → 409 subscription.tenant_purged. The
 * archive columns (archived_at, final_backup_id, archive_state) are cleared there, the
 * final backup itself stays in the ledger (never pruned).
 *
 * FREE-15: a shop that is on a free plan AND was archived by the dormant policy (its last
 * `archived` lifecycle event carries source `dormant_free_shop`) comes back `active` in the same
 * call (support restores it in one step). A free plan has no billing to wait for, so `suspended`
 * would only be a dead end. The second hop is `suspended -> active`, which the state machine
 * reserves for actor Billing (activation never comes from a manual flag); here it is the
 * free-plan activation, recorded with source `dormant_free_restore` and the operator as causer.
 * Both hops run in ONE central transaction (all or nothing). A shop archived by hand for any
 * other reason (violation, ...) and every paid shop keep the old behaviour (restored suspended).
 */
final class UnarchiveTenantAction
{
    public const RESTORE_SOURCE = 'dormant_free_restore';

    public function __construct(
        private readonly TransitionTenantStatusAction $transition,
        private readonly TenantEntitlementService $entitlements,
    ) {}

    public function execute(string $tenantId, CentralUser $operator, ?string $note): Tenant
    {
        $connection = DB::connection((new TenantLifecycleEvent)->getConnectionName());

        return $connection->transaction(function () use ($connection, $tenantId, $operator, $note): Tenant {
            // Decided before the unarchive clears the archive columns; the transition re-checks
            // `archived` under its own row lock (409 not_archived), so a stale read is harmless.
            $restoreActive = $this->isDormantFreeArchive($tenantId);

            $tenant = $this->transition->execute(TenantStatusTransition::unarchive($tenantId, $operator, $note));

            if ($restoreActive && $tenant->isOnFreePlan() && $tenant->status === TenantStatus::Suspended->value) {
                $tenant = $this->transition->execute(TenantStatusTransition::to(
                    $tenantId,
                    TenantStatus::Active,
                    TenantLifecycleActor::Billing,
                    expectFrom: TenantStatus::Suspended,
                    note: $note,
                    causer: $operator,
                    context: ['source' => self::RESTORE_SOURCE],
                ));

                $connection->afterCommit(fn () => $this->entitlements->forget($tenantId));
            }

            return $tenant->refresh();
        });
    }

    private function isDormantFreeArchive(string $tenantId): bool
    {
        $tenant = Tenant::query()->whereKey($tenantId)->first();

        if (! $tenant instanceof Tenant || ! $tenant->isOnFreePlan()) {
            return false;
        }

        $archive = TenantLifecycleEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('to_status', TenantStatus::Archived->value)
            ->orderByDesc('id')
            ->first();

        return $archive instanceof TenantLifecycleEvent
            && ($archive->properties['source'] ?? null) === HandleDormantFreeShopsAction::SOURCE;
    }
}
