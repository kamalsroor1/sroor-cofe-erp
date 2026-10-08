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

    private static bool $harnessStaleFilesSwept = false;

    private static int $harnessPhoneSequence = 0;

    private static ?string $harnessTemplate = null;

    /**
     * @var array<string, array{tenant: Tenant, admin_id: int, store_id: int, database: string}>
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

        if ($driver === 'sqlite') {
            $this->sweepStaleHarnessFiles();
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

        $database = (string) $tenant->database()->getName();
        $this->harnessTenants[$id] = [
            'tenant' => $tenant,
            'admin_id' => 0,
            'store_id' => 0,
            'database' => $database,
        ];

        if ($template !== null) {
            $this->cloneTenantTemplate($template, $tenant, $database);
        }

        $tenant->domains()->create(['domain' => $this->tenantDomain($tenant)]);

        if (! $this->tenantDatabaseExists($tenant)) {
            throw new RuntimeException("Harness: tenant database [{$database}] was not created by the TenantCreated pipeline.");
        }

        if ($template === null) {
            $this->inTenant($tenant, function (): void {
                (new PermissionsSeeder)->run();
            });
            $this->rememberTenantTemplate($database);
        }

        [$adminId, $storeId] = $this->inTenant($tenant, function () use ($id): array {
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
     * @return array{tenant: Tenant, admin_id: int, store_id: int, database: string}
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

        return $this->tenantDriverIsMysql() ? $template : null;
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

    private function cloneTenantTemplate(string $template, Tenant $tenant, string $database): void
    {
        if ($this->tenantDriverIsSqlite()) {
            if (! copy($template, database_path($database))) {
                throw new RuntimeException("Harness: could not clone the tenant template into [{$database}].");
            }

            return;
        }

        $tenant->database()->manager()->createDatabase($tenant);
        $this->copyMysqlSchema($template, $database);
    }

    /**
     * Copy every table (structure with indexes + foreign keys, then rows) of $from into
     * the existing schema $to, on the DDL connection so the central transaction survives.
     */
    private function copyMysqlSchema(string $from, string $to): void
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
                $create = (array) $ddl->selectOne("SHOW CREATE TABLE `{$from}`.`{$table}`");
                $ddl->unprepared((string) $create['Create Table']);
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

    /**
     * Remove harness sqlite files / storage dirs left by a crashed run (older than one
     * hour, so a concurrently running suite is never touched). Once per process.
     */
    private function sweepStaleHarnessFiles(): void
    {
        if (self::$harnessStaleFilesSwept) {
            return;
        }
        self::$harnessStaleFilesSwept = true;

        $cutoff = time() - 3600;
        $prefix = (string) config('tenancy.database.prefix', 'tenant_').static::$harnessTenantPrefix;
        $patterns = [
            database_path($prefix.'*'),
            $this->harnessCentralStoragePath().DIRECTORY_SEPARATOR.config('tenancy.filesystem.suffix_base', 'tenant').static::$harnessTenantPrefix.'*',
        ];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $path) {
                if ((int) @filemtime($path) >= $cutoff) {
                    continue;
                }

                is_dir($path) ? File::deleteDirectory($path) : @unlink($path);
            }
        }
    }
}
