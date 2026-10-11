<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\CentralAuditEvent;
use App\Enums\TenantAccessLevel;
use App\Enums\TenantBackupKind;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Exceptions\TenantLifecycleException;
use App\Models\Tenant;
use App\Models\TenantBackup;
use App\Models\TenantLifecycleEvent;
use App\Services\CentralAuditLogger;
use App\Support\Tenancy\TenantLifecyclePolicy;
use App\Support\Tenancy\TenantStatusTransition;
use App\Support\Tenancy\TenantStatusTransitioned;
use App\Support\Tenancy\TenantSuspensionReason;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The ONLY write path for `tenants.status` (IDEN-3.3).
 *
 * In one CENTRAL transaction, with the tenant row locked (lockForUpdate):
 *  1. checks `expectFrom` against the locked status (stale decision → 409);
 *  2. applies the state machine (TenantStatus via TenantLifecyclePolicy):
 *       - only actor Billing makes a tenant `active` (anyone else → 403);
 *       - archive only from suspended / cancelled (else 409 + TenantArchiveRefused audit);
 *       - an archived tenant only leaves through unarchive, back to status_before_archive;
 *       - OPS-9: `archived` needs a verified final / pre_purge backup of THIS tenant, at most
 *         tenant_lifecycle.archive.final_backup_max_age_minutes old (else 409
 *         final_backup_required + TenantArchiveRefused audit); a purged / purging tenant
 *         never changes again (409 tenant_purged);
 *       - any other move missing from the table → 409;
 *  3. updates the lifecycle columns, appends a `tenant_lifecycle_events` row, and writes
 *     the central audit (TenantStatusChanged / TenantArchived / TenantUnarchived);
 *  4. AFTER commit: dispatches TenantStatusTransitioned and, when the tenant became
 *     blocked, revokes its tokens (RevokeTenantTokensAction, tenant DB, never inside the
 *     central transaction).
 *
 * Nested use: a caller already inside a central transaction (ENTI-3.4 billing activation)
 * gets the lock/rows in its own transaction, and the after-commit work waits for it.
 */
final class TransitionTenantStatusAction
{
    public function __construct(
        private readonly TenantLifecyclePolicy $policy,
        private readonly CentralAuditLogger $audit,
        private readonly RevokeTenantTokensAction $revokeTokens,
    ) {}

