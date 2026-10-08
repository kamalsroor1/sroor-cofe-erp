<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\MigratingDatabase;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TestCase;

/**
 * F3a: tenants:audit-super-admin must only count model_has_roles rows whose model_type
 * is the User model. A super_admin role row attached to another morph (e.g. a Store)
 * that happens to share a user's id must neither be counted as a role holder nor pull
 * that user's phone into the "central super admin phones" match list.
 *
 * Central and tenant schemas share one sqlite :memory: DB in the suite, so tenancy
 * bootstrapping is disabled (TenancyInitialized/Ended faked) and $tenant->run() just
 * executes the closure on the same connection.
 */
final class AuditTenantSuperAdminRolesCommandTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT_ID = 'audit-tenant';

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            TenantCreated::class,
            CreatingDatabase::class,
            DatabaseCreated::class,
            MigratingDatabase::class,
            DatabaseMigrated::class,
            TenancyInitialized::class,
            TenancyEnded::class,
        ]);
        config(['tenancy.bootstrappers' => []]);

        $this->seed(PermissionsSeeder::class);

        $this->store = Store::create([
            'name' => 'الفرع الرئيسي',
            'code' => 'MAIN',
            'is_main' => true,
            'is_active' => true,
        ]);

        Tenant::create([
            'id' => self::TENANT_ID,
            'name' => 'مستأجر التدقيق',
            'slug' => self::TENANT_ID,
            'email' => 'audit@tenant.test',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy')) {
            tenancy()->tenant = null;
            tenancy()->initialized = false;
        }

        parent::tearDown();
    }

    private function superAdminRole(): Role
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }

    private function makeUser(string $phone, string $email): User
    {
        return User::factory()->create([
            'name' => 'مستخدم',
            'phone' => $phone,
            'email' => $email,
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
    }

    /**
     * @return array{tenant: string, role_exists: string, role_users: string, permissions: string, permission_roles: string, phone_matches: string}
     */
    private function auditRow(): array
    {
        $exit = Artisan::call('tenants:audit-super-admin', ['--tenant' => self::TENANT_ID]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);

        foreach (preg_split('/\R/u', $output) ?: [] as $line) {
            $cells = array_map('trim', explode('|', trim($line)));
            // A table row renders as "| c1 | c2 | ... |" => first/last cells empty.
            if (count($cells) === 8 && $cells[1] === self::TENANT_ID) {
                return [
                    'tenant' => $cells[1],
                    'role_exists' => $cells[2],
                    'role_users' => $cells[3],
                    'permissions' => $cells[4],
                    'permission_roles' => $cells[5],
                    'phone_matches' => $cells[6],
                ];
            }
        }

        $this->fail('Audit table row for the test tenant was not found in the command output.');
    }

    /** @return list<string> */
    private function matchedIds(string $cell): array
    {
        return $cell === '-' ? [] : array_map('trim', explode(',', $cell));
    }

    public function test_role_row_on_a_non_user_morph_is_not_counted_as_a_holder(): void
    {
        $role = $this->superAdminRole();
        $bystander = $this->makeUser('01000000031', 'bystander@tenant.test');

        // A super_admin row attached to a Store whose id equals the bystander's id.
        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => Store::class,
            'model_id' => $bystander->id,
        ]);

        $row = $this->auditRow();

        $this->assertSame('0', $row['role_users'], 'A non-User morph row must not be counted as a super_admin holder.');
    }

    public function test_role_row_on_a_non_user_morph_does_not_pull_a_user_into_phone_matches(): void
    {
        $role = $this->superAdminRole();
        $bystander = $this->makeUser('01000000037', 'bystander4@tenant.test');

        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => Store::class,
            'model_id' => $bystander->id,
        ]);

        $row = $this->auditRow();

        $this->assertNotContains('#'.$bystander->id, $this->matchedIds($row['phone_matches']), 'A non-User morph row pulled an unrelated user into the phone matches.');
        $this->assertSame('-', $row['phone_matches']);
    }

    public function test_real_user_holding_super_admin_is_still_counted_and_matched(): void
    {
        $this->superAdminRole();
        $holder = $this->makeUser('01000000032', 'holder@tenant.test');
        $holder->assignRole('super_admin');

        $other = $this->makeUser('01000000033', 'other@tenant.test');

        $row = $this->auditRow();

        $this->assertSame('1', $row['role_users']);
        $matches = $this->matchedIds($row['phone_matches']);
        $this->assertContains('#'.$holder->id, $matches);
        $this->assertNotContains('#'.$other->id, $matches);
    }

    public function test_mixed_rows_count_only_the_user_morph(): void
    {
        $role = $this->superAdminRole();
        $holder = $this->makeUser('01000000034', 'holder2@tenant.test');
        $holder->assignRole('super_admin');
        $bystander = $this->makeUser('01000000035', 'bystander2@tenant.test');

        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => Store::class,
            'model_id' => $bystander->id,
        ]);

        $row = $this->auditRow();

        $this->assertSame('1', $row['role_users']);
        $matches = $this->matchedIds($row['phone_matches']);
        $this->assertSame(['#'.$holder->id], $matches);
    }

    public function test_audit_remains_read_only_with_foreign_morph_rows(): void
    {
        $role = $this->superAdminRole();
        $bystander = $this->makeUser('01000000036', 'bystander3@tenant.test');
        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => Store::class,
            'model_id' => $bystander->id,
        ]);

        $before = DB::table('model_has_roles')->orderBy('model_id')->get()->map(fn ($r): array => (array) $r)->all();

        $this->auditRow();

        $after = DB::table('model_has_roles')->orderBy('model_id')->get()->map(fn ($r): array => (array) $r)->all();
        $this->assertSame($before, $after);
    }
}
