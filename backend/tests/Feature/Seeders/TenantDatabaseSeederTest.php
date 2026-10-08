<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TenantSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
use Tests\TestCase;

/**
 * F2: `php artisan tenants:seed` uses config('tenancy.seeder_parameters.--class').
 * That class runs INSIDE every tenant DB, so it must be tenant-safe: permissions and
 * tenant roles only — no users, no super_admin role, no super_admin.* permissions,
 * no plans, no sample tenant. It must never be the central DatabaseSeeder.
 */
final class TenantDatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Safety net while the config still points at the central DatabaseSeeder:
        // never let this test provision a real tenant database on disk.
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

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'users' => DB::table('users')->count(),
            'roles' => DB::table('roles')->count(),
            'permissions' => DB::table('permissions')->count(),
            'role_has_permissions' => DB::table('role_has_permissions')->count(),
            'model_has_roles' => DB::table('model_has_roles')->count(),
        ];
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
        $this->assertSame(0, User::withTrashed()->count());

        $this->seed($this->configuredSeederClass());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertGreaterThan(0, Permission::count(), 'Tenant seeder must create the ERP permissions.');
        $this->assertSame(0, User::withTrashed()->count(), 'Tenant seeder must not create users.');
        $this->assertDatabaseMissing('roles', ['name' => 'super_admin']);
        $this->assertSame(0, Permission::where('name', 'like', 'super_admin.%')->count());

        $admin = Role::findByName('admin');
        $expected = Permission::where('name', 'not like', 'super_admin.%')->orderBy('name')->pluck('name')->all();
        $actual = $admin->permissions()->orderBy('name')->pluck('name')->all();
        $this->assertSame($expected, $actual, 'Tenant admin must hold every non-super_admin permission.');

        // No central SaaS data either.
        $this->assertSame(0, DB::table('tenants')->count(), 'Tenant seeder must not provision tenants.');
    }

    public function test_tenant_seeder_is_idempotent(): void
    {
        $this->seed($this->configuredSeederClass());
        $first = $this->counts();

        $this->seed($this->configuredSeederClass());
        $second = $this->counts();

        $this->assertSame($first, $second);
        $this->assertSame(0, $second['users']);
    }
}
