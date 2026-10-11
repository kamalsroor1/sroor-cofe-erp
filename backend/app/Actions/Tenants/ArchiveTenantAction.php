<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\CentralAuditEvent;
use App\Enums\TenantArchiveState;
use App\Enums\TenantBackupKind;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Exceptions\TenantLifecycleException;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Services\CentralAuditLogger;
use App\Support\Tenancy\TenantStatusTransition;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * OPS-9: archive ONE tenant with a proven final backup (ArchiveTenantJob, `tenants:archive`).
 *
 *  1. Cache lock `tenant-lifecycle:{id}` (shared with tenants:purge);
 *  2. central transaction + row lock: re-check (not purged, still in $expectFrom and
 *     archivable), archive_state = running;
 *  3. OUTSIDE any transaction: TakeTenantBackupAction(final) — dump, encrypt, upload, verify
 *     on every configured disk;
 *  4. TransitionTenantStatusAction → `archived` naming that backup (the single writer
 *     re-checks it: same tenant, final kind, verified, recent), archived_at set, state cleared.
 *
 * Failure at any step: the status is untouched, archive_state = failed with a code
 * (final_backup_failed | status_changed | purged | unknown), TenantArchiveRefused audit,
 * and TenantLifecycleException::archiveFailed($code) is thrown (the job is marked failed).
 */
final class ArchiveTenantAction
{
    public const FINAL_BACKUP_FAILED = 'final_backup_failed';

    public const STATUS_CHANGED = 'status_changed';

    public const PURGED = 'purged';

    public const UNKNOWN = 'unknown';

    public function __construct(
        private readonly TakeTenantBackupAction $takeBackup,
        private readonly TransitionTenantStatusAction $transition,
        private readonly CentralAuditLogger $audit,
    ) {}

    public static function lockKey(string $tenantId): string
    {
        return 'tenant-lifecycle:'.$tenantId;
    }

    /**
     * @param  list<TenantStatus>  $expectFrom  statuses the requester saw (suspended / cancelled)
     * @param  array<string, scalar|null>  $context  extra audit context (no secrets)
     */
    public function execute(
        string $tenantId,
        TenantLifecycleActor $actor,
        array $expectFrom,
        ?CentralUser $causer,
        ?string $note,
        array $context = [],
    ): Tenant {
        $lock = Cache::lock(self::lockKey($tenantId), (int) config('tenant_lifecycle.archive.job_timeout_seconds', 1800) + 60);
        if (! $lock->get()) {
            // Another archive / a purge of this tenant is running: never touch its state.
            throw TenantLifecycleException::archiveInProgress(Tenant::query()->find($tenantId)?->archive_state);
        }

        try {
            $tenant = $this->claim($tenantId, $expectFrom, $causer, $actor);

            try {
                $backup = $this->takeBackup->execute($tenant, TenantBackupKind::Final);
            } catch (Throwable $e) {
                report($e);
                $this->fail($tenantId, self::FINAL_BACKUP_FAILED, $e, $causer, $actor);
            }

            try {
                return $this->transition->execute(TenantStatusTransition::to(
                    $tenantId,
                    TenantStatus::Archived,
                    $actor,
                    expectFrom: $expectFrom,
                    note: $note,
                    causer: $causer,
                    context: $context + ['archive_flow' => 'ops9'],
                    finalBackupId: (int) $backup->getKey(),
                ));
            } catch (TenantLifecycleException $e) {
                $this->fail($tenantId, match ($e->errorCode()) {
                    'subscription.tenant_purged' => self::PURGED,
                    'subscription.status_conflict', 'subscription.archive_not_allowed', 'subscription.invalid_transition' => self::STATUS_CHANGED,
                    'subscription.final_backup_required' => self::FINAL_BACKUP_FAILED,
                    default => self::UNKNOWN,
                }, $e, $causer, $actor);
            } catch (Throwable $e) {
                report($e);
                $this->fail($tenantId, self::UNKNOWN, $e, $causer, $actor);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<TenantStatus>  $expectFrom
     */
    private function claim(string $tenantId, array $expectFrom, ?CentralUser $causer, TenantLifecycleActor $actor): Tenant
    {
        $code = null;

        $tenant = $this->centralConnection()->transaction(function () use ($tenantId, $expectFrom, &$code): ?Tenant {
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->first();
            if (! $tenant instanceof Tenant) {
                $code = self::UNKNOWN;

                return null;
            }

            $from = $tenant->lifecycleStatus();
            if ($tenant->isPurged()) {
                $code = self::PURGED;
            } elseif ($from === null || ! $from->canBeArchived() || ($expectFrom !== [] && ! in_array($from, $expectFrom, true))) {
                $code = self::STATUS_CHANGED;
            }

            if ($code !== null) {
                return null;
            }

            $tenant->forceFill([
                'archive_state' => TenantArchiveState::Running,
                'archive_requested_at' => $tenant->archive_requested_at ?? now(),
                'archive_error_code' => null,
            ])->save();

            return $tenant;
        });

        if (! $tenant instanceof Tenant) {
            $this->fail($tenantId, $code ?? self::UNKNOWN, null, $causer, $actor);
        }

        return $tenant;
    }

    /**
     * Record the failure (status untouched) and stop.
     */
    private function fail(string $tenantId, string $code, ?Throwable $cause, ?CentralUser $causer, TenantLifecycleActor $actor): never
    {
        $tenant = null;

        try {
            $tenant = self::markFailed($tenantId, $code);
        } catch (Throwable $e) {
            report($e);
        }

        // The single writer already audited these two refusals (TenantArchiveRefused).
        $alreadyAudited = $cause instanceof TenantLifecycleException
            && in_array($cause->errorCode(), ['subscription.archive_not_allowed', 'subscription.final_backup_required'], true);

        if (! $alreadyAudited) {
            try {
                $this->audit->recordAttempt(
                    CentralAuditEvent::TenantArchiveRefused,
                    [
                        'reason' => 'archive_failed',
                        'archive_error_code' => $code,
                        'actor' => $actor->value,
                        'exception' => $cause !== null ? $cause::class : null,
                    ],
                    $causer,
                    $tenant,
                    $tenantId,
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        throw TenantLifecycleException::archiveFailed($code);
    }

    /**
     * archive_state = failed + code, only while an archive is pending and the tenant is not
     * archived (a late failure never overwrites a success). Also used by ArchiveTenantJob::failed().
     */
    public static function markFailed(string $tenantId, string $code): ?Tenant
    {
        return DB::connection((new TenantLifecycleEvent)->getConnectionName())->transaction(static function () use ($tenantId, $code): ?Tenant {
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->first();
            if (! $tenant instanceof Tenant) {
                return null;
            }

            if ($tenant->lifecycleStatus() !== TenantStatus::Archived && $tenant->archive_state !== TenantArchiveState::Failed) {
                $tenant->forceFill([
                    'archive_state' => TenantArchiveState::Failed,
                    'archive_error_code' => $code,
                ])->save();
            }

            return $tenant;
        });
    }

    private function centralConnection(): Connection
    {
        return DB::connection((new TenantLifecycleEvent)->getConnectionName());
    }
}
