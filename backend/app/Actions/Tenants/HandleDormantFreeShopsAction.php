<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\DTOs\Tenants\DormantFreeShopsRunResult;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantBackupKind;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Mail\DormantShopArchivedMail;
use App\Mail\DormantShopReminderMail;
use App\Mail\PlatformMailable;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Models\TenantLifecycleReminder;
use App\Services\CentralAuditLogger;
use App\Services\Plans\FreePlanIds;
use App\Support\PlatformHosts;
use App\Support\Tenancy\TenantStatusTransition;
use App\Support\Tenancy\TenantSuspensionReason;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * FREE-15: dormant FREE shops (every shop is its own database), daily, central.
 *
 * Candidates: provisioned, not purged tenants on a permanent free plan (FreePlanIds, the same
 * lookup as Tenant::isOnFreePlan()) whose stored status is active or read_only, and whose clock
 * (last_activity_at, else created_at) is at least dormant.remind_after_days old. Paying, trial,
 * past_due, suspended, cancelled and archived shops are never selected.
 *
 *  - remind_after_days (60): ONE reminder email to the owner per dormancy period. The ledger row
 *    (tenant_lifecycle_reminders, kind `dormant.remind`, target_date = the clock date) is unique per
 *    tenant and clock date, so the same dormancy is reminded once, and a shop that is active again
 *    gets a new clock date and, when it goes quiet again, a new reminder. No reset code needed.
 *  - archive_after_days (90): ARCHIVE through the existing path, never delete. Only when the
 *    reminder of this dormancy was sent at least min_reminder_gap_days earlier; a shop that never
 *    got it is reminded first and the archive waits (postponed). The archive notice email follows.
 *
 * Archive = the ONE status writer (TransitionTenantStatusAction, actor System) and the OPS-9 rules
 * unchanged: a verified FINAL backup first (TakeTenantBackupAction, outside any transaction), then
 * the legal System chain active -> past_due -> read_only -> suspended -> archived (the state
 * machine only archives from suspended / cancelled, and only a super-admin may suspend an active
 * shop directly). Every step is a lifecycle event + audit row with source `dormant_free_shop`.
 * Unarchive (super-admin) puts the shop back and restarts its clock. PurgeTenantAction and the
 * tenant database are never touched here.
 *
 * A shop whose owner has no valid email is NEVER reminded and therefore never archived (the
 * archive needs a sent reminder): the safe side, since nobody could have been warned. It stays
 * active and is counted as skipped on every run until an operator fixes the address.
 *
 * Stranded shops: the walk to `archived` is several committed steps. If one fails midway (a
 * transient DB error after `suspended`), the shop would sit `suspended` with its tokens revoked and
 * never be selected again. So each run also picks free-plan `suspended` shops whose LAST lifecycle
 * event carries source `dormant_free_shop` and finishes the archive (fresh verified backup, then
 * suspended -> archived). A mid-walk failure is logged and written to the central audit
 * (TenantArchiveRefused, reason `dormant_walk_failed`).
 *
 * Safe to rerun: a cache lock stops overlapping runs, a tenant is re-read (fresh clock and
 * status) before every mail and before the archive, each tenant has its own try/catch (the log
 * carries the exception class and tenant id only) and one run starts at most
 * dormant.max_archives_per_run archives.
 */
final class HandleDormantFreeShopsAction
{
    public const KIND_REMIND = 'dormant.remind';

    public const KIND_ARCHIVED = 'dormant.archived';

    public const SOURCE = 'dormant_free_shop';

    private const LOCK = 'tenants:handle-dormant-free';

    private const LOCK_SECONDS = 3600;

    public function __construct(
        private readonly FreePlanIds $freePlans,
        private readonly TakeTenantBackupAction $takeBackup,
        private readonly TransitionTenantStatusAction $transition,
        private readonly CentralAuditLogger $audit,
    ) {}

