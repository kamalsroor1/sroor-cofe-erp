<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\CentralPermission;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TestCase;

/**
 * P0-AUTH-3 contract: App\Support\PlatformSuperAdmin::check(mixed $user): bool
 * is the single source of truth for "is this a platform super admin".
 * Legacy branch (removed in IDEN-1.4, W2-B3): an App\Models\User holding the central
 * super_admin role.
 *
 * IDEN-1.2: PlatformSuperAdmin::can(mixed $user, CentralPermission|string): bool is true
 * only for an active App\Models\CentralUser holding that central-guard permission, in
 * central context; null, tenant users and anything else are false, never a TypeError.
 */
class PlatformSuperAdminTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCentralPlatformRoles;

    /** Former allowlist (values removed from repo); PlatformSuperAdmin is role-based, so any phone keeps these assertions meaningful. */
    private const FORMER_ALLOWLIST = ['01000000901', '01000000902'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCentralPlatformRoles();
    }

    private function makeUser(string $phone, string $email): User
    {
        return User::factory()->create([
            'phone' => $phone,
            'email' => $email,
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
    }

    public function test_returns_false_for_null(): void
    {
        $this->assertFalse(PlatformSuperAdmin::check(null));
    }

    public function test_returns_false_for_non_user_values(): void
    {
        $this->assertFalse(PlatformSuperAdmin::check(new \stdClass));
        $this->assertFalse(PlatformSuperAdmin::check(self::FORMER_ALLOWLIST[0]));
        $this->assertFalse(PlatformSuperAdmin::check((object) ['phone' => self::FORMER_ALLOWLIST[0]]));
    }

    public function test_returns_false_for_user_without_role_even_with_former_allowlisted_identity(): void
    {
        $this->assertFalse(PlatformSuperAdmin::check($this->makeUser(self::FORMER_ALLOWLIST[0], 'a@baraa-solutions.com')));
        $this->assertFalse(PlatformSuperAdmin::check($this->makeUser(self::FORMER_ALLOWLIST[1], 'superadmin@baraa-solutions.com')));
    }

    public function test_returns_false_for_tenant_admin_role(): void
    {
        $user = $this->makeUser('01000007048', 'tenant-admin@sroor.test');
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        $this->assertFalse(PlatformSuperAdmin::check($user));
    }

    public function test_returns_true_for_user_with_super_admin_role(): void
    {
        $user = $this->makeUser('01000007002', 'platform@sroor.test');
        $user->assignRole('super_admin');

        $this->assertTrue(PlatformSuperAdmin::check($user));
    }

    private function operator(?string $role, bool $active = true): CentralUser
    {
        $user = CentralUser::factory()->create(['is_active' => $active]);

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user->refresh();
    }

    public function test_can_returns_false_for_null_and_non_central_values(): void
    {
        foreach ([null, '', 'super_admin', 42, 1.5, true, [], new \stdClass] as $value) {
            $this->assertFalse(PlatformSuperAdmin::can($value, CentralPermission::TenantsView));
            $this->assertFalse(PlatformSuperAdmin::can($value, CentralPermission::TenantsView->value));
        }
    }

    public function test_can_returns_false_for_tenant_users_even_with_super_admin_role(): void
    {
        $legacy = $this->makeUser('01000007011', 'legacy-can@sroor.test');
        $legacy->assignRole('super_admin');
        $tenantAdmin = $this->makeUser('01000007012', 'tenant-admin-can@sroor.test');
        $tenantAdmin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        foreach (CentralPermission::cases() as $permission) {
            $this->assertFalse(PlatformSuperAdmin::can($legacy, $permission), $permission->value);
            $this->assertFalse(PlatformSuperAdmin::can($tenantAdmin, $permission), $permission->value);
        }
    }

    public function test_can_follows_the_central_role_matrix(): void
    {
        $superAdmin = $this->operator(CentralPermission::ROLE_SUPER_ADMIN);
        $support = $this->operator(CentralPermission::ROLE_SUPPORT);
        $nobody = $this->operator(null);

        foreach (CentralPermission::cases() as $permission) {
            $this->assertTrue(PlatformSuperAdmin::can($superAdmin, $permission), $permission->value);
            $this->assertTrue(PlatformSuperAdmin::can($superAdmin, $permission->value), $permission->value);
            $this->assertSame($permission->isReadOnly(), PlatformSuperAdmin::can($support, $permission), $permission->value);
            $this->assertFalse(PlatformSuperAdmin::can($nobody, $permission), $permission->value);
        }
    }

    public function test_can_returns_false_for_unknown_or_tenant_abilities(): void
    {
        $superAdmin = $this->operator(CentralPermission::ROLE_SUPER_ADMIN);

        foreach (['customers.manage', 'super_admin.access', 'super_admin.unknown', '', '*'] as $ability) {
            $this->assertFalse(PlatformSuperAdmin::can($superAdmin, $ability), $ability);
        }
    }

    public function test_can_and_check_return_false_for_inactive_operator(): void
    {
        $inactive = $this->operator(CentralPermission::ROLE_SUPER_ADMIN, active: false);

        $this->assertFalse(PlatformSuperAdmin::can($inactive, CentralPermission::TenantsView));
        $this->assertFalse(PlatformSuperAdmin::check($inactive));
    }

    public function test_check_is_true_only_for_central_super_admin_operator(): void
    {
        $this->assertTrue(PlatformSuperAdmin::check($this->operator(CentralPermission::ROLE_SUPER_ADMIN)));
        $this->assertFalse(PlatformSuperAdmin::check($this->operator(CentralPermission::ROLE_SUPPORT)));
        $this->assertFalse(PlatformSuperAdmin::check($this->operator(null)));
    }

    public function test_can_and_check_are_false_while_tenancy_is_initialized(): void
    {
        $operator = $this->operator(CentralPermission::ROLE_SUPER_ADMIN);
        $legacy = $this->makeUser('01000007013', 'legacy-tenancy@sroor.test');
        $legacy->assignRole('super_admin');

        // Stub tenant: no database is switched, only the initialized flag matters here.
        tenancy()->initialized = true;
        tenancy()->tenant = new Tenant(['id' => 'stub-tenant']);

        try {
            $this->assertFalse(PlatformSuperAdmin::can($operator, CentralPermission::TenantsView));
            $this->assertFalse(PlatformSuperAdmin::check($operator));
            $this->assertFalse(PlatformSuperAdmin::check($legacy));
        } finally {
            tenancy()->initialized = false;
            tenancy()->tenant = null;
        }
    }
}
