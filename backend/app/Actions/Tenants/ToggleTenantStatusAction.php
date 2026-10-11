<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\CentralAuditEvent;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Exceptions\TenantLifecycleException;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Services\CentralAuditLogger;
use App\Support\Tenancy\TenantStatusTransition;
use App\Support\Tenancy\TenantSuspensionReason;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Legacy super-admin "toggle status" (POST /api/v1/super-admin/tenants/{id}/toggle-status),
 * routed through the IDEN-3.3 state machine: TransitionTenantStatusAction is the only
 * writer of `tenants.status` (actor SuperAdmin, causer = the operator).
 *
 * One CENTRAL transaction, tenant row locked first:
 *  - `active`: refused with 409 `subscription.invalid_transition` (details.to = active,
 *    details.actor = super_admin). Only a verified payment (actor Billing) activates a
 *    tenant; the legacy "reactivate" and "extend the paid period" are billing operations.
 *  - `trial` on a tenant already in trial: no status change; `extend_days` (> 0, else 409)
 *    extends `trial_ends_at` from max(now, current end) as before. Audited
 *    `tenant_trial_extended`.
 *  - `trial` from another status: the state-machine move (read_only → trial only), then
 *    `trial_ends_at` = now + extend_days (or tenant_lifecycle.trial_extension_days when 0)
 *    and `trial_extended_at` stamped. Audited by the transition and `tenant_trial_extended`.
 *  - `suspended` / `read_only` / `cancelled`: the state-machine move with the reason/note
 *    (a suspension needs a reason: 422 otherwise).
 * Any move the state machine refuses is the transition's own 403/409/422.
 */
final class ToggleTenantStatusAction
{
    public function __construct(
        private readonly TransitionTenantStatusAction $transition,
        private readonly CentralAuditLogger $audit,
    ) {}

    public function execute(
        string $tenantId,
        TenantStatus $status,
        CentralUser $causer,
        int $extendDays = 0,
        ?TenantSuspensionReason $reason = null,
        ?string $note = null,
    ): Tenant {
        $connection = DB::connection((new TenantLifecycleEvent)->getConnectionName());

        return $connection->transaction(function () use ($tenantId, $status, $causer, $extendDays, $reason, $note): Tenant {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();
            $fromRaw = (string) $tenant->status;
            $from = TenantStatus::tryFrom($fromRaw);

            if ($status === TenantStatus::Active) {
                throw TenantLifecycleException::invalidTransition($fromRaw, TenantStatus::Active, TenantLifecycleActor::SuperAdmin);
            }

            if ($status === TenantStatus::Trial && $from === TenantStatus::Trial) {
                if ($extendDays <= 0) {
                    throw TenantLifecycleException::invalidTransition($fromRaw, TenantStatus::Trial, TenantLifecycleActor::SuperAdmin);
                }

                return $this->extendTrial($tenant, $this->extend($tenant->trial_ends_at, $extendDays), $extendDays, $causer, false);
            }

            $moved = $this->transition->execute(TenantStatusTransition::to(
                (string) $tenant->getKey(),
                $status,
                TenantLifecycleActor::SuperAdmin,
                expectFrom: $from !== null ? [$from] : [],
                reason: $reason,
                note: $note,
                causer: $causer,
                context: ['source' => 'toggle_status'],
            ));

            if ($status !== TenantStatus::Trial) {
                return $moved;
            }

            $days = $extendDays > 0 ? $extendDays : max(1, (int) config('tenant_lifecycle.trial_extension_days', 7));

            return $this->extendTrial($moved, now()->addDays($days), $days, $causer, true);
        });
    }

    private function extendTrial(Tenant $tenant, Carbon $endsAt, int $days, CentralUser $causer, bool $fromReadOnly): Tenant
    {
        $previousEnd = $tenant->trial_ends_at?->toIso8601String();

        $tenant->trial_ends_at = $endsAt;
        if ($fromReadOnly) {
            $tenant->trial_extended_at = now();
        }
        $tenant->save();

        $this->audit->record(
            CentralAuditEvent::TenantTrialExtended,
            [
                'source' => 'toggle_status',
                'days' => $days,
                'previous_trial_ends_at' => $previousEnd,
                'trial_ends_at' => $endsAt->toIso8601String(),
                'from_read_only' => $fromReadOnly,
            ],
            $causer,
            $tenant,
        );

        return $tenant;
    }

    private function extend(?Carbon $currentEnd, int $days): Carbon
    {
        $base = $currentEnd !== null && $currentEnd->isFuture() ? $currentEnd->copy() : now();

        return $base->addDays($days);
    }
}
