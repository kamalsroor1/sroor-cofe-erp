<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CentralAuditEvent;
use App\Enums\TenantProvisioningStatus;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use App\Services\Tenancy\ProvisionerMySQLDatabaseManager;
use App\Services\TenantProvisionerService;
use App\Support\Tenancy\ProvisioningErrorCode;
use App\Support\Tenancy\ProvisioningFailure;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SensitiveParameter;
use Stancl\Tenancy\Contracts\TenantDatabaseManager;
use Stancl\Tenancy\Database\DatabaseManager as TenancyDatabaseManager;
use Throwable;

/**
 * OPS-2: builds a registered tenant's workspace (TenantProvisionerService::provision()
 * dispatches it after the central commit).
 *
 *  1. claim: central transaction + row lock; a `ready` or `failed` tenant is left alone
 *     (idempotent; a failed one only runs again through RetryTenantProvisioningAction);
 *  2. database: created with the provisioner account. An EXISTING database is reused only
 *     if this job created it in an earlier attempt UNDER THE SAME NAME (the created name is
 *     stored, not a flag: a database renamed in between is foreign), or adopted if the
 *     operator named it explicitly AND it is empty; anything else is `database_exists`
 *     (permanent, never touched). Optional per-tenant MySQL user
 *     (tenancy.provisioning.per_tenant_db_user), ours only while it is still the tenant's
 *     current username (a username switched to a shared account is never dropped);
 *  3. tenants:migrate; 4. seed in one tenant transaction (TenantProvisionerService);
 *  5. ready + audit `tenant_provisioning_succeeded`.
 *  Always: tenancy ended and the tenant/provisioner connections purged.
 *
 * failed() (final attempt, timeout or permanent error): status `failed` + error code,
 * drops ONLY what this job created and the tenant still points at (database, per-tenant
 * user), audit `tenant_provisioning_failed`, and force-releases the WithoutOverlapping lock
 * (a worker killed on timeout never runs the middleware's `finally`, and the lock would
 * silently drop the retried job until it expires). The central row stays (CTO Q1) for a
 * super-admin retry.
 *
 * Payload: the tenant id only (ShouldBeEncrypted all the same). The first admin's password
 * hash is read from the tenant row (TenantProvisionerService::storedPasswordHash(),
 * encrypted at rest), never carried in the queue payload.
 */
