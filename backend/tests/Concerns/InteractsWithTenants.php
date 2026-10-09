<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\CentralUser;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * IDEN-4.8 multi-tenant test harness (database-per-tenant, like production).
 *
 * - Central DB: the default connection (sqlite `:memory:` in phpunit.xml, a real
 *   MySQL schema in phpunit.mysql.xml). Only CENTRAL migrations run there.
 * - Each tenant: a real stancl tenant created through the production
 *   `TenantCreated` pipeline (CreateDatabase + MigrateDatabase), so on sqlite it
 *   is its own file `database/tenant_qa….sqlite` and on MySQL its own schema.
 *   Every tenant is born with the PermissionsSeeder matrix, one main store and
 *   one `admin` user (mirrors TenantProvisionerService without a Plan).
 * - Everything the harness creates is removed after each test (DB files /
 *   schemas and the tenant-suffixed storage directory).
 * - [CTO W1 Q6] Every tenant gets its own auto-increment range (tenantIdOffset()):
 *   rows created inside two tenants never share an id, so a query that leaks across
 *   tenants cannot pass by accident because "id 1 exists over there too". Rows cloned
 *   from the template (the permission matrix) keep the template's ids.
 * - [CTO W1 Q6] Leftovers of crashed/killed runs (tenant DB files or schemas, storage
 *   directories, templates) are dropped when a process starts, not only on shutdown.
 *   Only artifacts proven to be harness-made are touched: the harness stamps its
 *   databases (sqlite `application_id`, MySQL comment on `migrations`), so a real
 *   developer tenant whose slug happens to start with "qa" is never dropped.
 *
 * Rules for tests using it:
 * - Tenant models (User, Store, Item…) are only valid INSIDE inTenant(); outside
 *   it the default connection is central. Pass ids/values out, not models to save.
 * - Authenticate HTTP calls with tenantHeaders() (Bearer token from the tenant DB).
 *   Tests\TenantTestCase resets tenancy + auth guards around every request, so a
 *   request never inherits the previous request's tenant or user.
 *
 * Standalone use (without TenantTestCase): `use RefreshDatabase, InteractsWithTenants;`
 * — setUp/tearDown hooks are wired automatically by Laravel's trait conventions.
 */
trait InteractsWithTenants
{
    /** Prefix of every harness tenant id: database name = tenant_qa<random>. */
    protected static string $harnessTenantPrefix = 'qa';

    /** Name of the cloned connection used for CREATE/DROP DATABASE on MySQL. */
    protected static string $harnessDdlConnection = 'tenant_harness_ddl';

    /**
     * Width of each tenant's id range: tenant N of the process starts every auto-increment
     * table at N * step + 1. 2000 slots keep the largest start (2e9) inside a signed INT.
     */
    protected static int $harnessIdOffsetStep = 1_000_000;

    private const HARNESS_ID_OFFSET_SLOTS = 2000;

    /** sqlite header `application_id` stamped on every harness tenant DB ("QAHT"). */
    protected const HARNESS_SQLITE_APPLICATION_ID = 0x51414854;

    /** MySQL marker: comment on the tenant's `migrations` table, `<marker>:<unix time>`. */
    private const HARNESS_MYSQL_MARKER = 'sroor-qa-harness';

    /**
     * Marker file written into every tenant storage directory the harness creates; content
     * `<marker>:<unix time>:<pid>`. The startup sweep only ever deletes directories holding it.
     */
    protected const HARNESS_STORAGE_MARKER_FILE = '.sroor-qa-harness';

    /** DB hosts the startup sweep may DROP schemas on (plus TEST_HARNESS_SWEEP_DB_HOSTS, comma-separated). */
    private const HARNESS_SWEEP_LOCAL_DB_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    /**
     * A tenant DB/storage dir lives for one test, so anything older than this belongs to a
     * dead run. Kept well above any single test so a concurrently running suite is safe.
     */
    private const HARNESS_STALE_TENANT_SECONDS = 900;

    /** Templates live for a whole process (full suite), so they get a longer grace period. */
    private const HARNESS_STALE_TEMPLATE_SECONDS = 10800;

    private static bool $harnessStaleFilesSwept = false;

    private static int $harnessPhoneSequence = 0;

    private static int $harnessTenantSequence = 0;

    private static ?string $harnessTemplate = null;

    /**
     * @var array<string, array{tenant: Tenant, admin_id: int, store_id: int, database: string, id_offset: int}>
     */
    private array $harnessTenants = [];

