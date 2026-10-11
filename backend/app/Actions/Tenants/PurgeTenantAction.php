<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Console\Commands\BackupTenantsCommand;
use App\DTOs\Tenants\PurgeTenantDTO;
use App\Enums\CentralAuditEvent;
use App\Exceptions\TenantLifecycleException;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Models\TenantRateLimitOverride;
use App\Services\CentralAuditLogger;
use App\Services\Tenancy\ProvisionerMySQLDatabaseManager;
use App\Services\TenantProvisionerService;
use App\Support\Tenancy\PurgeEligibility;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Stancl\Tenancy\Contracts\TenantDatabaseManager;
use Stancl\Tenancy\Database\DatabaseManager as TenancyDatabaseManager;
use Throwable;

/**
 * OPS-9 (CTO Q2, Q5): the ONLY code that drops a tenant database. Called by `tenants:purge`
 * only (Tests\Unit\Architecture\NoAutomaticPurgeTest), after the command proved a recent
 * verified backup with a restore drill and got the typed confirmation.
 *
 *  1. lock `tenant-lifecycle:{id}`; central transaction + row lock: already purged → refuse;
 *     first run → eligibility re-checked under the lock, purge_started_at / purged_by set,
 *     audit TenantPurgeStarted. A row with purge_started_at set is a RESUME (no re-check:
 *     the tenant is already unusable and nothing may transition it back);
 *  2. drop the database: MySQL/MariaDB through the provisioner account
 *     (ProvisionerMySQLDatabaseManager, which throws on failure; never the legacy
 *     SafeMySQLDatabaseManager that swallows errors), sqlite through stancl's manager. Refused
 *     when the name is neither the generated prefix+id+suffix nor the provisioner-recorded
 *     created database, is the central database, or is shared with another tenant. Asserted gone;
 *  3. drop the per-tenant MySQL user ONLY if the provisioner created it and it is still the
 *     tenant's username (a shared / operator account is never touched);
 *  4. delete storage/tenant<id> (id pattern + containment checked);
 *  5. central transaction: delete domains, impersonation tokens and rate-limit overrides,
 *     forget the DB credentials, purged_at; the row stays as a tombstone (billing, audit,
 *     lifecycle history, backups ledger kept; slug never reusable). Audit TenantPurged.
 *
 * A failure after step 1 audits TenantPurgeFailed{step, class} and rethrows; running the
 * command again resumes (every step is idempotent).
 *
 * @phpstan-type PurgeResult array{database: string, database_existed: bool, database_dropped: bool, user_dropped: bool, files_deleted: bool, resumed: bool}
 */
final class PurgeTenantAction
{
    public function __construct(
        private readonly CentralAuditLogger $audit,
    ) {}

    /**
     * @return PurgeResult
     */
    public function execute(PurgeTenantDTO $dto): array
    {
        $lock = Cache::lock(ArchiveTenantAction::lockKey($dto->tenantId), max(60, (int) config('tenant_lifecycle.purge.lock_seconds', 3600)));
        if (! $lock->get()) {
            throw new RuntimeException((string) __('console.purge.locked', ['tenant' => $dto->tenantId]));
        }

        $this->leaveTenantContext();

        try {
            [$tenant, $resumed] = $this->start($dto);
            $step = 'database';

            try {
                $database = (string) $tenant->database()->getName();
                [$existed, $dropped] = $this->dropDatabase($tenant, $database);

                $step = 'database_user';
                $userDropped = $this->dropCreatedUser($tenant);

                $step = 'files';
                $filesDeleted = $this->deleteFiles((string) $tenant->getKey());

                $step = 'central';
                $result = [
                    'database' => $database,
                    'database_existed' => $existed,
                    'database_dropped' => $dropped,
                    'user_dropped' => $userDropped,
                    'files_deleted' => $filesDeleted,
                    'resumed' => $resumed,
                ];
                $this->finish($dto, $result);

                return $result;
            } catch (Throwable $e) {
                $this->audit->recordAttempt(
                    CentralAuditEvent::TenantPurgeFailed,
                    ['step' => $step, 'class' => $e::class, 'purged_by' => $dto->purgedBy, 'backup_id' => $dto->backupId],
                    tenantId: $dto->tenantId,
                );

                throw $e;
            }
        } finally {
            $this->leaveTenantContext();
            $lock->release();
        }
    }

