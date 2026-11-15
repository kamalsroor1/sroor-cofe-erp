<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\System\GetSetupProgressAction;
use App\Actions\Tenants\AdvanceDocumentSequencesAction;
use App\Actions\Tenants\RevokeTenantTokensAction;
use App\Actions\Tenants\UpdateTenantDatabaseConfigAction;
use App\Enums\TenantStatus;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\InventoryAnalyticsService;
use App\Services\ProfitLossService;
use App\Services\Tenancy\ProvisionerMySQLDatabaseManager;
use App\Services\TenantProvisionerService;
use App\Support\DashboardCache;
use App\Support\TenantCache;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * BKP-02: point a shop at a database restored by `backup:restore-tenant --keep-restored`, from the
 * CLI, without the operator console (which needs 2FA and may be the thing that is down) and
 * without any manual SQL.
 *
 *   php artisan backup:swap-tenant <tenant id> --database=tenant_zz_restore_shop1_20261112010101
 *       [--number-margin=1000] [--live] [--force]
 *
 * In order (the previous database is never touched or dropped):
 *   1. checks: tenant is provisioned / not purged, the new database exists and is not the current
 *      one, and the shop is SUSPENDED (no sales are taken during the swap) unless --live says the
 *      operator accepts swapping a trading shop (suspended -> active is a Billing-only move, so a
 *      paying shop cannot always be suspended for a few seconds); summary, then a yes/no (default
 *      no) unless --force;
 *   2. GRANT of the per-tenant MySQL user on the new database (only when
 *      TENANT_DB_PER_TENANT_USER=true and this tenant has a provisioner-created user);
 *   3. the RESTORED database is made ready while the shop still runs on its current one, through an
 *      unsaved copy of the tenant that points at it: tenant migrations (a backup older than the
 *      code has an older schema), EVERY API token deleted (tokens inside the backup, and users
 *      disabled after it, must not come back to life), document counters pushed forward by
 *      --number-margin (AdvanceDocumentSequencesAction, so numbers printed after the backup are
 *      not handed out again). If any of these fails the shop is NOT switched and exits 1;
 *   4. only then the tenant's `tenancy_db_name` is changed (UpdateTenantDatabaseConfigAction,
 *      audited): there is no moment where the shop runs on an unmigrated database, with the
 *      backup's tokens or with old counters;
 *   5. this tenant's cache entries are forgotten (never Cache::flush(): the store is shared);
 *      a report is printed.
 * A failure after step 4 (none is expected: the cache step handles its own) points the tenant back
 * at the previous database and exits 1.
 */
final class SwapTenantDatabaseCommand extends Command
{
    public const DEFAULT_MARGIN = 1000;

