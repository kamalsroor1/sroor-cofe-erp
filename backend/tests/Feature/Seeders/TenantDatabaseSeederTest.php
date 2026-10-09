<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TenantSampleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\MigratingDatabase;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TenantTestCase;

/**
 * F2: `php artisan tenants:seed` uses config('tenancy.seeder_parameters.--class').
 * That class runs INSIDE every tenant DB, so it must be tenant-safe: permissions and
 * tenant roles only — no users, no super_admin role, no super_admin.* permissions,
 * no plans, no sample tenant. It must never be the central DatabaseSeeder.
 *
 * QA-4: the seeder now runs through the real `tenants:seed` command against a harness
 * tenant's own database (its permission matrix wiped first, so the seeder has to rebuild
 * it), and a second tenant proves `--tenants=` seeds only the tenant it names.
 */
final class TenantDatabaseSeederTest extends TenantTestCase
{
    private Tenant $tenant;

    private Tenant $other;

    protected function setUp(): void
    {
        parent::setUp();

        // Harness tenants first: they need the real provisioning events.
        $this->tenant = $this->createTenant();
        $this->other = $this->createTenant();

        // Safety net: never let a seeder provision a tenant database on disk.
        Event::fake([
            TenantCreated::class,
            CreatingDatabase::class,
            DatabaseCreated::class,
            MigratingDatabase::class,
            DatabaseMigrated::class,
        ]);
        $this->app->instance(TenantSampleSeeder::class, new class extends TenantSampleSeeder
        {
            public function run(): void {}
        });
    }

    private function configuredSeederClass(): string
    {
        $class = config('tenancy.seeder_parameters.--class');
        $this->assertIsString($class);

        return $class;
    }

    /** Run the production command for exactly one tenant. */
    private function tenantsSeed(Tenant $tenant): void
    {
        $this->artisan('tenants:seed', ['--tenants' => [(string) $tenant->getTenantKey()], '--force' => true])
            ->assertSuccessful();
        $this->endTenancy();
    }

    /** Remove the tenant's whole permission matrix, so the seeder must recreate it. */
    private function wipePermissionMatrix(Tenant $tenant): void
    {
        $this->inTenant($tenant, function (): void {
            DB::table('role_has_permissions')->delete();
            DB::table('model_has_permissions')->delete();
            DB::table('model_has_roles')->delete();
            DB::table('roles')->delete();
            DB::table('permissions')->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    /** @return array<string, int> */
    private function counts(Tenant $tenant): array
    {
        return $this->inTenant($tenant, fn (): array => [
            'users' => DB::table('users')->count(),
            'roles' => DB::table('roles')->count(),
            'permissions' => DB::table('permissions')->count(),
            'role_has_permissions' => DB::table('role_has_permissions')->count(),
            'model_has_roles' => DB::table('model_has_roles')->count(),
        ]);
    }

    public function test_tenancy_seeder_parameter_points_to_an_existing_tenant_safe_class(): void
    {
        $class = ltrim($this->configuredSeederClass(), '\\');

        $this->assertNotSame('DatabaseSeeder', $class);
        $this->assertNotSame(DatabaseSeeder::class, $class);
        $this->assertStringStartsWith('Database\\Seeders\\', $class, 'Use the fully qualified class name.');
        $this->assertTrue(class_exists($class), 'Configured tenant seeder class does not exist.');
        $this->assertFalse(is_a($class, DatabaseSeeder::class, true), 'Tenant seeder must not extend the central DatabaseSeeder.');
    }

    public function test_tenant_seeder_creates_permissions_and_admin_role_but_no_platform_identity(): void
    {
        $this->wipePermissionMatrix($this->tenant);
        $usersBefore = $this->inTenant($this->tenant, fn (): int => User::withTrashed()->count());
        $centralTenantsBefore = Tenant::query()->count();
        $this->assertSame(0, $this->counts($this->tenant)['permissions']);

        $this->tenantsSeed($this->tenant);

        $this->inTenant($this->tenant, function () use ($usersBefore): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->assertGreaterThan(0, Permission::count(), 'Tenant seeder must create the ERP permissions.');
            $this->assertSame($usersBefore, User::withTrashed()->count(), 'Tenant seeder must not create users.');
            $this->assertDatabaseMissing('roles', ['name' => 'super_admin']);
            $this->assertSame(0, Permission::where('name', 'like', 'super_admin.%')->count());

            $admin = Role::findByName('admin');
            $expected = Permission::where('name', 'not like', 'super_admin.%')->orderBy('name')->pluck('name')->all();
            $actual = $admin->permissions()->orderBy('name')->pluck('name')->all();
            $this->assertSame($expected, $actual, 'Tenant admin must hold every non-super_admin permission.');
        });

        // No central SaaS data either.
        $this->assertSame($centralTenantsBefore, Tenant::query()->count(), 'Tenant seeder must not provision tenants.');
    }

    public function test_tenant_seeder_is_idempotent(): void
    {
        $this->tenantsSeed($this->tenant);
        $first = $this->counts($this->tenant);

        $this->tenantsSeed($this->tenant);
        $second = $this->counts($this->tenant);

        $this->assertSame($first, $second);
        $this->assertSame(1, $second['users'], 'Only the harness admin exists; the seeder added nobody.');
    }

    public function test_seeding_one_tenant_never_touches_another_tenant(): void
    {
        $other = $this->other;
        $this->wipePermissionMatrix($this->tenant);
        $this->wipePermissionMatrix($other);

        $this->tenantsSeed($this->tenant);

        $this->assertGreaterThan(0, $this->counts($this->tenant)['permissions']);
        $this->assertSame(0, $this->counts($other)['permissions'], 'tenants:seed --tenants=A seeded tenant B.');
        $this->assertSame(0, $this->counts($other)['roles']);
    }
}