    /**
     * @return array{0: Tenant, 1: bool} the locked tenant and whether this is a resume
     */
    private function start(PurgeTenantDTO $dto): array
    {
        return $this->centralConnection()->transaction(function () use ($dto): array {
            $tenant = Tenant::query()->whereKey($dto->tenantId)->lockForUpdate()->first();
            if (! $tenant instanceof Tenant) {
                throw new RuntimeException((string) __('console.purge.tenant_not_found', ['tenant' => $dto->tenantId]));
            }

            if ($tenant->purged_at !== null) {
                throw TenantLifecycleException::purgeRefused(PurgeEligibility::REASON_PURGED);
            }

            if ($tenant->purge_started_at !== null) {
                return [$tenant, true];
            }

            $eligibility = PurgeEligibility::forTenant($tenant, now());
            if (! $eligibility->eligible) {
                $this->audit->recordAttempt(
                    CentralAuditEvent::TenantPurgeRefused,
                    ['reason' => (string) $eligibility->reason, 'stage' => 'locked_recheck', 'purged_by' => $dto->purgedBy],
                    subject: $tenant,
                );

                throw TenantLifecycleException::purgeRefused((string) $eligibility->reason);
            }

            $tenant->forceFill([
                'purge_started_at' => now(),
                'purged_by' => $dto->purgedBy,
            ])->save();

            $this->audit->record(
                CentralAuditEvent::TenantPurgeStarted,
                [
                    'backup_id' => $dto->backupId,
                    'sha256' => $dto->backupSha256,
                    'database' => (string) $tenant->database()->getName(),
                    'purged_by' => $dto->purgedBy,
                ],
                subject: $tenant,
            );

            return [$tenant, false];
        });
    }

    /**
     * @return array{0: bool, 1: bool} existed before, dropped now
     */
    private function dropDatabase(Tenant $tenant, string $database): array
    {
        $this->assertDroppable($tenant, $database);
        $manager = $this->databaseManager($tenant);

        if (! $this->databaseExists($manager, $database)) {
            return [false, false];
        }

        $this->leaveTenantContext();
        // Windows keeps an sqlite file locked while a PDO handle is alive.
        gc_collect_cycles();
        $manager->deleteDatabase($tenant);

        if ($this->databaseExists($manager, $database)) {
            gc_collect_cycles();
            $manager->deleteDatabase($tenant);
        }

        if ($this->databaseExists($manager, $database)) {
            throw new RuntimeException((string) __('console.purge.database_still_exists', ['database' => $database]));
        }

        return [true, true];
    }

    /**
     * Never drop the central database or a database another tenant points at.
     */
    private function assertDroppable(Tenant $tenant, string $database): void
    {
        if ($database === '') {
            throw new RuntimeException((string) __('console.purge.database_unsafe', ['database' => $database]));
        }

        $template = $tenant->database()->getTemplateConnectionName();
        $driver = config("database.connections.{$template}.driver");
        if ($driver === 'mysql' || $driver === 'mariadb') {
            ProvisionerMySQLDatabaseManager::assertDatabaseName($database);
        } elseif (preg_match('/^[A-Za-z0-9_.\-]{1,128}$/', $database) !== 1) {
            throw new RuntimeException((string) __('console.purge.database_unsafe', ['database' => $database]));
        }

        // M1: only a database this platform made for THIS tenant: the generated name (stancl's
        // default generator: prefix + id + suffix, the suffix carrying `.sqlite` for sqlite, the
        // exact name the sqlite manager resolves under database_path()) or the name the
        // provisioner recorded creating. A tenancy_db_name edited to point anywhere else (an
        // operator database, another schema) is never dropped.
        $expected = (string) config('tenancy.database.prefix').$tenant->getTenantKey().(string) config('tenancy.database.suffix');
        $created = $tenant->getInternal(TenantProvisionerService::CREATED_DATABASE_KEY);
        if ($database !== $expected && ! (is_string($created) && $created !== '' && $created === $database)) {
            throw new RuntimeException((string) __('console.purge.database_unexpected_name', [
                'database' => $database,
                'expected' => $expected,
            ]));
        }

        $central = (string) config('database.connections.'.config('tenancy.database.central_connection').'.database');
        if ($central !== '' && ($central === $database || basename(str_replace('\\', '/', $central)) === $database)) {
            throw new RuntimeException((string) __('console.purge.database_unsafe', ['database' => $database]));
        }

        foreach (Tenant::query()->whereKeyNot($tenant->getKey())->cursor() as $other) {
            if ((string) $other->database()->getName() === $database) {
                throw new RuntimeException((string) __('console.purge.database_shared', ['database' => $database]));
            }
        }
    }