    protected function setUpInteractsWithTenants(): void
    {
        $this->harnessTenants = [];

        $central = $this->centralConnectionName();
        $driver = (string) config("database.connections.{$central}.driver");

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // CREATE/DROP DATABASE implicitly commits the open transaction of the session that
            // issues it. Running that DDL on a cloned connection (separate session) keeps the
            // RefreshDatabase transaction on the central connection intact.
            config([
                'database.connections.'.static::$harnessDdlConnection => config("database.connections.{$central}"),
                'tenancy.database.template_tenant_connection' => static::$harnessDdlConnection,
            ]);
        }

        if (! self::$harnessStaleFilesSwept) {
            self::$harnessStaleFilesSwept = true;
            $this->sweepStaleHarnessArtifacts();
        }
    }

    protected function tearDownInteractsWithTenants(): void
    {
        $this->cleanUpTenants();
    }

    protected function centralConnectionName(): string
    {
        return (string) config('tenancy.database.central_connection', config('database.default'));
    }

    /**
     * Provision a fully isolated tenant: central row + domain, own database (migrated
     * through the production pipeline), permission matrix, main store and admin user.
     *
     * @param  array<string, mixed>  $attributes  central `tenants` columns to override
     */
    protected function createTenant(array $attributes = []): Tenant
    {
        $this->endTenancy();

        $id = (string) ($attributes['id'] ?? static::$harnessTenantPrefix.Str::lower(Str::random(12)));
        $template = $this->tenantTemplate();

        $defaults = [
            'id' => $id,
            'name' => 'مستأجر اختبار '.$id,
            'slug' => $id,
            'email' => $id.'@harness.test',
            'status' => 'active',
            'subscription_ends_at' => now()->addMonth(),
            'enabled_features' => [],
        ];

        if ($template !== null) {
            // stancl's CreateDatabase job stops the TenantCreated pipeline on this internal
            // flag; the database is then cloned from this process's already-migrated template.
            // The first tenant of every process still goes through the real pipeline.
            $defaults['tenancy_create_database'] = false;
        }

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->create(array_merge($defaults, $attributes, ['id' => $id]));

        // Before anything else can fail: proof of origin for the startup sweep.
        $this->markHarnessStorageDirectory($id);

        $database = (string) $tenant->database()->getName();
        $offset = $this->nextHarnessIdOffset();
        $this->harnessTenants[$id] = [
            'tenant' => $tenant,
            'admin_id' => 0,
            'store_id' => 0,
            'database' => $database,
            'id_offset' => $offset,
        ];

        if ($template !== null) {
            $this->cloneTenantTemplate($template, $tenant, $database, $offset);
        }

        $tenant->domains()->create(['domain' => $this->tenantDomain($tenant)]);

        if (! $this->tenantDatabaseExists($tenant)) {
            throw new RuntimeException("Harness: tenant database [{$database}] was not created by the TenantCreated pipeline.");
        }

        if ($template === null) {
            $this->inTenant($tenant, function (): void {
                (new PermissionsSeeder)->run();

                if ($this->tenantDriverIsSqlite()) {
                    DB::statement('PRAGMA application_id = '.self::HARNESS_SQLITE_APPLICATION_ID);
                }
            });

            if ($this->tenantDriverIsMysql()) {
                $this->stampMysqlHarnessMarker($database);
            }

            $this->rememberTenantTemplate($database);

            if ($this->tenantDriverIsMysql()) {
                // Clones get their range inside CREATE TABLE; the pipeline tenant needs ALTERs (once per process).
                $this->applyMysqlIdOffset($database, $offset);
            }
        }

        [$adminId, $storeId] = $this->inTenant($tenant, function () use ($id, $offset): array {
            if ($this->tenantDriverIsSqlite()) {
                $this->applySqliteIdOffset($offset);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $store = Store::query()->create([
                'name' => 'الفرع الرئيسي',
                'code' => 'MAIN-01',
                'type' => 'retail',
                'is_main' => true,
                'is_active' => true,
            ]);

            $admin = User::query()->create([
                'name' => 'مدير '.$id,
                'email' => 'admin@'.$id.'.harness.test',
                'phone' => $this->nextHarnessPhone(),
                'password' => Hash::make('password'),
                'is_active' => true,
                'default_store_id' => $store->id,
            ]);
            $admin->assignRole('admin');

            return [(int) $admin->id, (int) $store->id];
        });

        $this->harnessTenants[$id]['admin_id'] = $adminId;
        $this->harnessTenants[$id]['store_id'] = $storeId;

        return $tenant;
    }

    /**
     * Run $callback inside $tenant's database, restoring the previous context
     * (central or another tenant) afterwards, even if the callback throws.
     *
     * @template TReturn
     *
     * @param  Closure(Tenant): TReturn  $callback
     * @return TReturn
     */
    protected function inTenant(Tenant $tenant, Closure $callback): mixed
    {
        return $tenant->run($callback);
    }

    /** The tenant's admin user (fresh from the tenant DB; do not save it outside inTenant()). */
    protected function tenantAdmin(Tenant $tenant): User
    {
        $adminId = $this->harnessEntry($tenant)['admin_id'];

        return $this->inTenant($tenant, fn (): User => User::query()->findOrFail($adminId));
    }

    /**
     * First id of this tenant's private range minus one: every auto-increment table of the
     * tenant hands out ids above it (template-cloned rows such as permissions excepted).
     */
    protected function tenantIdOffset(Tenant $tenant): int
    {
        return $this->harnessEntry($tenant)['id_offset'];
    }

    /** The tenant's main store (fresh from the tenant DB). */
    protected function tenantStore(Tenant $tenant): Store
    {
        $storeId = $this->harnessEntry($tenant)['store_id'];

        return $this->inTenant($tenant, fn (): Store => Store::query()->findOrFail($storeId));
    }

    /**
     * Create an extra user inside the tenant, optionally with a role and/or direct permissions.
     *
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $attributes
     */
    protected function createTenantUser(Tenant $tenant, ?string $role = null, array $permissions = [], array $attributes = []): User
    {
        $storeId = $this->harnessEntry($tenant)['store_id'];

        return $this->inTenant($tenant, function () use ($role, $permissions, $attributes, $storeId): User {
            $suffix = Str::lower(Str::random(8));

            $user = User::query()->create(array_merge([
                'name' => 'مستخدم '.$suffix,
                'email' => $suffix.'@user.harness.test',
                'phone' => $this->nextHarnessPhone(),
                'password' => Hash::make('password'),
                'is_active' => true,
                'default_store_id' => $storeId,
            ], $attributes));

            if ($role !== null) {
                $user->assignRole($role);
            }

            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            if ($permissions !== []) {
                $user->givePermissionTo($permissions);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $user;
        });
    }

    /** A Sanctum personal access token stored in the tenant DB (defaults to the tenant admin). */
    protected function tenantToken(Tenant $tenant, ?User $user = null): string
    {
        $userId = $user?->getKey() ?? $this->harnessEntry($tenant)['admin_id'];

        return $this->inTenant(
            $tenant,
            fn (): string => User::query()->findOrFail($userId)->createToken('harness')->plainTextToken,
        );
    }

    /**
     * Headers for an API call as $user (default: tenant admin) on $store (default: main store),
     * selecting the tenant with `X-Tenant` the way the Android/Electron clients do.
     *
     * @return array<string, string>
     */
    protected function tenantHeaders(Tenant $tenant, ?User $user = null, Store|int|null $store = null): array
    {
        $storeId = $store instanceof Store ? (int) $store->getKey() : ($store ?? $this->harnessEntry($tenant)['store_id']);

        return [
            'Accept' => 'application/json',
            'X-Tenant' => (string) $tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$this->tenantToken($tenant, $user),
            'X-Store-Id' => (string) $storeId,
        ];
    }

    /**
     * Same tenant/store selection as tenantHeaders() but WITHOUT a token: the request reaches
     * the tenant's routes as a guest, so authentication (401) is what gets tested.
     *
     * @return array<string, string>
     */
    protected function tenantGuestHeaders(Tenant $tenant, Store|int|null $store = null): array
    {
        $storeId = $store instanceof Store ? (int) $store->getKey() : ($store ?? $this->harnessEntry($tenant)['store_id']);

        return [
            'Accept' => 'application/json',
            'X-Tenant' => (string) $tenant->getTenantKey(),
            'X-Store-Id' => (string) $storeId,
        ];
    }

    /** The tenant's own host (domain record), for host-based resolution like the SPA. */
    protected function tenantDomain(Tenant $tenant): string
    {
        return $tenant->getTenantKey().'.harness.test';
    }

    /** Absolute URL on the tenant's host, e.g. tenantUrl($t, '/api/v1/items'). */
    protected function tenantUrl(Tenant $tenant, string $path): string
    {
        return 'http://'.$this->tenantDomain($tenant).'/'.ltrim($path, '/');
    }

    /**
     * A platform super admin in the CENTRAL database (never mirrored into any tenant).
     *
     * Works with today's CentralUser (central `users` table, `web` guard) and with the
     * standalone CentralUser of IDEN-1.1 (`central_users`, `central` guard): attributes
     * are filtered to the real columns and the role uses the model's own guard.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function centralSuperAdmin(array $attributes = []): CentralUser
    {
        $this->endTenancy();

        $user = new CentralUser;
        $connection = (string) $user->getConnectionName();
        $columns = Schema::connection($connection)->getColumnListing($user->getTable());
        $suffix = Str::lower(Str::random(8));

        $values = array_merge([
            'name' => 'مشرف المنصة '.$suffix,
            'email' => 'super-'.$suffix.'@central.harness.test',
            'phone' => $this->nextHarnessPhone(),
            'password' => Hash::make('password'),
            'is_active' => true,
        ], $attributes);

        $user->forceFill(array_intersect_key($values, array_flip($columns)))->save();

        $centralSeeder = 'Database\\Seeders\\CentralPermissionsSeeder';
        if (class_exists($centralSeeder) && ! Role::query()->where('name', 'super_admin')->exists()) {
            $this->seed($centralSeeder);
        }

        $guard = Guard::getDefaultName($user);
        $role = Role::findOrCreate('super_admin', $guard);
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->refresh();
    }

    /**
     * Bearer headers for a central (control-plane) user. Deliberately WITHOUT
     * X-Tenant / X-Store-Id: central calls never carry tenant context.
     *
     * @return array<string, string>
     */
    protected function centralHeaders(Model $centralUser): array
    {
        $this->endTenancy();

        if (! method_exists($centralUser, 'createToken')) {
            throw new RuntimeException('Harness: central user model cannot issue tokens.');
        }

        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$centralUser->createToken('harness-central')->plainTextToken,
        ];
    }

    /** Physical database name: sqlite file name under database/ or MySQL schema name. */
    protected function tenantDatabaseName(Tenant $tenant): string
    {
        return $this->harnessTenants[(string) $tenant->getTenantKey()]['database'] ?? (string) $tenant->database()->getName();
    }

    protected function tenantDatabaseExists(Tenant $tenant): bool
    {
        return $tenant->database()->manager()->databaseExists($this->tenantDatabaseName($tenant));
    }

    /**
     * Drop every database and tenant storage directory the harness created. Idempotent;
     * runs automatically before the application is destroyed.
     */
    protected function cleanUpTenants(): void
    {
        $this->endTenancy();

        $failures = [];

        foreach ($this->harnessTenants as $id => $entry) {
            $tenant = $entry['tenant'];

            DB::purge('tenant');

            try {
                $manager = $tenant->database()->manager();
                if ($manager->databaseExists($entry['database'])) {
                    $manager->deleteDatabase($tenant);
                }

                // Windows keeps an sqlite file locked while a PDO handle is alive.
                if ($manager->databaseExists($entry['database'])) {
                    gc_collect_cycles();
                    $manager->deleteDatabase($tenant);
                }

                if ($manager->databaseExists($entry['database'])) {
                    $failures[] = $entry['database'];
                }
            } catch (\Throwable $e) {
                $failures[] = $entry['database'].' ('.$e->getMessage().')';
            }

            $storageDir = $this->tenantStoragePath($id);
            if (is_dir($storageDir)) {
                File::deleteDirectory($storageDir);
            }

            unset($this->harnessTenants[$id]);
        }

        if (config('database.connections.'.static::$harnessDdlConnection) !== null) {
            DB::purge(static::$harnessDdlConnection);
        }

        if ($failures !== []) {
            throw new RuntimeException('Harness: could not remove tenant databases: '.implode(', ', $failures));
        }
    }

    protected function endTenancy(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }

    /**
     * @return array{tenant: Tenant, admin_id: int, store_id: int, database: string, id_offset: int}
     */
    private function harnessEntry(Tenant $tenant): array
    {
        $id = (string) $tenant->getTenantKey();

        if (! isset($this->harnessTenants[$id])) {
            throw new RuntimeException("Harness: tenant [{$id}] was not created by createTenant().");
        }

        return $this->harnessTenants[$id];
    }

    /** storage/<suffix_base><id> as built by stancl's FilesystemTenancyBootstrapper. */
    private function tenantStoragePath(string $tenantId): string
    {
        $base = $this->harnessCentralStoragePath();

        return $base.DIRECTORY_SEPARATOR.config('tenancy.filesystem.suffix_base', 'tenant').$tenantId;
    }

    /** Create the tenant's storage directory with the harness marker file inside. */
    private function markHarnessStorageDirectory(string $tenantId): void
    {
        $dir = $this->tenantStoragePath($tenantId);
        File::ensureDirectoryExists($dir);

        $marker = $dir.DIRECTORY_SEPARATOR.self::HARNESS_STORAGE_MARKER_FILE;
        if (file_put_contents($marker, self::HARNESS_MYSQL_MARKER.':'.time().':'.getmypid()) === false) {
            throw new RuntimeException("Harness: could not write the storage marker [{$marker}].");
        }
    }

    /**
     * Creation time recorded in a harness storage marker, or null when the directory holds
     * no (readable, well-formed) marker: such a directory is never harness-made for the sweep.
     */
    private function harnessStorageMarkerCreatedAt(string $dir): ?int
    {
        $marker = $dir.DIRECTORY_SEPARATOR.self::HARNESS_STORAGE_MARKER_FILE;
        if (! is_file($marker)) {
            return null;
        }

        $content = (string) @file_get_contents($marker);
        if (preg_match('/^'.preg_quote(self::HARNESS_MYSQL_MARKER, '/').':(\d+)(?::\d+)?$/', trim($content), $m) !== 1) {
            return null;
        }

        return (int) $m[1];
    }

    private function harnessCentralStoragePath(): string
    {
        // Resolved in central context, so this is the un-suffixed storage path.
        return rtrim(storage_path(), '\\/');
    }

    /**
     * This process's migrated + permission-seeded tenant template (sqlite: file path,
     * MySQL: schema name), or null when the next tenant must run the full pipeline.
     */
    private function tenantTemplate(): ?string
    {
        $template = self::$harnessTemplate;

        if ($template === null) {
            return null;
        }

        if ($this->tenantDriverIsSqlite()) {
            return is_file($template) ? $template : null;
        }

        if (! $this->tenantDriverIsMysql()) {
            return null;
        }

        // Another process's startup sweep may have dropped a template older than the grace
        // period; fall back to the full pipeline (which builds a new template) instead of failing.
        if (! $this->mysqlSchemaExists($template)) {
            self::$harnessTemplate = null;

            return null;
        }

        return $template;
    }

    /**
     * Snapshot the first tenant of the process (just migrated by the real pipeline and
     * seeded with the permission matrix) so later tenants are a clone instead of a full
     * migration run. sqlite: a file in the system temp dir; MySQL: a `tenant_qatpl…`
     * schema. Both are removed when the PHP process shuts down.
     */
    private function rememberTenantTemplate(string $database): void
    {
        DB::purge('tenant');

        if ($this->tenantDriverIsSqlite()) {
            $template = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-tenant-harness-'.getmypid().'-'.Str::lower(Str::random(6)).'.sqlite';
            if (! copy(database_path($database), $template)) {
                return;
            }

            self::$harnessTemplate = $template;
            register_shutdown_function(static function () use ($template): void {
                if (is_file($template)) {
                    @unlink($template);
                }
            });

            return;
        }

        if (! $this->tenantDriverIsMysql()) {
            return;
        }

        $template = config('tenancy.database.prefix', 'tenant_').static::$harnessTenantPrefix.'tpl'.Str::lower(Str::random(10));
        $ddl = DB::connection(static::$harnessDdlConnection);
        $ddl->statement("CREATE DATABASE `{$template}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->copyMysqlSchema($database, $template);

        self::$harnessTemplate = $template;

        /** @var array<string, mixed> $config */
        $config = (array) config('database.connections.'.static::$harnessDdlConnection);
        register_shutdown_function(static function () use ($template, $config): void {
            try {
                $pdo = new \PDO(
                    sprintf('mysql:host=%s;port=%s', $config['host'] ?? '127.0.0.1', $config['port'] ?? '3306'),
                    (string) ($config['username'] ?? ''),
                    (string) ($config['password'] ?? ''),
                );
                $pdo->exec("DROP DATABASE IF EXISTS `{$template}`");
            } catch (\Throwable) {
                // Best effort: a leftover tenant_qatpl* schema only costs disk on a test server.
            }
        });
    }

    private function cloneTenantTemplate(string $template, Tenant $tenant, string $database, int $idOffset): void
    {
        if ($this->tenantDriverIsSqlite()) {
            if (! copy($template, database_path($database))) {
                throw new RuntimeException("Harness: could not clone the tenant template into [{$database}].");
            }

            return;
        }

        $tenant->database()->manager()->createDatabase($tenant);
        $this->copyMysqlSchema($template, $database, $idOffset + 1);
    }

    /**
     * Copy every table (structure with indexes + foreign keys, then rows) of $from into
     * the existing schema $to, on the DDL connection so the central transaction survives.
     *
     * With $autoIncrementStart (tenant clones) every table starts its ids there and the
     * harness marker gets a fresh timestamp; without it (template snapshot) the CREATE
     * statements are copied verbatim.
     */
    private function copyMysqlSchema(string $from, string $to, ?int $autoIncrementStart = null): void
    {
        $ddl = DB::connection(static::$harnessDdlConnection);
        $central = (string) $ddl->getDatabaseName();

        $tables = array_map(
            static fn (object $row): string => (string) ((array) $row)['name'],
            $ddl->select(
                "select table_name as name from information_schema.tables where table_schema = ? and table_type = 'BASE TABLE'",
                [$from],
            ),
        );

        $ddl->statement('SET FOREIGN_KEY_CHECKS=0');
        // SHOW CREATE TABLE emits unqualified names (also in REFERENCES), so build the copy
        // from inside the target schema.
        $ddl->unprepared("USE `{$to}`");

        try {
            foreach ($tables as $table) {
                $create = (string) ((array) $ddl->selectOne("SHOW CREATE TABLE `{$from}`.`{$table}`"))['Create Table'];
                if ($autoIncrementStart !== null) {
                    $create = $this->rewriteMysqlTableOptions($create, $autoIncrementStart, $table === 'migrations');
                }
                $ddl->unprepared($create);
            }

            foreach ($tables as $table) {
                if ($ddl->selectOne("select 1 as present from `{$from}`.`{$table}` limit 1") !== null) {
                    $ddl->unprepared("INSERT INTO `{$to}`.`{$table}` SELECT * FROM `{$from}`.`{$table}`");
                }
            }
        } finally {
            $ddl->unprepared("USE `{$central}`");
            $ddl->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function tenantTemplateDriver(): string
    {
        $template = (string) (config('tenancy.database.template_tenant_connection') ?: $this->centralConnectionName());

        return (string) config("database.connections.{$template}.driver");
    }

    private function tenantDriverIsSqlite(): bool
    {
        return $this->tenantTemplateDriver() === 'sqlite';
    }

    private function tenantDriverIsMysql(): bool
    {
        return in_array($this->tenantTemplateDriver(), ['mysql', 'mariadb'], true);
    }

    /** Dummy, never-real phone numbers in the 0100000xxxx test range. */
    private function nextHarnessPhone(): string
    {
        self::$harnessPhoneSequence++;

        return '0100'.str_pad((string) (getmypid() % 1000), 3, '0', STR_PAD_LEFT)
            .str_pad((string) (self::$harnessPhoneSequence % 10000), 4, '0', STR_PAD_LEFT);
    }

    /** Next id range of this process: 1 * step, 2 * step, … (wraps after the last slot). */
    private function nextHarnessIdOffset(): int
    {
        $slot = (self::$harnessTenantSequence % self::HARNESS_ID_OFFSET_SLOTS) + 1;
        self::$harnessTenantSequence++;

        return $slot * static::$harnessIdOffsetStep;
    }

    /** Inside the tenant (sqlite): every AUTOINCREMENT table continues above $offset. */
    private function applySqliteIdOffset(int $offset): void
    {
        $tables = array_map(
            static fn (object $row): string => (string) ((array) $row)['name'],
            DB::select("select name from sqlite_master where type = 'table' and sql like '%autoincrement%'"),
        );

        DB::transaction(static function () use ($tables, $offset): void {
            foreach ($tables as $table) {
                $current = (int) DB::scalar('select seq from sqlite_sequence where name = ?', [$table]);
                DB::delete('delete from sqlite_sequence where name = ?', [$table]);
                DB::insert('insert into sqlite_sequence (name, seq) values (?, ?)', [$table, max($current, $offset)]);
            }
        });
    }

    /** MySQL pipeline tenant (not cloned): move every auto-increment counter above $offset. */
    private function applyMysqlIdOffset(string $database, int $offset): void
    {
        $ddl = DB::connection(static::$harnessDdlConnection);

        $tables = array_map(
            static fn (object $row): string => (string) ((array) $row)['name'],
            $ddl->select(
                "select distinct table_name as name from information_schema.columns where table_schema = ? and extra like '%auto_increment%'",
                [$database],
            ),
        );

        foreach ($tables as $table) {
            $ddl->statement("ALTER TABLE `{$database}`.`{$table}` AUTO_INCREMENT = ".($offset + 1));
        }
    }

    /** Proof of origin for the startup sweep; inherited by the template and every clone. */
    private function stampMysqlHarnessMarker(string $database): void
    {
        DB::connection(static::$harnessDdlConnection)->statement(
            "ALTER TABLE `{$database}`.`migrations` COMMENT = '".self::HARNESS_MYSQL_MARKER.':'.time()."'",
        );
    }

    /**
     * Replace the table options of a SHOW CREATE TABLE statement: ids start at $start, and
     * for the `migrations` table the harness marker is re-stamped with the clone's time.
     */
    private function rewriteMysqlTableOptions(string $create, int $start, bool $stampMarker): string
    {
        $end = strrpos($create, ')');
        if ($end === false) {
            return $create;
        }

        $columns = substr($create, 0, $end + 1);
        $options = (string) preg_replace('/\sAUTO_INCREMENT=\d+/i', '', substr($create, $end + 1));

        if ($stampMarker) {
            $options = (string) preg_replace("/\\sCOMMENT='(?:[^'\\\\]|\\\\.|'')*'/i", '', $options);
            $options .= " COMMENT='".self::HARNESS_MYSQL_MARKER.':'.time()."'";
        }

        return $columns.$options.' AUTO_INCREMENT='.$start;
    }

    private function mysqlSchemaExists(string $schema): bool
    {
        return DB::connection(static::$harnessDdlConnection)->selectOne(
            'select 1 as present from information_schema.schemata where schema_name = ?',
            [$schema],
        ) !== null;
    }

    /**
     * Remove what crashed or killed runs left behind: harness tenant databases (sqlite files /
     * MySQL schemas), tenant storage directories and process templates. Runs once per process
     * at startup (setUp) and can be called directly. Returns the names it removed.
     *
     * Safety: only artifacts proven to be harness-made (marker, or the exact shape and content
     * the harness produces) and older than a grace period are removed, so neither a real
     * developer tenant (e.g. slug "qahwa") nor a concurrently running suite is ever touched.
     *
     * @return list<string>
     */
    protected function sweepStaleHarnessArtifacts(): array
    {
        $removed = [];

        // Never sweep (delete files, DROP schemas) outside the testing environment.
        if (! app()->environment('testing')) {
            return $removed;
        }

        if ($this->tenantDriverIsSqlite()) {
            array_push($removed, ...$this->sweepStaleSqliteDatabases());
        } elseif ($this->tenantDriverIsMysql()
            && config('database.connections.'.static::$harnessDdlConnection) !== null
            && $this->harnessDdlHostIsLocal()) {
            array_push($removed, ...$this->sweepStaleMysqlSchemas());
        }

        array_push($removed, ...$this->sweepStaleStorageDirectories());

        return $removed;
    }

    /**
     * The DROP-capable sweep only runs against a local / explicitly allowlisted DB host, so a
     * misconfigured .env.testing pointing at a shared or production server is never swept.
     */
    private function harnessDdlHostIsLocal(): bool
    {
        $host = strtolower(trim((string) config('database.connections.'.static::$harnessDdlConnection.'.host', '')));
        if ($host === '') {
            return false;
        }

        $extra = array_filter(array_map(
            static fn (string $h): string => strtolower(trim($h)),
            explode(',', (string) env('TEST_HARNESS_SWEEP_DB_HOSTS', '')),
        ));

        return in_array($host, [...self::HARNESS_SWEEP_LOCAL_DB_HOSTS, ...$extra], true);
    }

    /** @return list<string> */
    private function sweepStaleSqliteDatabases(): array
    {
        $removed = [];
        $tenantCutoff = time() - self::HARNESS_STALE_TENANT_SECONDS;
        $prefix = (string) config('tenancy.database.prefix', 'tenant_').static::$harnessTenantPrefix;
        $sideFiles = [];

        foreach (glob(database_path($prefix.'*')) ?: [] as $path) {
            if (! is_file($path)) {
                continue;
            }

            if (preg_match('/-(journal|wal|shm)$/', $path) === 1) {
                $sideFiles[] = $path;

                continue;
            }

            if (self::sqliteApplicationId($path) !== self::HARNESS_SQLITE_APPLICATION_ID
                || (int) @filemtime($path) >= $tenantCutoff) {
                continue;
            }

            if (@unlink($path)) {
                $removed[] = basename($path);
            }
        }

        foreach ($sideFiles as $path) {
            $main = (string) preg_replace('/-(journal|wal|shm)$/', '', $path);
            if (! is_file($main) && (int) @filemtime($path) < $tenantCutoff && @unlink($path)) {
                $removed[] = basename($path);
            }
        }

        // Per-process templates live in the temp dir; their shutdown unlink fails while Windows
        // still holds a handle, and never runs when the process is killed.
        $templateCutoff = time() - self::HARNESS_STALE_TEMPLATE_SECONDS;
        foreach (glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-tenant-harness-*.sqlite*') ?: [] as $path) {
            if ($path === self::$harnessTemplate || ! is_file($path) || (int) @filemtime($path) >= $templateCutoff) {
                continue;
            }

            if (@unlink($path)) {
                $removed[] = basename($path);
            }
        }

        return $removed;
    }

    /** @return list<string> */
    private function sweepStaleMysqlSchemas(): array
    {
        $ddl = DB::connection(static::$harnessDdlConnection);
        $prefix = (string) config('tenancy.database.prefix', 'tenant_').static::$harnessTenantPrefix;
        $like = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $prefix).'%';
        $removed = [];

        $schemas = array_map(
            static fn (object $row): string => (string) ((array) $row)['name'],
            $ddl->select('select schema_name as name from information_schema.schemata where schema_name like ?', [$like]),
        );

        foreach ($schemas as $schema) {
            if ($schema === self::$harnessTemplate) {
                continue;
            }

            $createdAt = $this->mysqlHarnessSchemaCreatedAt($schema, $prefix);
            if ($createdAt === null) {
                continue;
            }

            $grace = str_starts_with($schema, $prefix.'tpl') ? self::HARNESS_STALE_TEMPLATE_SECONDS : self::HARNESS_STALE_TENANT_SECONDS;
            if ($createdAt >= time() - $grace) {
                continue;
            }

            $ddl->statement("DROP DATABASE IF EXISTS `{$schema}`");
            $removed[] = $schema;
        }

        return $removed;
    }

    /**
     * Creation time of a schema the harness provably made, or null when that cannot be proven
     * (such schemas are never dropped). Proof: the marker on `migrations`; for schemas left by
     * runs from before the marker existed, the exact id shape plus harness-only content.
     */
    private function mysqlHarnessSchemaCreatedAt(string $schema, string $prefix): ?int
    {
        $ddl = DB::connection(static::$harnessDdlConnection);

        $migrations = $ddl->selectOne(
            "select table_comment as comment from information_schema.tables where table_schema = ? and table_name = 'migrations'",
            [$schema],
        );
        $comment = $migrations === null ? '' : (string) ((array) $migrations)['comment'];
        if (str_starts_with($comment, self::HARNESS_MYSQL_MARKER.':')) {
            return (int) substr($comment, strlen(self::HARNESS_MYSQL_MARKER) + 1);
        }

        $quoted = preg_quote($prefix, '/');
        $isLegacyTemplate = preg_match('/^'.$quoted.'tpl[a-z0-9]{10}$/', $schema) === 1;
        $isLegacyTenant = preg_match('/^'.$quoted.'[a-z0-9]{12}$/', $schema) === 1;
        if (! $isLegacyTemplate && ! $isLegacyTenant) {
            return null;
        }

        $hasUsers = $ddl->selectOne(
            "select 1 as present from information_schema.tables where table_schema = ? and table_name = 'users'",
            [$schema],
        ) !== null;
        if ($migrations === null || ! $hasUsers) {
            return null;
        }

        $users = (array) $ddl->selectOne(
            "select count(*) as total, coalesce(sum(case when email like '%.harness.test' then 0 else 1 end), 0) as foreign_users from `{$schema}`.`users`",
        );

        // Templates are snapshotted before any user exists; tenants only ever hold harness users.
        $proven = $isLegacyTemplate
            ? (int) $users['total'] === 0
            : ((int) $users['total'] > 0 && (int) $users['foreign_users'] === 0);
        if (! $proven) {
            return null;
        }

        $created = (array) $ddl->selectOne(
            'select unix_timestamp(min(create_time)) as created from information_schema.tables where table_schema = ?',
            [$schema],
        );

        return $created['created'] === null ? null : (int) $created['created'];
    }

    /**
     * Only directories holding the harness marker file whose recorded creation time is past the
     * grace period are removed. Unmarked directories (a real developer tenant "qahwa…", or
     * legacy harness leftovers from before the marker existed) are always skipped: directory
     * mtime is not used, because it does not change on nested writes.
     *
     * @return list<string>
     */
    private function sweepStaleStorageDirectories(): array
    {
        $removed = [];
        $cutoff = time() - self::HARNESS_STALE_TENANT_SECONDS;
        $suffixBase = (string) config('tenancy.filesystem.suffix_base', 'tenant');
        $idPattern = '/^'.preg_quote(static::$harnessTenantPrefix, '/').'[a-z0-9]+$/';

        foreach (glob($this->harnessCentralStoragePath().DIRECTORY_SEPARATOR.$suffixBase.static::$harnessTenantPrefix.'*') ?: [] as $path) {
            $tenantId = substr(basename($path), strlen($suffixBase));

            if (! is_dir($path) || is_link($path) || preg_match($idPattern, $tenantId) !== 1) {
                continue;
            }

            $createdAt = $this->harnessStorageMarkerCreatedAt($path);
            if ($createdAt === null || $createdAt >= $cutoff) {
                continue;
            }

            // A database for this id still exists: a live harness tenant or a real one. Leave it.
            if ($this->tenantDatabaseExistsFor($tenantId)) {
                continue;
            }

            File::deleteDirectory($path);
            $removed[] = basename($path);
        }

        return $removed;
    }

    private function tenantDatabaseExistsFor(string $tenantId): bool
    {
        $name = (string) config('tenancy.database.prefix', 'tenant_').$tenantId;

        if ($this->tenantDriverIsSqlite()) {
            return is_file(database_path($name.config('tenancy.database.suffix', '')));
        }

        return $this->tenantDriverIsMysql() && $this->mysqlSchemaExists($name);
    }

    /** sqlite header field `application_id` (offset 68, big-endian), or null for non-sqlite files. */
    private static function sqliteApplicationId(string $path): ?int
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $header = (string) fread($handle, 72);
        fclose($handle);

        if (strlen($header) < 72 || ! str_starts_with($header, "SQLite format 3\0")) {
            return null;
        }

        $unpacked = unpack('N', substr($header, 68, 4));

        return $unpacked === false ? null : (int) $unpacked[1];
    }
}
