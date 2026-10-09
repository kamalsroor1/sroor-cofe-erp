<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\CentralUser;
use App\Models\Item;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * IDEN-4.8 smoke test for the multi-tenant harness (Tests\TenantTestCase +
 * Tests\Concerns\InteractsWithTenants).
 *
 * Proves the harness gives every test REAL database-per-tenant isolation:
 * two tenants never share rows, a request never inherits the previous
 * request's tenant / authenticated user, tokens do not cross tenants, the
 * central DB holds no tenant tables, nothing is left on disk afterwards, and
 * provisioning stays under the per-tenant overhead budget.
 *
 * Runs on sqlite (central :memory: + one file per tenant) and on MySQL 8
 * (QA-1 job, `--group mysql`).
 */
#[Group('harness')]
#[Group('mysql')]
final class TenantHarnessSmokeTest extends TenantTestCase
{
    /** Acceptance criterion: provisioning overhead < 1.5 s per tenant. */
    private const MAX_SECONDS_PER_TENANT = 1.5;

    public function test_two_tenants_get_distinct_databases_with_identical_baseline(): void
    {
        $a = $this->createTenant(['name' => 'محمصة الأمل']);
        $b = $this->createTenant(['name' => 'Coffee Corner']);

        $this->assertNotSame($a->getTenantKey(), $b->getTenantKey());
        $this->assertNotSame($this->tenantDatabaseName($a), $this->tenantDatabaseName($b));
        $this->assertTrue($this->tenantDatabaseExists($a));
        $this->assertTrue($this->tenantDatabaseExists($b));

        foreach ([$a, $b] as $tenant) {
            $baseline = $this->inTenant($tenant, fn (): array => [
                'tenant' => tenant()?->getTenantKey(),
                'users' => User::query()->count(),
                'main_stores' => Store::query()->where('is_main', true)->count(),
                'admin_is_admin' => User::query()->firstOrFail()->hasRole('admin'),
                'has_items_table' => Schema::hasTable('items'),
            ]);

            $this->assertSame([
                'tenant' => $tenant->getTenantKey(),
                'users' => 1,
                'main_stores' => 1,
                'admin_is_admin' => true,
                'has_items_table' => true,
            ], $baseline);
        }

        $this->assertSame('محمصة الأمل', Tenant::query()->findOrFail($a->getTenantKey())->name);
        $this->assertFalse(tenancy()->initialized, 'inTenant() must leave the test in central context.');
    }

    public function test_central_database_holds_no_tenant_tables(): void
    {
        $this->createTenant();

        $central = Schema::connection($this->centralConnectionName());

        $this->assertTrue($central->hasTable('tenants'));
        $this->assertTrue($central->hasTable('domains'));
        $this->assertFalse($central->hasTable('items'), 'Tenant tables leaked into the central DB.');
        $this->assertFalse($central->hasTable('invoices'), 'Tenant tables leaked into the central DB.');
        $this->assertFalse($central->hasTable('stores'), 'Tenant tables leaked into the central DB.');
    }

    public function test_rows_written_in_one_tenant_are_invisible_in_the_other(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        $this->inTenant($a, fn () => $this->makeItem('QA-A-01', 'بن يمني', '0.250'));

        $this->assertSame(1, $this->inTenant($a, fn (): int => Item::query()->count()));
        $this->assertSame(0, $this->inTenant($b, fn (): int => Item::query()->count()));
        $this->assertSame(
            '0.250',
            $this->inTenant($a, fn (): string => (string) Item::query()->where('code', 'QA-A-01')->value('current_stock')),
        );

        // Same code in B is allowed: unique constraints are per tenant DB.
        $this->inTenant($b, fn () => $this->makeItem('QA-A-01', 'Brazil Santos', '7.000'));
        $this->assertSame(
            'بن يمني',
            $this->inTenant($a, fn (): string => (string) Item::query()->where('code', 'QA-A-01')->value('name')),
        );
    }

    public function test_in_tenant_restores_the_previous_context_when_nested(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        $seen = $this->inTenant($a, function () use ($b): array {
            $inner = $this->inTenant($b, fn (): ?string => tenant()?->getTenantKey());

            return [$inner, tenant()?->getTenantKey()];
        });

        $this->assertSame([$b->getTenantKey(), $a->getTenantKey()], $seen);
        $this->assertFalse(tenancy()->initialized);
    }