    /** The provisioner-created per-tenant user, only while it is still the tenant's username. */
    private function dropCreatedUser(Tenant $tenant): bool
    {
        $record = $tenant->getInternal(TenantProvisionerService::CREATED_USER_KEY);
        $current = $tenant->getInternal('db_username');

        if (! is_array($record) || ! is_string($current) || $current === '') {
            return false;
        }

        $username = $record['username'] ?? null;
        $host = $record['host'] ?? null;
        if (! is_string($username) || $username !== $current || ! is_string($host) || $host === '') {
            return false;
        }

        $manager = $this->databaseManager($tenant);
        if (! $manager instanceof ProvisionerMySQLDatabaseManager) {
            return false;
        }

        $manager->dropUser($username, $host);

        return true;
    }

    /** storage/<suffix_base><id> (stancl FilesystemTenancyBootstrapper), from central context. */
    private function deleteFiles(string $tenantId): bool
    {
        if (preg_match(BackupTenantsCommand::SUBJECT_PATTERN, $tenantId) !== 1) {
            throw new RuntimeException((string) __('console.backups.invalid_subject'));
        }

        $base = storage_path();
        $directory = $base.DIRECTORY_SEPARATOR.config('tenancy.filesystem.suffix_base', 'tenant').$tenantId;

        if (! is_dir($directory)) {
            return false;
        }

        $real = realpath($directory);
        $realBase = realpath($base);
        if ($real === false || $realBase === false || dirname($real) !== $realBase) {
            throw new RuntimeException((string) __('console.purge.storage_unsafe'));
        }

        File::deleteDirectory($real);

        if (is_dir($real)) {
            throw new RuntimeException((string) __('console.purge.storage_not_deleted'));
        }

        return true;
    }

    /**
     * @param  PurgeResult  $result
     */
    private function finish(PurgeTenantDTO $dto, array $result): void
    {
        $connection = $this->centralConnection();

        $connection->transaction(function () use ($dto, $result, $connection): void {
            $tenant = Tenant::query()->whereKey($dto->tenantId)->lockForUpdate()->firstOrFail();
            $id = (string) $tenant->getKey();

            $domains = $tenant->domains()->delete();
            $tokens = $connection->table('tenant_user_impersonation_tokens')->where('tenant_id', $id)->delete();
            $overrides = TenantRateLimitOverride::query()->where('tenant_id', $id)->delete();

            $tenant->offsetUnset('tenancy_db_username');
            $tenant->offsetUnset('tenancy_db_password');
            $tenant->offsetUnset('tenancy_'.TenantProvisionerService::CREATED_USER_KEY);
            $tenant->offsetUnset('tenancy_'.TenantProvisionerService::CREATED_DATABASE_KEY);
            $tenant->forceFill(['purged_at' => now()])->save();

            $this->audit->record(
                CentralAuditEvent::TenantPurged,
                $result + [
                    'backup_id' => $dto->backupId,
                    'sha256' => $dto->backupSha256,
                    'purged_by' => $dto->purgedBy,
                    'domains_deleted' => $domains,
                    'impersonation_tokens_deleted' => $tokens,
                    'rate_limit_overrides_deleted' => $overrides,
                ],
                subject: $tenant,
            );
        });
    }

    /**
     * MySQL/MariaDB: the provisioner account (throws on failure). Other drivers (sqlite in
     * dev/tests): stancl's configured manager.
     */
    private function databaseManager(Tenant $tenant): TenantDatabaseManager
    {
        $template = $tenant->database()->getTemplateConnectionName();
        $driver = config("database.connections.{$template}.driver");

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return ProvisionerMySQLDatabaseManager::forProvisioning();
        }

        return $tenant->database()->manager();
    }

    /**
     * Fresh existence check (DDL and file operations happen in between).
     *
     * @phpstan-impure
     */
    private function databaseExists(TenantDatabaseManager $manager, string $database): bool
    {
        return $manager->databaseExists($database);
    }

    private function leaveTenantContext(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        app(TenancyDatabaseManager::class)->purgeTenantConnection();
    }

    private function centralConnection(): Connection
    {
        return DB::connection((new TenantLifecycleEvent)->getConnectionName());
    }
}