    public function execute(bool $dryRun = false, ?CarbonImmutable $now = null): DormantFreeShopsRunResult
    {
        if (! (bool) config('tenant_lifecycle.dormant.enabled', true)) {
            return new DormantFreeShopsRunResult(DormantFreeShopsRunResult::STATE_DISABLED, $dryRun);
        }

        $freePlanIds = $this->freePlans->all();
        if ($freePlanIds === []) {
            return new DormantFreeShopsRunResult(DormantFreeShopsRunResult::STATE_NO_FREE_PLANS, $dryRun);
        }

        $lock = Cache::lock(self::LOCK, self::LOCK_SECONDS);
        if (! $lock->get()) {
            return new DormantFreeShopsRunResult(DormantFreeShopsRunResult::STATE_LOCKED, $dryRun);
        }

        try {
            return $this->run($freePlanIds, $dryRun, $now ?? CarbonImmutable::now());
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<int>  $freePlanIds
     */
    private function run(array $freePlanIds, bool $dryRun, CarbonImmutable $now): DormantFreeShopsRunResult
    {
        $remindAfter = $this->remindAfterDays();
        $maxArchives = max(1, (int) config('tenant_lifecycle.dormant.max_archives_per_run', 25));
        $chunk = max(1, (int) config('tenant_lifecycle.dormant.chunk', 100));

        $checked = 0;
        $reminded = [];
        $archived = [];
        $postponed = [];
        $skipped = [];
        $failed = [];

        // Column names are constants; the cutoff is a bound parameter.
        Tenant::query()
            ->provisioned()
            ->whereIn('status', [TenantStatus::Active->value, TenantStatus::ReadOnly->value])
            ->whereIn('plan_id', $freePlanIds)
            ->whereRaw('COALESCE(last_activity_at, created_at) <= ?', [$now->subDays($remindAfter)->toDateTimeString()])
            ->orderBy('id')
            ->chunkById($chunk, function ($tenants) use ($now, $dryRun, $maxArchives, &$checked, &$reminded, &$archived, &$postponed, &$skipped, &$failed): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    $checked++;
                    $id = (string) $tenant->getKey();

                    try {
                        $outcome = $this->handle($tenant, $now, $dryRun, count($archived) >= $maxArchives);
                    } catch (Throwable $e) {
                        Log::warning('Dormant free shop handling failed.', ['exception' => $e::class, 'tenant_id' => $id]);
                        $failed[] = $id;

                        continue;
                    }

                    match ($outcome) {
                        'reminded' => $reminded[] = $id,
                        'archived' => $archived[] = $id,
                        'postponed' => $postponed[] = $id,
                        'skipped' => $skipped[] = $id,
                        default => null,
                    };
                }
            });

        foreach ($this->strandedCandidates($freePlanIds) as $stranded) {
            $checked++;
            $id = (string) $stranded->getKey();

            try {
                if (count($archived) >= $maxArchives) {
                    $skipped[] = $id;
                } elseif ($this->archive($stranded, $now, $dryRun, $stranded->dormantDays($now), true)) {
                    $archived[] = $id;
                } else {
                    $skipped[] = $id;
                }
            } catch (Throwable $e) {
                Log::warning('Dormant free shop stranded-archive recovery failed.', ['exception' => $e::class, 'tenant_id' => $id]);
                $failed[] = $id;
            }
        }

        Log::info('Dormant free shops run finished.', [
            'checked' => $checked,
            'reminded' => count($reminded),
            'archived' => count($archived),
            'postponed' => count($postponed),
            'failed' => count($failed),
            'dry_run' => $dryRun,
        ]);

        return new DormantFreeShopsRunResult(DormantFreeShopsRunResult::STATE_RAN, $dryRun, $checked, $reminded, $archived, $postponed, $skipped, $failed);
    }