final class ProvisionTenantJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $tenantId,
    ) {
        $connection = config('tenancy.provisioning.queue_connection');
        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }

        $this->onQueue((string) config('tenancy.provisioning.queue', 'default'));
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function uniqueId(): string
    {
        return $this->tenantId;
    }

    /**
     * The unique lock outlives every attempt + backoff (3 × 900 + 30 + 120); the same window
     * makes an idle pending/running tenant "stale" (retryable). config unique_for.
     */
    public function uniqueFor(): int
    {
        return TenantProvisionerService::provisioningWindowSeconds();
    }

    /**
     * Release the unique and the overlap lock of a tenant's provisioning. Only for a tenant
     * no job can still be working on (failed, or stale: idle longer than the unique window).
     */
    public static function releaseLocks(string $tenantId): void
    {
        $job = new self($tenantId);

        (new UniqueLock(app(CacheRepository::class)))->release($job);
        $job->releaseOverlapLock();
    }

    /**
     * A redelivery of a still-running attempt (queue `retry_after` shorter than the
     * timeout) is dropped instead of migrating the same database twice in parallel.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [$this->overlapMiddleware()];
    }

    public function handle(TenantProvisionerService $provisioner, CentralAuditLogger $audit): void
    {
        $tenant = $this->claim();
        if ($tenant === null) {
            return;
        }

        try {
            $passwordHash = TenantProvisionerService::storedPasswordHash($tenant)
                ?? throw new ProvisioningFailure(ProvisioningErrorCode::SeedFailed, ProvisioningFailure::fixedMessage(ProvisioningErrorCode::SeedFailed).' (first-admin seed missing)');

            $this->prepareDatabase($tenant);
            $this->migrate($tenant);
            $this->seed($tenant, $provisioner, $passwordHash);
            $this->markReady($audit);
        } catch (ProvisioningFailure $failure) {
            $this->recordAttemptError($failure->errorCode);

            if (! $failure->errorCode->isPermanent()) {
                throw $failure;
            }

            Log::warning('Tenant provisioning stopped (permanent error)', [
                'tenant_id' => $this->tenantId,
                'error_code' => $failure->errorCode->value,
            ]);
            if ($this->job !== null) {
                $this->fail($failure);
            } else {
                $this->failed($failure);
            }
        } finally {
            $this->leaveTenantContext();
        }
    }

    public function failed(?Throwable $exception): void
    {
        try {
            $this->markFailed($exception);
        } finally {
            $this->releaseOverlapLock();
        }
    }

    private function markFailed(?Throwable $exception): void
    {
        $this->leaveTenantContext();

        $code = match (true) {
            $exception instanceof ProvisioningFailure => $exception->errorCode,
            $exception instanceof TimeoutExceededException, $exception instanceof MaxAttemptsExceededException => ProvisioningErrorCode::Timeout,
            default => ProvisioningErrorCode::Unexpected,
        };

        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant instanceof Tenant || $tenant->provisioningStatus()->isReady()) {
            return;
        }

        $cleanup = $this->dropCreatedArtifacts($tenant);

        $tenant = DB::connection(TenantProvisionerService::centralConnection())->transaction(function () use ($code, $cleanup): ?Tenant {
            $locked = $this->lockedTenant();
            if ($locked === null || $locked->provisioningStatus()->isReady()) {
                return null;
            }

            $locked->forceFill([
                'provisioning_status' => TenantProvisioningStatus::Failed,
                'provisioning_error_code' => $code->value,
            ]);

            $this->forgetDroppedArtifacts($locked, $cleanup);

            $locked->save();

            return $locked;
        });

        if ($tenant === null) {
            return;
        }

        app(CentralAuditLogger::class)->recordAttempt(
            CentralAuditEvent::TenantProvisioningFailed,
            [
                'error_code' => $code->value,
                'attempts' => (int) $tenant->provisioning_attempts,
                'database_dropped' => $cleanup['clean'],
            ],
            subject: $tenant,
        );
    }

    /** Lock the row and move it to `running`; null when there is nothing to do. */
    private function claim(): ?Tenant
    {
        return DB::connection(TenantProvisionerService::centralConnection())->transaction(function (): ?Tenant {
            $tenant = $this->lockedTenant();
            if ($tenant === null) {
                return null;
            }

            $status = $tenant->provisioningStatus();
            if ($status === TenantProvisioningStatus::Ready || $status === TenantProvisioningStatus::Failed) {
                return null;
            }

            $tenant->forceFill([
                'provisioning_status' => TenantProvisioningStatus::Running,
                'provisioning_attempts' => (int) $tenant->provisioning_attempts + 1,
                'provisioning_started_at' => $tenant->provisioning_started_at ?? now(),
                'provisioning_error_code' => null,
            ])->save();

            return $tenant;
        });
    }

    private function prepareDatabase(Tenant $tenant): void
    {
        $name = (string) $tenant->database()->getName();

        try {
            $manager = $this->databaseManager($tenant);
            if ($manager instanceof ProvisionerMySQLDatabaseManager) {
                ProvisionerMySQLDatabaseManager::assertDatabaseName($name);
            }
        } catch (InvalidArgumentException $e) {
            throw ProvisioningFailure::because(ProvisioningErrorCode::InvalidDatabaseName, $e);
        }

        try {
            $exists = $this->databaseExists($manager, $name);
        } catch (Throwable $e) {
            throw ProvisioningFailure::because(ProvisioningErrorCode::DatabaseCreateFailed, $e);
        }

        if ($exists) {
            $ours = $this->createdDatabaseIsCurrent($tenant, $name);
            $adoptable = ! $ours
                && $tenant->getInternal(TenantProvisionerService::EXPLICIT_DATABASE_KEY) === true
                && $this->databaseIsEmpty($tenant, $manager, $name);

            if (! $ours && ! $adoptable) {
                throw new ProvisioningFailure(ProvisioningErrorCode::DatabaseExists);
            }
        } else {
            try {
                $manager->createDatabase($tenant);
                $created = $this->databaseExists($manager, $name);
            } catch (Throwable $e) {
                throw ProvisioningFailure::because(ProvisioningErrorCode::DatabaseCreateFailed, $e);
            }

            if (! $created) {
                throw new ProvisioningFailure(ProvisioningErrorCode::DatabaseCreateFailed);
            }

            // Only now: failed() may drop it (while the tenant still points at this name).
            // Never set for a database that pre-existed.
            $this->remember($tenant, [TenantProvisionerService::CREATED_DATABASE_KEY => $name]);
        }

        if ($manager instanceof ProvisionerMySQLDatabaseManager) {
            $this->ensureTenantDatabaseUser($tenant, $manager, $name);
        }
    }

    /**
     * One MySQL user per tenant, granted on its own (escaped) database only. Skipped when
     * the flag is off or the tenant uses other credentials: operator-supplied, or a username
     * switched away from the one this job created (that account is never touched).
     */
    private function ensureTenantDatabaseUser(Tenant $tenant, ProvisionerMySQLDatabaseManager $manager, string $database): void
    {
        if (! config('tenancy.provisioning.per_tenant_db_user')) {
            return;
        }

        $created = $this->createdUserIfCurrent($tenant);
        $username = $tenant->getInternal('db_username');
        if ($created === null && is_string($username) && $username !== '') {
            return; // not ours: explicit operator credentials (or a shared account)
        }

        try {
            if ($created !== null && $manager->userExists($created['username'], $created['host'])) {
                $manager->grant($database, $created['username'], $created['host']);

                return;
            }

            $host = $created['host'] ?? (string) config('tenancy.provisioning.db_user_host', 'localhost');
            $username = $created['username'] ?? 'tu_'.Str::lower(Str::random(20));
            $password = Str::password(40, symbols: false);

            // Remembered BEFORE the CREATE USER, so failed() can always DROP USER IF EXISTS.
            $this->remember($tenant, [
                'db_username' => $username,
                'db_password' => Tenant::sealDatabasePassword($password),
                TenantProvisionerService::CREATED_USER_KEY => ['username' => $username, 'host' => $host],
            ]);

            $manager->createUser($database, $username, $password, $host);
        } catch (Throwable $e) {
            // Never chained: the QueryException of CREATE USER carries the password in its SQL.
            throw ProvisioningFailure::because(ProvisioningErrorCode::DatabaseUserFailed, $e, chain: false);
        }
    }

    private function migrate(Tenant $tenant): void
    {
        try {
            $exitCode = Artisan::call('tenants:migrate', [
                '--tenants' => [(string) $tenant->getTenantKey()],
                '--force' => true,
            ]);
        } catch (Throwable $e) {
            throw ProvisioningFailure::because(ProvisioningErrorCode::MigrationFailed, $e);
        } finally {
            $this->leaveTenantContext();
        }

        if ($exitCode !== 0) {
            throw new ProvisioningFailure(ProvisioningErrorCode::MigrationFailed, 'tenants:migrate exited with '.$exitCode);
        }
    }

    private function seed(Tenant $tenant, TenantProvisionerService $provisioner, #[SensitiveParameter] string $passwordHash): void
    {
        try {
            $provisioner->seedTenantDatabase($tenant, $passwordHash);
        } catch (Throwable $e) {
            // Never chained: the cause may carry the first admin's data (SQL bindings, hash).
            throw ProvisioningFailure::because(ProvisioningErrorCode::SeedFailed, $e, chain: false);
        }
    }

    private function markReady(CentralAuditLogger $audit): void
    {
        DB::connection(TenantProvisionerService::centralConnection())->transaction(function () use ($audit): void {
            $tenant = $this->lockedTenant();
            if ($tenant === null) {
                return;
            }

            $tenant->forceFill([
                'provisioning_status' => TenantProvisioningStatus::Ready,
                'provisioning_error_code' => null,
                'provisioned_at' => now(),
            ]);
            // The first-admin seed (password hash) is not needed any more.
            $tenant->offsetUnset('tenancy_'.TenantProvisionerService::SEED_KEY);
            $tenant->save();

            $audit->record(
                CentralAuditEvent::TenantProvisioningSucceeded,
                [
                    'attempts' => (int) $tenant->provisioning_attempts,
                    'database_created' => $this->createdDatabaseIsCurrent($tenant, (string) $tenant->database()->getName()),
                    'per_tenant_db_user' => $this->createdUserIfCurrent($tenant) !== null,
                ],
                subject: $tenant,
            );
        });
    }

    /** Keep the last error code of a failed (retried) attempt visible to operators. */
    private function recordAttemptError(ProvisioningErrorCode $code): void
    {
        try {
            Tenant::query()->whereKey($this->tenantId)
                ->where('provisioning_status', TenantProvisioningStatus::Running->value)
                ->update(['provisioning_error_code' => $code->value]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Drop the database / per-tenant user ONLY if this job created them AND the tenant still
     * points at them (same database name, same username). Something we created under an
     * older name / username is left alone and logged (an operator decides), never dropped.
     *
     * `clean` = nothing created by this job is left behind.
     *
     * @return array{database_dropped: bool, user_dropped: bool, clean: bool}
     */
    private function dropCreatedArtifacts(Tenant $tenant): array
    {
        $result = ['database_dropped' => false, 'user_dropped' => false, 'clean' => true];

        $name = (string) $tenant->database()->getName();
        $createdName = $tenant->getInternal(TenantProvisionerService::CREATED_DATABASE_KEY);
        $createdDatabase = $this->createdDatabaseIsCurrent($tenant, $name);
        $userRecord = $tenant->getInternal(TenantProvisionerService::CREATED_USER_KEY);
        $createdUser = $this->createdUserIfCurrent($tenant);

        if (($createdName !== null && ! $createdDatabase) || ($userRecord !== null && $createdUser === null)) {
            // Identifiers only, never a credential.
            Log::warning('Tenant provisioning: an artifact it created is no longer the tenant\'s; left in place', [
                'tenant_id' => $this->tenantId,
                'created_database' => is_string($createdName) ? $createdName : null,
                'created_user' => is_array($userRecord) && is_string($userRecord['username'] ?? null) ? $userRecord['username'] : null,
            ]);
            $result['clean'] = false;
        }

        if (! $createdDatabase && $createdUser === null) {
            return $result;
        }

        try {
            $manager = $this->databaseManager($tenant);

            if ($createdDatabase) {
                if ($this->databaseExists($manager, $name)) {
                    // Windows keeps an sqlite file locked while a PDO handle is alive.
                    gc_collect_cycles();
                    $manager->deleteDatabase($tenant);
                }

                if ($this->databaseExists($manager, $name)) {
                    Log::error('Tenant provisioning: could not drop the database it created', ['tenant_id' => $this->tenantId]);

                    return ['database_dropped' => false, 'user_dropped' => false, 'clean' => false];
                }

                $result['database_dropped'] = true;
            }

            if ($createdUser !== null && $manager instanceof ProvisionerMySQLDatabaseManager) {
                $manager->dropUser($createdUser['username'], $createdUser['host']);
                $result['user_dropped'] = true;
            }
        } catch (Throwable $e) {
            report($e);
            $result['clean'] = false;
        }

        return $result;
    }

    /**
     * Forget, on the locked central row, what dropCreatedArtifacts() actually dropped.
     *
     * @param  array{database_dropped: bool, user_dropped: bool, clean: bool}  $cleanup
     */
    private function forgetDroppedArtifacts(Tenant $tenant, array $cleanup): void
    {
        if ($cleanup['user_dropped']) {
            $tenant->offsetUnset('tenancy_db_username');
            $tenant->offsetUnset('tenancy_db_password');
            $tenant->offsetUnset('tenancy_'.TenantProvisionerService::CREATED_USER_KEY);
        }

        if ($cleanup['database_dropped']) {
            $tenant->offsetUnset('tenancy_'.TenantProvisionerService::CREATED_DATABASE_KEY);
        }
    }

    /** The stored created-database NAME equals the tenant's current database name. */
    private function createdDatabaseIsCurrent(Tenant $tenant, string $name): bool
    {
        $created = $tenant->getInternal(TenantProvisionerService::CREATED_DATABASE_KEY);

        return is_string($created) && $created !== '' && $created === $name;
    }

    /**
     * The per-tenant user this job created, while it is STILL the tenant's username.
     *
     * @return array{username: string, host: string}|null
     */
    private function createdUserIfCurrent(Tenant $tenant): ?array
    {
        $record = $tenant->getInternal(TenantProvisionerService::CREATED_USER_KEY);
        $current = $tenant->getInternal('db_username');

        if (! is_array($record) || ! is_string($current) || $current === '') {
            return null;
        }

        $username = $record['username'] ?? null;
        $host = $record['host'] ?? null;

        if (! is_string($username) || $username !== $current || ! is_string($host) || $host === '') {
            return null;
        }

        return ['username' => $username, 'host' => $host];
    }

    private function databaseIsEmpty(Tenant $tenant, TenantDatabaseManager $manager, string $name): bool
    {
        if ($manager instanceof ProvisionerMySQLDatabaseManager) {
            return $manager->databaseIsEmpty($name);
        }

        try {
            return $tenant->run(static fn (): bool => Schema::getTables() === []);
        } finally {
            $this->leaveTenantContext();
        }
    }

    /**
     * MySQL/MariaDB: the provisioner account. Other drivers (sqlite in dev/tests): stancl's
     * configured manager, which needs no privileged account.
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
     * Persist internal (`tenancy_`-prefixed) keys on the central row, under its lock.
     *
     * @param  array<string, mixed>  $values
     */
    private function remember(Tenant $tenant, #[SensitiveParameter] array $values): void
    {
        DB::connection(TenantProvisionerService::centralConnection())->transaction(function () use ($tenant, $values): void {
            $locked = $this->lockedTenant() ?? throw new ProvisioningFailure(ProvisioningErrorCode::Unexpected, 'Tenant row disappeared during provisioning.');

            foreach ($values as $key => $value) {
                $locked->setInternal($key, $value);
                $tenant->setInternal($key, $value);
            }

            $locked->save();
        });
    }

    /**
     * Fresh existence check (DDL and file operations happen in between).
     *
     * @phpstan-impure
     */
    private function databaseExists(TenantDatabaseManager $manager, string $name): bool
    {
        return $manager->databaseExists($name);
    }

    private function overlapMiddleware(): WithoutOverlapping
    {
        return (new WithoutOverlapping($this->tenantId))->dontRelease()->expireAfter($this->timeout + 60);
    }

    /**
     * Force-release the WithoutOverlapping lock under the very key the middleware uses
     * (WithoutOverlapping::getLockKey(): `laravel-queue-overlap:` + job class + `:` + tenant
     * id, on the default cache store). Never throws: failed() must finish.
     */
    private function releaseOverlapLock(): void
    {
        try {
            Cache::lock($this->overlapMiddleware()->getLockKey($this))->forceRelease();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function lockedTenant(): ?Tenant
    {
        $tenant = Tenant::query()->whereKey($this->tenantId)->lockForUpdate()->first();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function leaveTenantContext(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        app(TenancyDatabaseManager::class)->purgeTenantConnection();

        $provisioner = (string) config('tenancy.provisioning.connection', 'provisioner');
        if (array_key_exists($provisioner, DB::getConnections())) {
            DB::purge($provisioner);
        }
    }
}
