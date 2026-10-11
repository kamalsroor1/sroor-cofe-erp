<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\PurgeTenantAction;
use App\Actions\Tenants\TakeTenantBackupAction;
use App\Actions\Tenants\VerifyTenantBackupAction;
use App\DTOs\Tenants\PurgeTenantDTO;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantBackupKind;
use App\Models\Tenant;
use App\Models\TenantBackup;
use App\Services\CentralAuditLogger;
use App\Services\Tenancy\ProvisionerMySQLDatabaseManager;
use App\Support\Tenancy\PurgeEligibility;
use Illuminate\Console\Command;
use Stancl\Tenancy\Contracts\TenantDatabaseManager;
use Throwable;

/**
 * OPS-9 (CTO Q2–Q5): irreversibly drop ONE tenant's database and files. CLI only: never
 * scheduled, never queued, never reachable from the API, and there is no --force.
 *
 *   php artisan tenants:purge <tenant id>
 *       [--backup=<ledger id> | --take-backup] [--dry-run] [--db-user=sroor_provisioner]
 *       [--confirm=<slug> --yes-i-understand]   (non-interactive only)
 *
 * Safety rails, in order (any failure = exit 1 + TenantPurgeRefused{reason}, nothing dropped):
 *  1. eligibility (PurgeEligibility): archived ≥ 30 days or suspended/cancelled ≥ 90 days,
 *     never a `violation` suspension;
 *  2. a backup of THIS tenant, verified at upload and at most purge.backup_max_age_hours old
 *     (--backup, else the newest such row), or a fresh `pre_purge` one (--take-backup);
 *  3. VerifyTenantBackupAction: sha256 re-checked on the disk, decrypted, manifest subject,
 *     restore drill into a scratch database (purge.require_restore_drill);
 *  4. summary; --dry-run stops here;
 *  5. typed confirmation: the shop code (slug) exactly, then a yes/no defaulting to no.
 *     Non-interactive runs need --confirm=<slug> AND --yes-i-understand.
 * Then PurgeTenantAction. A run on a tenant whose purge already started RESUMES it (no
 * eligibility re-check; the backup rails still apply while its database exists).
 */
final class PurgeTenantCommand extends Command
{
    protected $signature = 'tenants:purge
        {tenant : Tenant id}
        {--backup= : tenant_backups id to rely on (default: the newest recent verified backup of the tenant)}
        {--take-backup : Take a fresh pre_purge backup now and rely on it}
        {--confirm= : Non-interactive confirmation: the shop code (slug), typed exactly}
        {--yes-i-understand : Non-interactive confirmation that the drop is irreversible}
        {--dry-run : Run every check and print the summary, drop nothing}
        {--db-user= : MySQL account for the restore drill (its password is asked, never passed)}';