    public function test_tenant_headers_authenticate_each_tenant_admin_against_its_own_database(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($a))
            ->assertOk()
            ->assertJsonPath('data.user.id', $this->tenantAdmin($a)->id)
            ->assertJsonPath('data.user.phone', $this->tenantAdmin($a)->phone);

        $this->assertFalse(tenancy()->initialized, 'A request must not leave tenancy initialized for the test.');

        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($b))
            ->assertOk()
            ->assertJsonPath('data.user.phone', $this->tenantAdmin($b)->phone);
    }

    public function test_api_lists_only_the_requesting_tenants_rows_on_sequential_requests(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();
        $this->inTenant($a, fn () => $this->makeItem('QA-ONLY-A', 'صنف المستأجر أ', '3.000'));
        $this->inTenant($b, fn () => $this->makeItem('QA-ONLY-B', 'صنف المستأجر ب', '4.000'));

        // A then B then A: each request must resolve its own tenant, never the previous one.
        $this->getJson('/api/v1/items', $this->tenantHeaders($a))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'QA-ONLY-A')
            ->assertJsonMissing(['code' => 'QA-ONLY-B']);

        $this->getJson('/api/v1/items', $this->tenantHeaders($b))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'QA-ONLY-B')
            ->assertJsonMissing(['code' => 'QA-ONLY-A']);

        $this->getJson('/api/v1/items', $this->tenantHeaders($a))
            ->assertOk()
            ->assertJsonPath('data.0.code', 'QA-ONLY-A');
    }

    public function test_host_based_resolution_reaches_the_tenant_database(): void
    {
        $a = $this->createTenant();
        $this->inTenant($a, fn () => $this->makeItem('QA-HOST-A', 'صنف بالدومين', '1.000'));

        $headers = $this->tenantHeaders($a);
        unset($headers['X-Tenant']);

        $this->getJson($this->tenantUrl($a, '/api/v1/items'), $headers)
            ->assertOk()
            ->assertJsonPath('data.0.code', 'QA-HOST-A');
    }

    public function test_token_of_tenant_a_is_rejected_by_tenant_b(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        $crossover = array_merge($this->tenantHeaders($b), [
            'Authorization' => 'Bearer '.$this->tenantToken($a),
        ]);

        $this->getJson('/api/v1/auth/me', $crossover)->assertUnauthorized();
        $this->getJson('/api/v1/items', $crossover)->assertUnauthorized();
    }

    public function test_authenticated_user_does_not_leak_into_the_next_request(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($a))->assertOk();

        // No Authorization header: the user resolved by the previous request must not be reused.
        $this->getJson('/api/v1/auth/me', ['X-Tenant' => $b->getTenantKey()])->assertUnauthorized();
        $this->getJson('/api/v1/auth/me', ['X-Tenant' => $a->getTenantKey()])->assertUnauthorized();
    }

    public function test_unknown_tenant_header_is_404(): void
    {
        $this->createTenant();

        $this->getJson('/api/v1/auth/me', ['X-Tenant' => 'qa-does-not-exist'])->assertNotFound();
    }

    public function test_tenant_user_with_explicit_permissions_is_scoped_to_its_tenant(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        $cashier = $this->createTenantUser($a, role: 'cashier');
        $viewer = $this->createTenantUser($a, permissions: ['items.view']);

        $this->assertTrue($this->inTenant($a, fn (): bool => User::query()->findOrFail($cashier->id)->hasRole('cashier')));
        $this->assertTrue($this->inTenant($a, fn (): bool => User::query()->findOrFail($viewer->id)->can('items.view')));
        $this->assertSame(1, $this->inTenant($b, fn (): int => User::query()->count()), 'Users created for A appeared in B.');

        $this->getJson('/api/v1/items', $this->tenantHeaders($a, $viewer))->assertOk();
        // The viewer's token lives in A's DB: presenting it to B must not authenticate anyone.
        $crossover = array_merge($this->tenantHeaders($b), [
            'Authorization' => 'Bearer '.$this->tenantToken($a, $viewer),
        ]);
        $this->getJson('/api/v1/items', $crossover)->assertUnauthorized();
    }

    public function test_central_super_admin_lives_only_in_the_central_database(): void
    {
        $tenant = $this->createTenant();
        $super = $this->centralSuperAdmin();

        $this->assertInstanceOf(CentralUser::class, $super);
        $this->assertSame($this->centralConnectionName(), $super->getConnectionName());
        $this->assertTrue(
            DB::connection($this->centralConnectionName())->table($super->getTable())->where('id', $super->getKey())->exists(),
        );
        // Harness facts only. Whether a CentralUser passes PlatformSuperAdmin::check()
        // is IDEN-1.x authorization semantics and is asserted in those tests.

        // Never mirrored into a tenant DB.
        $this->assertSame(
            0,
            $this->inTenant($tenant, fn (): int => User::query()->where('email', $super->email)->count()),
        );

        $centralHeaders = $this->centralHeaders($super);
        $this->assertArrayHasKey('Authorization', $centralHeaders);
        $this->assertArrayNotHasKey('X-Tenant', $centralHeaders);
        $this->assertArrayNotHasKey('X-Store-Id', $centralHeaders);
    }

    public function test_cleanup_removes_every_tenant_database_and_storage_directory(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        // Touch tenant-scoped storage so the cleanup has something to remove.
        $storageDirs = [];
        foreach ([$a, $b] as $tenant) {
            $storageDirs[] = $this->inTenant($tenant, function (): string {
                $dir = storage_path('app');
                @mkdir($dir, 0777, true);
                file_put_contents($dir.DIRECTORY_SEPARATOR.'harness-probe.txt', 'probe');

                return storage_path();
            });
        }

        foreach ($storageDirs as $dir) {
            $this->assertDirectoryExists($dir);
        }

        $this->cleanUpTenants();

        $this->assertFalse($this->tenantDatabaseExists($a), 'Tenant A database left behind.');
        $this->assertFalse($this->tenantDatabaseExists($b), 'Tenant B database left behind.');
        foreach ($storageDirs as $dir) {
            $this->assertDirectoryDoesNotExist($dir, 'Tenant storage directory left behind.');
        }
        $this->assertFalse(tenancy()->initialized);

        // Idempotent: a second cleanup (the automatic one in tearDown) is a no-op.
        $this->cleanUpTenants();
        $this->assertFalse($this->tenantDatabaseExists($a));
    }

    public function test_provisioning_overhead_is_under_the_per_tenant_budget(): void
    {
        // Warm-up so one-off per-process costs (template build, autoload) are not counted.
        $this->createTenant();

        $count = 3;
        $start = hrtime(true);
        for ($i = 0; $i < $count; $i++) {
            $this->createTenant();
        }
        $secondsPerTenant = (hrtime(true) - $start) / 1e9 / $count;

        $this->assertLessThan(
            self::MAX_SECONDS_PER_TENANT,
            $secondsPerTenant,
            sprintf('Harness overhead %.3fs per tenant exceeds %.1fs.', $secondsPerTenant, self::MAX_SECONDS_PER_TENANT),
        );
    }

    public function test_every_tenant_gets_its_own_id_range_so_ids_never_repeat_across_tenants(): void
    {
        $tenants = [$this->createTenant(), $this->createTenant(), $this->createTenant()];

        $offsets = array_map(fn (Tenant $t): int => $this->tenantIdOffset($t), $tenants);
        $this->assertCount(3, array_unique($offsets), 'Two tenants share an id range.');

        $seen = [];
        foreach ($tenants as $i => $tenant) {
            $offset = $offsets[$i];
            $ids = $this->inTenant($tenant, function () use ($i): array {
                $item = $this->makeItem('QA-RANGE-'.$i, 'صنف '.$i, '0.250');

                return [
                    'users' => (int) User::query()->min('id'),
                    'stores' => (int) Store::query()->min('id'),
                    'items' => (int) $item->id,
                ];
            });

            foreach ($ids as $table => $id) {
                $this->assertGreaterThan($offset, $id, "{$table} id is below the tenant's range.");
                $this->assertLessThanOrEqual($offset + 1000, $id, "{$table} id did not start at the tenant's range.");
                $seen[$table][] = $id;
            }

            // Template clones and the pipeline tenant both honour the range (first tenant of a
            // process is built by the real pipeline, later ones are cloned).
            $this->getJson('/api/v1/items', $this->tenantHeaders($tenant))
                ->assertOk()
                ->assertJsonPath('data.0.id', $ids['items']);
        }

        foreach ($seen as $table => $ids) {
            $this->assertCount(3, array_unique($ids), "The same {$table} id exists in two tenants.");
        }

        // A's item id simply does not exist in B (a leaked id cannot resolve to B's own row).
        $itemOfA = $seen['items'][0];
        $this->assertNull($this->inTenant($tenants[1], fn (): ?Item => Item::query()->find($itemOfA)));
        $this->getJson('/api/v1/items/'.$itemOfA, $this->tenantHeaders($tenants[1]))->assertNotFound();
    }

    public function test_startup_sweep_drops_only_stale_harness_made_artifacts(): void
    {
        $live = $this->createTenant();
        $grace = 2 * 3600;
        $ids = [
            'stale' => 'qa'.Str::lower(Str::random(12)),
            'fresh' => 'qa'.Str::lower(Str::random(12)),
            'foreign' => 'qa'.Str::lower(Str::random(12)),
            'orphan_dir' => 'qa'.Str::lower(Str::random(12)),
            'legacy_dir' => 'qa'.Str::lower(Str::random(12)),
            'fresh_dir' => 'qa'.Str::lower(Str::random(12)),
        ];
        $prefix = (string) config('tenancy.database.prefix');
        $storage = rtrim(storage_path(), '\\/').DIRECTORY_SEPARATOR.config('tenancy.filesystem.suffix_base');
        $sqlite = config('database.connections.'.$this->centralConnectionName().'.driver') === 'sqlite';
        $template = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-tenant-harness-0-'.Str::lower(Str::random(6)).'.sqlite';

        try {
            if ($sqlite) {
                $this->makeSqliteFile(database_path($prefix.$ids['stale'].'.sqlite'), harness: true, ageSeconds: $grace);
                $this->makeSqliteFile(database_path($prefix.$ids['fresh'].'.sqlite'), harness: true, ageSeconds: 0);
                // Same name shape but no harness marker: could be a real tenant ("qahwa…"). Never touched.
                $this->makeSqliteFile(database_path($prefix.$ids['foreign'].'.sqlite'), harness: false, ageSeconds: $grace);
                $this->makeSqliteFile($template, harness: true, ageSeconds: 4 * 3600);
            } else {
                $this->makeMysqlSchema($prefix.$ids['stale'], markerAge: $grace, email: null);
                $this->makeMysqlSchema($prefix.$ids['fresh'], markerAge: 0, email: null);
                $this->makeMysqlSchema($prefix.$ids['foreign'], markerAge: null, email: 'owner@real-shop.example');
            }

            // Marked + stale, no database: a crashed run's leftover. Swept.
            $this->makeStorageDir($storage.$ids['orphan_dir'], markerAge: $grace, mtimeAge: $grace);
            // Marked + stale but its database still exists: kept.
            $this->makeStorageDir($storage.$ids['foreign'], markerAge: $grace, mtimeAge: $grace);
            // Unmarked + old + no database: a real local tenant ("qahwa…", DB in MySQL) or a
            // pre-marker leftover. Never deleted.
            $this->makeStorageDir($storage.$ids['legacy_dir'], markerAge: null, mtimeAge: $grace);
            // Marked but fresh (a concurrently running suite), even with an old directory mtime: kept.
            $this->makeStorageDir($storage.$ids['fresh_dir'], markerAge: 0, mtimeAge: $grace);
            clearstatcache();

            $removed = $this->sweepStaleHarnessArtifacts();
            clearstatcache();

            if ($sqlite) {
                $this->assertContains($prefix.$ids['stale'].'.sqlite', $removed);
                $this->assertFileDoesNotExist(database_path($prefix.$ids['stale'].'.sqlite'));
                $this->assertFileExists(database_path($prefix.$ids['fresh'].'.sqlite'), 'A fresh (possibly concurrent) harness DB was swept.');
                $this->assertFileExists(database_path($prefix.$ids['foreign'].'.sqlite'), 'An unmarked database was swept.');
                $this->assertFileDoesNotExist($template, 'A stale process template survived the sweep.');
            } else {
                $this->assertContains($prefix.$ids['stale'], $removed);
                $this->assertFalse($this->schemaExists($prefix.$ids['stale']));
                $this->assertTrue($this->schemaExists($prefix.$ids['fresh']), 'A fresh (possibly concurrent) harness schema was swept.');
                $this->assertTrue($this->schemaExists($prefix.$ids['foreign']), 'A schema with real users was swept.');
            }

            $this->assertDirectoryDoesNotExist($storage.$ids['orphan_dir'], 'A stale marked harness storage directory survived.');
            $this->assertContains(basename($storage.$ids['orphan_dir']), $removed);
            $this->assertDirectoryExists($storage.$ids['foreign'], 'A storage directory whose database still exists was swept.');
            $this->assertDirectoryExists($storage.$ids['legacy_dir'], 'An unmarked storage directory (possibly a real tenant) was swept.');
            $this->assertFileExists($storage.$ids['legacy_dir'].DIRECTORY_SEPARATOR.'upload.txt', 'Contents of an unmarked storage directory were touched.');
            $this->assertDirectoryExists($storage.$ids['fresh_dir'], 'A fresh (possibly concurrent) marked storage directory was swept.');
            $this->assertNotContains(basename($storage.$ids['legacy_dir']), $removed);
            $this->assertNotContains(basename($storage.$ids['fresh_dir']), $removed);
            $this->assertDirectoryExists($storage.$live->getTenantKey(), 'The sweep removed the storage directory of a live tenant.');
            $this->assertTrue($this->tenantDatabaseExists($live), 'The sweep removed a live tenant of this test.');
            $this->assertNotContains($this->tenantDatabaseName($live), $removed);
        } finally {
            foreach ($ids as $id) {
                if ($sqlite) {
                    @unlink(database_path($prefix.$id.'.sqlite'));
                } else {
                    $this->ddl()->statement("DROP DATABASE IF EXISTS `{$prefix}{$id}`");
                }
                if (is_dir($storage.$id)) {
                    File::deleteDirectory($storage.$id);
                }
            }
            @unlink($template);
        }
    }

    public function test_harness_marks_the_storage_directory_of_every_tenant_it_creates(): void
    {
        $tenant = $this->createTenant();
        $dir = rtrim(storage_path(), '\\/').DIRECTORY_SEPARATOR.config('tenancy.filesystem.suffix_base').$tenant->getTenantKey();
        $marker = $dir.DIRECTORY_SEPARATOR.self::HARNESS_STORAGE_MARKER_FILE;

        $this->assertFileExists($marker);
        $this->assertMatchesRegularExpression('/^sroor-qa-harness:\d+:\d+$/', (string) file_get_contents($marker));

        $this->cleanUpTenants();
        $this->assertDirectoryDoesNotExist($dir, 'Cleanup left the marked storage directory behind.');
    }

    public function test_sweep_does_nothing_outside_the_testing_environment(): void
    {
        $dir = rtrim(storage_path(), '\\/').DIRECTORY_SEPARATOR.config('tenancy.filesystem.suffix_base').'qa'.Str::lower(Str::random(12));
        $env = $this->app['env'];

        try {
            $this->makeStorageDir($dir, markerAge: 2 * 3600, mtimeAge: 2 * 3600);
            $this->app['env'] = 'production';

            $this->assertSame([], $this->sweepStaleHarnessArtifacts());
            $this->assertDirectoryExists($dir, 'The sweep ran outside the testing environment.');
        } finally {
            $this->app['env'] = $env;
            if (is_dir($dir)) {
                File::deleteDirectory($dir);
            }
        }
    }

    public function test_schema_drop_sweep_is_allowed_only_on_local_or_allowlisted_db_hosts(): void
    {
        $original = config('database.connections.tenant_harness_ddl');
        $previous = getenv('TEST_HARNESS_SWEEP_DB_HOSTS');
        $check = fn (): bool => (bool) (new \ReflectionMethod(TenantTestCase::class, 'harnessDdlHostIsLocal'))->invoke($this);
        $cases = [
            '127.0.0.1' => true,
            'localhost' => true,
            '::1' => true,
            'LOCALHOST' => true,
            'db.prod.example.com' => false,
            '10.0.0.5' => false,
            '' => false,
        ];

        try {
            $this->setSweepAllowlist(false);
            foreach ($cases as $host => $allowed) {
                config(['database.connections.tenant_harness_ddl' => ['driver' => 'mysql', 'host' => (string) $host]]);
                $this->assertSame($allowed, $check(), "Host [{$host}]");
            }

            $this->setSweepAllowlist('mysql, mariadb');
            config(['database.connections.tenant_harness_ddl' => ['driver' => 'mysql', 'host' => 'mysql']]);
            $this->assertTrue($check(), 'An allowlisted CI service host was rejected.');
            config(['database.connections.tenant_harness_ddl' => ['driver' => 'mysql', 'host' => 'db.prod.example.com']]);
            $this->assertFalse($check(), 'A non-allowlisted host passed while an allowlist was set.');
        } finally {
            config(['database.connections.tenant_harness_ddl' => $original]);
            $this->setSweepAllowlist($previous);
        }
    }

    private function setSweepAllowlist(string|false $value): void
    {
        if ($value === false) {
            putenv('TEST_HARNESS_SWEEP_DB_HOSTS');
            unset($_ENV['TEST_HARNESS_SWEEP_DB_HOSTS'], $_SERVER['TEST_HARNESS_SWEEP_DB_HOSTS']);

            return;
        }

        putenv('TEST_HARNESS_SWEEP_DB_HOSTS='.$value);
        $_ENV['TEST_HARNESS_SWEEP_DB_HOSTS'] = $value;
        $_SERVER['TEST_HARNESS_SWEEP_DB_HOSTS'] = $value;
    }

    /** A tenant-style storage dir with a real file inside; $markerAge null = no harness marker. */
    private function makeStorageDir(string $dir, ?int $markerAge, int $mtimeAge): void
    {
        File::ensureDirectoryExists($dir);
        file_put_contents($dir.DIRECTORY_SEPARATOR.'upload.txt', 'probe');
        if ($markerAge !== null) {
            file_put_contents($dir.DIRECTORY_SEPARATOR.self::HARNESS_STORAGE_MARKER_FILE, 'sroor-qa-harness:'.(time() - $markerAge).':0');
        }
        touch($dir, time() - $mtimeAge);
    }

    private function makeSqliteFile(string $path, bool $harness, int $ageSeconds): void
    {
        $pdo = new \PDO('sqlite:'.$path);
        $pdo->exec('create table probe (id integer primary key autoincrement)');
        if ($harness) {
            $pdo->exec('PRAGMA application_id = '.self::HARNESS_SQLITE_APPLICATION_ID);
        }
        $pdo = null;

        if ($ageSeconds > 0) {
            touch($path, time() - $ageSeconds);
        }
    }

    private function makeMysqlSchema(string $schema, ?int $markerAge, ?string $email): void
    {
        $ddl = $this->ddl();
        $ddl->statement("CREATE DATABASE `{$schema}`");
        $comment = $markerAge === null ? '' : " COMMENT='sroor-qa-harness:".(time() - $markerAge)."'";
        $ddl->statement("CREATE TABLE `{$schema}`.`migrations` (id int unsigned auto_increment primary key, migration varchar(255))".$comment);
        $ddl->statement("CREATE TABLE `{$schema}`.`users` (id bigint unsigned auto_increment primary key, email varchar(255))");
        if ($email !== null) {
            $ddl->insert("insert into `{$schema}`.`users` (email) values (?)", [$email]);
        }
    }

    private function schemaExists(string $schema): bool
    {
        return $this->ddl()->selectOne('select 1 as present from information_schema.schemata where schema_name = ?', [$schema]) !== null;
    }

    private function ddl(): Connection
    {
        return DB::connection('tenant_harness_ddl');
    }

    private function makeItem(string $code, string $name, string $stock): Item
    {
        return Item::query()->create([
            'code' => $code,
            'name' => $name,
            'category' => 'coffee_beans',
            'cost_price' => '100.000',
            'selling_price' => '150.000',
            'current_stock' => $stock,
            'min_stock_level' => '0.000',
            'is_active' => true,
        ]);
    }
}