    protected $signature = 'backup:swap-tenant
        {tenant : Tenant id}
        {--database= : Name of the restored database to switch the shop to}
        {--number-margin=1000 : Added to every document counter (must exceed the documents issued since the backup)}
        {--live : Swap a shop that is not suspended (it may take sales on the old database until the switch)}
        {--force : Do not ask for confirmation}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.swap.description'));
    }

    public function handle(
        UpdateTenantDatabaseConfigAction $updateDatabase,
        RevokeTenantTokensAction $revokeTokens,
    ): int {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $tenant = Tenant::query()->find((string) $this->argument('tenant'));
        $database = trim((string) $this->option('database'));
        $margin = (int) $this->option('number-margin');

        if (! $tenant instanceof Tenant) {
            return $this->refuse((string) __('console.swap.tenant_not_found', ['tenant' => (string) $this->argument('tenant')]));
        }
        if ($database === '' || preg_match('/^[A-Za-z0-9_]{1,64}$/', $database) !== 1) {
            return $this->refuse((string) __('console.swap.invalid_database'));
        }
        if ($margin < 1) {
            return $this->refuse((string) __('console.swap.invalid_margin'));
        }
        if ($tenant->isPurged() || ! $tenant->isProvisioned()) {
            return $this->refuse((string) __('console.swap.tenant_not_ready'));
        }

        if ($tenant->status !== TenantStatus::Suspended->value) {
            if (! $this->option('live')) {
                return $this->refuse((string) __('console.swap.not_suspended', ['status' => (string) $tenant->status]));
            }
            $this->warn((string) __('console.swap.live_warning', ['status' => (string) $tenant->status]));
        }

        $previous = (string) $tenant->database()->getName();
        if ($previous === $database) {
            return $this->refuse((string) __('console.swap.same_database'));
        }

        $manager = $this->manager();
        if ($manager !== null && ! $manager->databaseExists($database)) {
            return $this->refuse((string) __('console.swap.database_missing', ['database' => $database]));
        }

        $this->line((string) __('console.swap.summary', [
            'tenant' => (string) $tenant->getTenantKey(),
            'from' => $previous,
            'to' => $database,
            'margin' => $margin,
        ]));
        if (! $this->option('force') && ! $this->confirm((string) __('console.swap.confirm'), false)) {
            $this->warn((string) __('console.swap.aborted'));

            return self::FAILURE;
        }

        // Step 3 runs against the restored database through an UNSAVED copy of the tenant: the
        // stored tenant keeps pointing at $previous until everything below succeeded.
        $restored = clone $tenant;
        $restored->setInternal('db_name', $database);

        try {
            $this->grant($tenant, $manager, $database);

            if (! $this->migrate($restored)) {
                return $this->refuse((string) __('console.swap.migrate_failed', ['restored' => $database, 'database' => $previous]));
            }

            // Initializes (and ends) tenancy on the restored copy itself.
            $tokens = $revokeTokens->execute($restored);
            $this->info((string) __('console.swap.tokens_revoked', ['count' => $tokens]));

            /** @var array{bumped: int, created: int} $sequences */
            $sequences = $this->onRestored($restored, static fn (): array => app(AdvanceDocumentSequencesAction::class)->execute($margin));
            $this->info((string) __('console.swap.sequences', ['bumped' => $sequences['bumped'], 'created' => $sequences['created'], 'margin' => $margin]));
        } catch (Throwable $e) {
            Log::error('backup:swap-tenant failed before the switch', ['tenant' => (string) $tenant->getTenantKey(), 'exception' => $e::class, 'message' => $e->getMessage()]);

            return $this->refuse((string) __('console.swap.prepare_failed', ['reason' => $e::class, 'database' => $previous]));
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }

        $repointed = false;
        try {
            $updateDatabase->execute($tenant, ['tenancy_db_name' => $database]);
            $repointed = true;
            $this->info((string) __('console.swap.pointed', ['database' => $database]));
            $tenant = $tenant->fresh() ?? $tenant;
        } catch (Throwable $e) {
            Log::error('backup:swap-tenant failed', ['tenant' => (string) $tenant->getTenantKey(), 'exception' => $e::class, 'message' => $e->getMessage()]);

            if (tenancy()->initialized) {
                tenancy()->end();
            }

            // Keep 79959ea1: once repointed, anything that still fails sends the shop back.
            return $repointed
                ? $this->repointBack($updateDatabase, $tenant, $previous, $database, $e)
                : $this->refuse((string) __('console.swap.failed', ['reason' => $e::class]));
        }

        $this->forgetTenantCache($tenant);

        $this->info((string) __('console.swap.done', ['tenant' => (string) $tenant->getTenantKey(), 'database' => $database, 'previous' => $previous]));

        return self::SUCCESS;
    }

    /** MySQL/MariaDB only: the provisioner connection that can GRANT and look up schemas. */
    private function manager(): ?ProvisionerMySQLDatabaseManager
    {
        $driver = (string) config('database.connections.'.config('tenancy.provisioning.connection', 'provisioner').'.driver', '');

        return in_array($driver, ['mysql', 'mariadb'], true) ? ProvisionerMySQLDatabaseManager::forProvisioning() : null;
    }

    private function grant(Tenant $tenant, ?ProvisionerMySQLDatabaseManager $manager, string $database): void
    {
        if ($manager === null || ! config('tenancy.provisioning.per_tenant_db_user')) {
            return;
        }

        $record = $tenant->getInternal(TenantProvisionerService::CREATED_USER_KEY);
        $username = is_array($record) ? ($record['username'] ?? null) : null;
        $host = is_array($record) ? ($record['host'] ?? null) : null;
        if (! is_string($username) || ! is_string($host) || ! $manager->userExists($username, $host)) {
            $this->warn((string) __('console.swap.grant_skipped'));

            return;
        }

        $manager->grant($database, $username, $host);
        $this->info((string) __('console.swap.granted', ['database' => $database]));
    }

    /**
     * The tenant migrations on the restored database. Not `tenants:migrate`: it reloads the stored
     * tenant, which still points at the previous database. Same parameters
     * (tenancy.migration_parameters) on the tenant connection the restored copy initializes.
     */
    private function migrate(Tenant $restored): bool
    {
        try {
            $exit = $this->onRestored($restored, static fn (): int => Artisan::call('migrate', (array) config('tenancy.migration_parameters')));
        } catch (Throwable $e) {
            Log::error('backup:swap-tenant migration failed', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return false;
        }

        if ($exit !== 0) {
            Log::error('backup:swap-tenant migration failed', ['exit' => $exit, 'output' => Artisan::output()]);

            return false;
        }
        $this->info((string) __('console.swap.migrated'));

        return true;
    }

    /**
     * Run $work with tenancy initialized on the restored copy, and always end it afterwards
     * (Tenant::run() leaves the context behind when the callback throws).
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $work
     * @return TResult
     */
    private function onRestored(Tenant $restored, Closure $work): mixed
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        try {
            tenancy()->initialize($restored);

            return $work();
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    private function repointBack(UpdateTenantDatabaseConfigAction $updateDatabase, Tenant $tenant, string $previous, string $restored, Throwable $cause): int
    {
        try {
            $updateDatabase->execute($tenant->fresh() ?? $tenant, ['tenancy_db_name' => $previous]);
        } catch (Throwable $e) {
            Log::critical('backup:swap-tenant could not point the tenant back at its previous database', [
                'tenant' => (string) $tenant->getTenantKey(),
                'previous' => $previous,
                'restored' => $restored,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->refuse((string) __('console.swap.rollback_failed', ['reason' => $cause::class, 'database' => $previous, 'restored' => $restored]));
        }

        return $this->refuse((string) __('console.swap.rolled_back', ['reason' => $cause::class, 'database' => $previous]));
    }

    /**
     * Forget only THIS tenant's cached data. The cache store is shared by every tenant and by central
     * state (rate limiters, locks, 2FA challenges) because CacheTenancyBootstrapper is disabled, so
     * Cache::flush() here would wipe them all (see App\Support\TenantCache).
     */
    private function forgetTenantCache(Tenant $tenant): void
    {
        $tenantId = (string) $tenant->getTenantKey();

        try {
            Cache::forget(Setting::cacheKeyFor($tenantId));
            Cache::forget(GetSetupProgressAction::cacheKeyFor($tenantId));

            foreach ([DashboardCache::NAMESPACE, ProfitLossService::CACHE_NAMESPACE, InventoryAnalyticsService::CACHE_NAMESPACE] as $namespace) {
                TenantCache::bumpFor($tenantId, $namespace);
            }

            // spatie's permission cache key is scoped to the tenant once tenancy is bootstrapped.
            $tenant->run(static fn (): bool => app(PermissionRegistrar::class)->forgetCachedPermissions());
            $this->info((string) __('console.swap.cache_cleared'));
        } catch (Throwable $e) {
            Log::warning('backup:swap-tenant could not clear the tenant cache', ['tenant' => $tenantId, 'exception' => $e::class]);
            $this->warn((string) __('console.swap.cache_failed'));
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