    private ?Tenant $tenant = null;

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.purge.description'));
    }

    public function handle(
        TakeTenantBackupAction $takeBackup,
        VerifyTenantBackupAction $verifyBackup,
        PurgeTenantAction $purge,
        CentralAuditLogger $audit,
    ): int {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $id = trim((string) $this->argument('tenant'));
        $tenant = $id === '' ? null : Tenant::query()->find($id);
        if (! $tenant instanceof Tenant) {
            $this->error((string) __('console.purge.tenant_not_found', ['tenant' => $id]));

            return self::FAILURE;
        }
        $this->tenant = $tenant;
        $id = (string) $tenant->getKey();

        if ($tenant->purged_at !== null) {
            $this->error((string) __('console.purge.already_purged', ['tenant' => $id]));

            return self::FAILURE;
        }

        $resume = $tenant->purge_started_at !== null;
        if ($resume) {
            $this->warn((string) __('console.purge.resuming', ['tenant' => $id]));
        } else {
            $eligibility = PurgeEligibility::forTenant($tenant, now());
            if (! $eligibility->eligible) {
                return $this->refuse($audit, (string) $eligibility->reason, [
                    'eligible_at' => $eligibility->eligibleAt?->toIso8601String(),
                ]);
            }
        }

        if ($this->option('take-backup') && $this->option('backup') !== null) {
            return $this->refuse($audit, 'conflicting_options');
        }

        $database = (string) $tenant->database()->getName();
        $databaseExists = $this->databaseExists($tenant, $database);
        $dryRun = (bool) $this->option('dry-run');

        $backup = null;
        if ($databaseExists) {
            if ($this->option('take-backup')) {
                if (! $dryRun) {
                    $this->line((string) __('console.purge.taking_backup', ['tenant' => $id]));
                    try {
                        $backup = $takeBackup->execute($tenant, TenantBackupKind::PrePurge);
                    } catch (Throwable $e) {
                        report($e);
                        $this->error($e->getMessage());

                        return $this->refuse($audit, 'backup_failed');
                    }
                }
            } else {
                [$backup, $reason] = $this->recentBackup($id);
                if ($backup === null) {
                    return $this->refuse($audit, (string) $reason);
                }
            }

            if ($backup !== null) {
                $this->line((string) __('console.purge.verifying_backup', ['backup' => $backup->getKey()]));
                try {
                    $verifyBackup->execute(
                        $backup,
                        $id,
                        (bool) config('tenant_lifecycle.purge.require_restore_drill', true),
                        is_string($this->option('db-user')) ? $this->option('db-user') : null,
                        fn (string $command, array $arguments): int => $this->call($command, $arguments),
                    );
                } catch (Throwable $e) {
                    $this->error($e->getMessage());

                    return $this->refuse($audit, 'backup_verification_failed', ['backup_id' => (int) $backup->getKey()]);
                }
                $this->info((string) __('console.purge.backup_verified', ['backup' => $backup->getKey()]));
            }
        } else {
            $this->warn((string) __('console.purge.database_missing', ['database' => $database]));
        }

        $this->summary($tenant, $database, $databaseExists, $backup, $resume);

        if ($dryRun) {
            $this->info((string) __('console.purge.dry_run_done'));

            return self::SUCCESS;
        }

        $confirmation = $this->confirmation((string) $tenant->slug);
        if ($confirmation !== null) {
            return $this->refuse($audit, $confirmation);
        }

        try {
            $result = $purge->execute(new PurgeTenantDTO(
                $id,
                PurgeTenantDTO::cliActor(),
                $backup !== null ? (int) $backup->getKey() : null,
                $backup?->sha256,
            ));
        } catch (Throwable $e) {
            report($e);
            $this->error((string) __('console.purge.failed', ['tenant' => $id, 'reason' => $e->getMessage()]));

            return self::FAILURE;
        }

        $this->info((string) __('console.purge.done', [
            'tenant' => $id,
            'database' => $result['database'],
        ]));

        return self::SUCCESS;
    }

    /**
     * --backup=<id>, else the newest verified backup of the tenant (any kind) inside the age
     * window. Returns the row, or null and the refusal reason.
     *
     * @return array{0: TenantBackup|null, 1: string|null}
     */
    private function recentBackup(string $tenantId): array
    {
        if (! BackupTenantsCommand::ledgerAvailable()) {
            return [null, 'backup_missing'];
        }

        $hours = max(1, (int) config('tenant_lifecycle.purge.backup_max_age_hours', 24));
        $since = now()->subHours($hours);
        $requested = $this->option('backup');

        if (is_string($requested) && $requested !== '') {
            $row = ctype_digit($requested) ? TenantBackup::query()->find((int) $requested) : null;

            return match (true) {
                ! $row instanceof TenantBackup => [null, 'backup_missing'],
                $row->tenant_id !== $tenantId => [null, 'backup_wrong_tenant'],
                $row->verified_at === null => [null, 'backup_unverified'],
                $row->created_at === null || $row->created_at->lt($since) => [null, 'backup_too_old'],
                default => [$row, null],
            };
        }

        $row = TenantBackup::query()
            ->forSubject($tenantId)
            ->verified()
            ->latest('created_at')
            ->latest('id')
            ->first();

        return match (true) {
            ! $row instanceof TenantBackup => [null, 'backup_missing'],
            $row->created_at === null || $row->created_at->lt($since) => [null, 'backup_too_old'],
            default => [$row, null],
        };
    }

    /** null = confirmed; otherwise the refusal reason. */
    private function confirmation(string $slug): ?string
    {
        $typed = $this->option('confirm');

        if (! $this->input->isInteractive()) {
            if (! is_string($typed) || $typed === '' || ! $this->option('yes-i-understand')) {
                return 'confirmation_missing';
            }

            return $typed === $slug ? null : 'confirmation_mismatch';
        }

        if (! is_string($typed) || $typed === '') {
            $typed = (string) $this->ask((string) __('console.purge.type_slug', ['slug' => $slug]));
        }

        if ($typed !== $slug) {
            return 'confirmation_mismatch';
        }

        if ($this->option('yes-i-understand')) {
            return null;
        }

        return $this->confirm((string) __('console.purge.confirm_drop'), false) ? null : 'not_confirmed';
    }

    private function summary(Tenant $tenant, string $database, bool $databaseExists, ?TenantBackup $backup, bool $resume): void
    {
        $yes = (string) __('console.purge.yes');
        $no = (string) __('console.purge.no');

        $this->table([__('console.purge.col_item'), __('console.purge.col_value')], [
            [__('console.purge.row_tenant'), (string) $tenant->getKey().' / '.$tenant->slug.' / '.$tenant->name],
            [__('console.purge.row_status'), (string) $tenant->status],
            [__('console.purge.row_archived_at'), $tenant->archived_at?->toIso8601String() ?? '-'],
            [__('console.purge.row_database'), $database.' ('.($databaseExists ? $yes : $no).')'],
            [__('console.purge.row_domains'), (string) $tenant->domains()->count()],
            [__('console.purge.row_backup'), $backup === null
                ? ($this->option('take-backup') && $databaseExists ? (string) __('console.purge.backup_will_be_taken') : '-')
                : $backup->getKey().' / '.$backup->kind->value.' / '.$backup->disk.' / '.$backup->created_at?->toIso8601String().' / '.substr($backup->sha256, 0, 12)],
            [__('console.purge.row_resume'), $resume ? $yes : $no],
        ]);

        $this->warn((string) __('console.purge.irreversible'));
    }

    /**
     * @param  array<string, scalar|null>  $extra
     */
    private function refuse(CentralAuditLogger $audit, string $reason, array $extra = []): int
    {
        try {
            $audit->recordAttempt(
                CentralAuditEvent::TenantPurgeRefused,
                ['reason' => $reason, 'purged_by' => PurgeTenantDTO::cliActor()] + $extra,
                subject: $this->tenant,
            );
        } catch (Throwable $e) {
            report($e);
        }

        $this->error((string) __('console.purge.refused', ['reason' => __('console.purge.reasons.'.$reason)]));

        return self::FAILURE;
    }

    private function databaseExists(Tenant $tenant, string $database): bool
    {
        $template = $tenant->database()->getTemplateConnectionName();
        $driver = config("database.connections.{$template}.driver");

        /** @var TenantDatabaseManager $manager */
        $manager = $driver === 'mysql' || $driver === 'mariadb'
            ? ProvisionerMySQLDatabaseManager::forProvisioning()
            : $tenant->database()->manager();

        return $manager->databaseExists($database);
    }
}