    public function execute(TenantStatusTransition $transition): Tenant
    {
        $connection = $this->centralConnection();

        return $connection->transaction(function () use ($transition, $connection): Tenant {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()
                ->whereKey($transition->tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($tenant->isPurged()) {
                throw TenantLifecycleException::tenantPurged();
            }

            $fromRaw = (string) $tenant->status;
            $from = TenantStatus::tryFrom($fromRaw);

            if ($transition->expectFrom !== [] && ($from === null || ! in_array($from, $transition->expectFrom, true))) {
                throw TenantLifecycleException::statusConflict($transition->expectFrom, $fromRaw);
            }

            $to = $transition->isUnarchive()
                ? $this->unarchiveTarget($tenant, $from, $fromRaw)
                : $this->checkedTarget($transition, $tenant, $from, $fromRaw);

            $finalBackup = ! $transition->isUnarchive() && $to === TenantStatus::Archived
                ? $this->provenFinalBackup($transition, $tenant, $fromRaw)
                : null;

            $reason = $this->reasonFor($transition, $tenant, $to);

            $this->applyColumns($tenant, $from, $to, $reason, $transition->isUnarchive(), $finalBackup);

            $event = TenantLifecycleEvent::query()->create([
                'tenant_id' => (string) $tenant->getKey(),
                'from_status' => $from?->value,
                'to_status' => $to->value,
                'actor' => $transition->actor->value,
                'central_user_id' => $transition->causer !== null ? (int) $transition->causer->getKey() : null,
                'reason' => $reason?->value,
                'note' => $transition->note,
                'properties' => $transition->context === [] ? null : $transition->context,
            ]);

            $this->audit->record(
                $this->auditEvent($transition, $to),
                array_merge($transition->context, [
                    'from' => $fromRaw,
                    'to' => $to->value,
                    'actor' => $transition->actor->value,
                    'reason' => $reason?->value,
                    'note' => $transition->note,
                    'lifecycle_event_id' => (int) $event->getKey(),
                ], $finalBackup === null ? [] : [
                    'final_backup_id' => (int) $finalBackup->getKey(),
                    'final_backup_sha256' => $finalBackup->sha256,
                ]),
                $transition->causer,
                $tenant,
            );

            event(new TenantStatusTransitioned(
                tenantId: (string) $tenant->getKey(),
                from: $from,
                to: $to,
                actor: $transition->actor,
                reason: $reason,
                centralUserId: $transition->causer !== null ? (int) $transition->causer->getKey() : null,
                lifecycleEventId: (int) $event->getKey(),
            ));

            if ($to->accessLevel() === TenantAccessLevel::Blocked) {
                $connection->afterCommit(fn () => $this->revokeTokensSafely($tenant));
            }

            return $tenant;
        });
    }

    private function checkedTarget(TenantStatusTransition $transition, Tenant $tenant, ?TenantStatus $from, string $fromRaw): TenantStatus
    {
        /** @var TenantStatus $to */
        $to = $transition->to;
        $actor = $transition->actor;

        if ($to === TenantStatus::Archived && ($from === null || ! $from->canBeArchived())) {
            // Survives the rollback of this transaction (recordAttempt semantics).
            $this->audit->recordAttempt(
                CentralAuditEvent::TenantArchiveRefused,
                ['from' => $fromRaw, 'actor' => $actor->value],
                $transition->causer,
                $tenant,
            );

            throw TenantLifecycleException::archiveNotAllowed($fromRaw);
        }

        if ($to === TenantStatus::Active && $actor !== TenantLifecycleActor::Billing) {
            throw TenantLifecycleException::activationRequiresPayment($actor);
        }

        // Leaving `archived` is unarchive only (target = status_before_archive).
        if ($from === null || $from === TenantStatus::Archived || ! $this->policy->canTransition($from, $to, $actor)) {
            throw TenantLifecycleException::invalidTransition($fromRaw, $to, $actor);
        }

        return $to;
    }

    /**
     * OPS-9 (CTO Q-B13): no `archived` without a proven final backup. The row must belong to
     * THIS tenant, be a final / pre_purge archive, be verified (re-downloaded, sha256 matched)
     * and be recent. Read from the central ledger inside the same central transaction.
     */
    private function provenFinalBackup(TenantStatusTransition $transition, Tenant $tenant, string $fromRaw): TenantBackup
    {
        $maxAge = max(1, (int) config('tenant_lifecycle.archive.final_backup_max_age_minutes', 120));

        $backup = $transition->finalBackupId === null ? null : TenantBackup::query()
            ->whereKey($transition->finalBackupId)
            ->where('tenant_id', (string) $tenant->getKey())
            ->kind(...TenantBackupKind::finalKinds())
            ->whereNotNull('verified_at')
            ->where('created_at', '>=', now()->subMinutes($maxAge))
            ->first();

        if (! $backup instanceof TenantBackup) {
            // Survives the rollback of this transaction (recordAttempt semantics).
            $this->audit->recordAttempt(
                CentralAuditEvent::TenantArchiveRefused,
                [
                    'from' => $fromRaw,
                    'actor' => $transition->actor->value,
                    'reason' => 'final_backup_required',
                    'final_backup_id' => $transition->finalBackupId,
                ],
                $transition->causer,
                $tenant,
            );

            throw TenantLifecycleException::finalBackupRequired($transition->finalBackupId);
        }

        return $backup;
    }

    private function unarchiveTarget(Tenant $tenant, ?TenantStatus $from, string $fromRaw): TenantStatus
    {
        if ($from !== TenantStatus::Archived) {
            throw TenantLifecycleException::notArchived($fromRaw);
        }

        $previous = $tenant->status_before_archive;

        // Legacy rows archived before IDEN-3.2 have no previous status: restore the safest
        // one (suspended still blocks access, and the operator decides what comes next).
        if ($previous === null || ! TenantStatus::Archived->canTransitionTo($previous, TenantLifecycleActor::SuperAdmin)) {
            return TenantStatus::Suspended;
        }

        return $previous;
    }

    private function reasonFor(TenantStatusTransition $transition, Tenant $tenant, TenantStatus $to): ?TenantSuspensionReason
    {
        return match ($to) {
            TenantStatus::Suspended => $transition->reason
                ?? ($transition->isUnarchive() ? $tenant->suspension_reason : null)
                ?? ($transition->actor === TenantLifecycleActor::System
                    ? TenantSuspensionReason::NonPayment
                    : throw TenantLifecycleException::reasonRequired()),
            TenantStatus::Cancelled, TenantStatus::Archived => $transition->reason ?? $tenant->suspension_reason,
            default => null,
        };
    }

    private function applyColumns(Tenant $tenant, ?TenantStatus $from, TenantStatus $to, ?TenantSuspensionReason $reason, bool $unarchive, ?TenantBackup $finalBackup): void
    {
        $now = now();

        $tenant->status = $to->value;
        $tenant->status_changed_at = $now;
        $tenant->suspension_reason = $reason;

        if ($to === TenantStatus::ReadOnly) {
            $tenant->read_only_since = $now;
        } elseif ($to === TenantStatus::Active || $to === TenantStatus::Trial) {
            $tenant->read_only_since = null;
        }

        if ($to === TenantStatus::Archived) {
            $tenant->status_before_archive = $from;
            $tenant->archived_at = $now;
            $tenant->final_backup_id = $finalBackup !== null ? (int) $finalBackup->getKey() : null;
            $tenant->archive_state = null;
            $tenant->archive_error_code = null;
        } elseif ($unarchive) {
            $tenant->status_before_archive = null;
            $tenant->archived_at = null;
            $tenant->final_backup_id = null;
            $tenant->archive_state = null;
            $tenant->archive_error_code = null;
        }

        $tenant->save();
    }

    private function auditEvent(TenantStatusTransition $transition, TenantStatus $to): CentralAuditEvent
    {
        if ($transition->isUnarchive()) {
            return CentralAuditEvent::TenantUnarchived;
        }

        return $to === TenantStatus::Archived
            ? CentralAuditEvent::TenantArchived
            : CentralAuditEvent::TenantStatusChanged;
    }

    /**
     * The status change is already committed and the access middleware (IDEN-3.5) blocks
     * the tenant on its own; a tenant DB that is down must not turn the transition into an
     * error for the caller. The failure is reported for the operators.
     */
    private function revokeTokensSafely(Tenant $tenant): void
    {
        try {
            $this->revokeTokens->execute($tenant);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function centralConnection(): Connection
    {
        return DB::connection((new TenantLifecycleEvent)->getConnectionName());
    }
}