    /**
     * Free-plan shops left `suspended` by an interrupted dormant archive: the latest lifecycle
     * event is the dormant policy's own move into `suspended`. The (few) suspended free shops are
     * read, then the event is checked in PHP (no JSON-path SQL, portable across drivers).
     *
     * @param  list<int>  $freePlanIds
     * @return list<Tenant>
     */
    private function strandedCandidates(array $freePlanIds): array
    {
        $found = [];

        Tenant::query()
            ->provisioned()
            ->where('status', TenantStatus::Suspended->value)
            ->whereIn('plan_id', $freePlanIds)
            ->orderBy('id')
            ->chunkById(100, function ($tenants) use (&$found): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    if ($this->isStranded($tenant)) {
                        $found[] = $tenant;
                    }
                }
            });

        return $found;
    }

    private function isStranded(Tenant $tenant): bool
    {
        if ($tenant->lifecycleStatus() !== TenantStatus::Suspended || ! $tenant->isOnFreePlan()) {
            return false;
        }

        $last = TenantLifecycleEvent::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->orderByDesc('id')
            ->first();

        return $last instanceof TenantLifecycleEvent
            && $last->to_status === TenantStatus::Suspended
            && ($last->properties['source'] ?? null) === self::SOURCE;
    }

    /**
     * @return 'reminded'|'archived'|'postponed'|'skipped'|'none'
     */
    private function handle(Tenant $candidate, CarbonImmutable $now, bool $dryRun, bool $archiveCapReached): string
    {
        $tenant = $this->fresh($candidate, $now);
        if ($tenant === null) {
            return 'skipped'; // active again, moved on or no longer free since the list was read
        }

        $anchor = $tenant->activityAnchor();
        $days = $tenant->dormantDays($now);
        $reminder = $anchor === null ? null : $this->reminderRow($tenant, $anchor);

        if ($days >= $this->archiveAfterDays()) {
            if ($reminder === null) {
                // The 60-day reminder was missed: send it now, the archive waits for a later run.
                return $this->remind($tenant, $now, $dryRun) ? 'postponed' : 'skipped';
            }

            if ($reminder->created_at === null || $reminder->created_at->greaterThan($now->subDays($this->minReminderGapDays()))) {
                return 'postponed';
            }

            if ($archiveCapReached) {
                return 'skipped';
            }

            return $this->archive($tenant, $now, $dryRun, $days) ? 'archived' : 'skipped';
        }

        if ($reminder !== null) {
            return 'none';
        }

        return $this->remind($tenant, $now, $dryRun) ? 'reminded' : 'none';
    }

    /**
     * The tenant re-read now, or null when it is no longer a dormant free candidate
     * (activity resumed, status or plan changed since the candidate list was read).
     */
    private function fresh(Tenant $candidate, CarbonImmutable $now): ?Tenant
    {
        $tenant = Tenant::query()->provisioned()->whereKey($candidate->getKey())->first();

        if (! $tenant instanceof Tenant
            || ! in_array($tenant->lifecycleStatus(), [TenantStatus::Active, TenantStatus::ReadOnly], true)
            || ! $tenant->isOnFreePlan()
            || $tenant->dormantDays($now) < $this->remindAfterDays()) {
            return null;
        }

        return $tenant;
    }

    private function remind(Tenant $tenant, CarbonImmutable $now, bool $dryRun): bool
    {
        $anchor = $tenant->activityAnchor();
        if ($anchor === null || ! $this->hasOwnerEmail($tenant)) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        try {
            $row = TenantLifecycleReminder::query()->create([
                'tenant_id' => (string) $tenant->getKey(),
                'kind' => self::KIND_REMIND,
                'target_date' => $anchor->toDateString(),
                'recipients' => 1,
            ]);
        } catch (QueryException $e) {
            if (! $this->isDuplicateKey($e)) {
                throw $e; // counted as failed by the per-tenant catch
            }

            return false; // the unique key: this dormancy was reminded already (a racing run)
        }

        try {
            $locale = $this->localeOf($tenant);
            $archiveOn = $this->archiveDate($anchor, $now);

            Mail::to((string) $tenant->email)->queue(new DormantShopReminderMail(
                businessName: (string) $tenant->name,
                loginUrl: $this->loginUrl($tenant),
                inactiveDays: $tenant->dormantDays($now),
                archiveOn: $archiveOn->locale(PlatformMailable::resolveLocale($locale))->translatedFormat('j F Y'),
                locale: $locale,
            ));

            $row->forceFill(['tenant_notified_at' => now()])->save();
        } catch (Throwable $e) {
            // Nothing was queued: free the once-only slot so tomorrow's run retries.
            $row->delete();

            throw $e;
        }

        return true;
    }

    /** SQLSTATE 23000 (integrity constraint) covers MySQL 1062 and SQLite; 23505 is PostgreSQL. */
    private function isDuplicateKey(QueryException $e): bool
    {
        $state = (string) ($e->errorInfo[0] ?? $e->getCode());

        return in_array($state, ['23000', '23505'], true)
            && (str_contains(strtolower($e->getMessage()), 'unique') || str_contains(strtolower($e->getMessage()), 'duplicate'));
    }

    private function archive(Tenant $tenant, CarbonImmutable $now, bool $dryRun, int $days, bool $stranded = false): bool
    {
        if ($dryRun) {
            return true;
        }

        $id = (string) $tenant->getKey();
        $lock = Cache::lock(ArchiveTenantAction::lockKey($id), (int) config('tenant_lifecycle.archive.job_timeout_seconds', 1800) + 60);
        if (! $lock->get()) {
            return false; // an archive / purge of this shop is already running: its state wins
        }

        try {
            // Outside any transaction: dump, encrypt, upload and verify (throws when not proven).
            $backup = $this->takeBackup->execute($tenant, TenantBackupKind::Final);

            // The backup took a while: the owner may have come back meanwhile.
            if ($stranded) {
                $current = Tenant::query()->provisioned()->whereKey($tenant->getKey())->first();
                if (! $current instanceof Tenant || ! $this->isStranded($current)) {
                    return false; // an operator moved it meanwhile
                }
            } else {
                $current = $this->fresh($tenant, CarbonImmutable::now());
                if ($current === null || $current->dormantDays(CarbonImmutable::now()) < $this->archiveAfterDays()) {
                    return false;
                }
            }

            $context = ['source' => self::SOURCE, 'dormant_days' => $days];
            $from = $current->lifecycleStatus() ?? TenantStatus::Active;

            try {
                foreach ($this->pathToSuspended($from) as $step) {
                    $this->transition->execute(TenantStatusTransition::to(
                        $id,
                        $step['to'],
                        TenantLifecycleActor::System,
                        expectFrom: $step['from'],
                        reason: $step['to'] === TenantStatus::Suspended ? TenantSuspensionReason::Other : null,
                        context: $context,
                    ));
                }

                $archivedTenant = $this->transition->execute(TenantStatusTransition::to(
                    $id,
                    TenantStatus::Archived,
                    TenantLifecycleActor::System,
                    expectFrom: TenantStatus::Suspended,
                    context: $context,
                    finalBackupId: (int) $backup->getKey(),
                ));
            } catch (Throwable $e) {
                // Earlier steps are committed: the shop may now sit suspended. The next run finishes it.
                Log::warning('Dormant free shop archive walk failed midway.', ['exception' => $e::class, 'tenant_id' => $id]);
                $this->audit->recordAttempt(
                    CentralAuditEvent::TenantArchiveRefused,
                    ['reason' => 'dormant_walk_failed', 'source' => self::SOURCE, 'exception' => $e::class],
                    null,
                    $current,
                );

                throw $e;
            }
        } finally {
            $lock->release();
        }

        $this->notifyArchived($archivedTenant, $days);

        return true;
    }

    /**
     * The legal System moves from $from up to `suspended` (active -> past_due -> read_only ->
     * suspended; read_only -> suspended).
     *
     * @return list<array{from: TenantStatus, to: TenantStatus}>
     */
    private function pathToSuspended(TenantStatus $from): array
    {
        $chain = [TenantStatus::Active, TenantStatus::PastDue, TenantStatus::ReadOnly, TenantStatus::Suspended];
        $start = array_search($from, $chain, true);
        $steps = [];

        if ($start === false) {
            return $steps;
        }

        for ($i = $start; $i < count($chain) - 1; $i++) {
            $steps[] = ['from' => $chain[$i], 'to' => $chain[$i + 1]];
        }

        return $steps;
    }

    private function notifyArchived(Tenant $tenant, int $days): void
    {
        $anchor = $tenant->activityAnchor();

        try {
            if ($anchor !== null) {
                TenantLifecycleReminder::query()->firstOrCreate(
                    ['tenant_id' => (string) $tenant->getKey(), 'kind' => self::KIND_ARCHIVED, 'target_date' => $anchor->toDateString()],
                    ['recipients' => 1],
                );
            }

            if ($this->hasOwnerEmail($tenant)) {
                Mail::to((string) $tenant->email)->queue(new DormantShopArchivedMail(
                    businessName: (string) $tenant->name,
                    inactiveDays: $days,
                    locale: $this->localeOf($tenant),
                ));
            }
        } catch (Throwable $e) {
            // The shop IS archived; a lost notice must not turn that into a failure.
            Log::warning('Dormant free shop archive notice failed.', ['exception' => $e::class, 'tenant_id' => (string) $tenant->getKey()]);
        }
    }

    private function reminderRow(Tenant $tenant, CarbonImmutable $anchor): ?TenantLifecycleReminder
    {
        return TenantLifecycleReminder::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('kind', self::KIND_REMIND)
            ->whereDate('target_date', $anchor->toDateString())
            ->first();
    }

    /** The day the shop is archived: the archive threshold, but never sooner than the reminder gap from today. */
    private function archiveDate(CarbonImmutable $anchor, CarbonImmutable $now): CarbonImmutable
    {
        $threshold = $anchor->addDays($this->archiveAfterDays());
        $earliest = $now->addDays($this->minReminderGapDays());

        return $threshold->greaterThan($earliest) ? $threshold : $earliest;
    }

    private function loginUrl(Tenant $tenant): string
    {
        $host = $tenant->domains()->orderBy('id')->value('domain');

        return PlatformHosts::url(is_string($host) && $host !== '' ? $host : PlatformHosts::tenantHost((string) $tenant->slug), '/login');
    }

    /** The language the owner signed up with (self-signup), else null = the platform default. */
    private function localeOf(Tenant $tenant): ?string
    {
        $locale = SignupRequest::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->whereNotNull('locale')
            ->orderByDesc('id')
            ->value('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    private function hasOwnerEmail(Tenant $tenant): bool
    {
        return filter_var((string) $tenant->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function remindAfterDays(): int
    {
        return max(1, (int) config('tenant_lifecycle.dormant.remind_after_days', 60));
    }

    /** At least one day after the reminder threshold, so a misconfiguration can never archive before reminding. */
    private function archiveAfterDays(): int
    {
        return max($this->remindAfterDays() + 1, (int) config('tenant_lifecycle.dormant.archive_after_days', 90));
    }

    private function minReminderGapDays(): int
    {
        return max(0, (int) config('tenant_lifecycle.dormant.min_reminder_gap_days', 7));
    }
}
