<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\CentralPermission;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlatformSuperAdmin;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * P0-AUTH-3 contract: App\Support\PlatformSuperAdmin::check(mixed $user): bool
 * is the single source of truth for "is this a platform super admin".
 *
 * IDEN-1.4 / IDEN-1.8: only an active App\Models\CentralUser holding the central-guard
 * `super_admin` role is one. The Phase 0 branch (an App\Models\User holding a `super_admin`
 * role) is gone: such a row, in the central `users` table or in a tenant DB, is never a
 * platform operator.
 *
 * IDEN-1.2: PlatformSuperAdmin::can(mixed $user, CentralPermission|string): bool is true
 * only for an active App\Models\CentralUser holding that central-guard permission, in
 * central context; null, tenant users and anything else are false, never a TypeError.
 */
class PlatformSuperAdminTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    /** Former allowlist (values removed from repo); PlatformSuperAdmin is role-based, so any phone keeps these assertions meaningful. */
    private const FORMER_ALLOWLIST = ['01000000901', '01000000902'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCentralPlatformRoles();
    }

    /** A row of the CENTRAL `users` table (Phase 0 operator storage). */
    private function makeUser(string $phone, string $email): User
    {
        $this->endTenancy();

        $user = new User;
        $user->forceFill([
            'name' => 'مستخدم '.$phone,
            'phone' => $phone,
            'email' => $email,
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ])->save();

        return $user;
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
        $tenant = $this->createTenant();
        $admin = $this->tenantAdmin($tenant);

        $this->assertTrue($this->inTenant($tenant, static fn (): bool => $admin->hasRole('admin')), 'fixture: a real tenant admin');
        $this->assertFalse(PlatformSuperAdmin::check($admin));
        $this->assertFalse($this->inTenant($tenant, static fn (): bool => PlatformSuperAdmin::check($admin)));
    }

    /**
     * Was test_returns_true_for_user_with_super_admin_role (Phase 0 contract). IDEN-1.4 removed
     * the legacy branch on purpose, so the same fixture must now be refused.
     */
    public function test_legacy_users_table_super_admin_is_no_longer_a_platform_operator(): void
    {
        $legacy = $this->legacyUsersTableSuperAdmin();

        $this->assertTrue($legacy->hasRole('super_admin'), 'fixture: the legacy web-guard role is really held');
        $this->assertFalse(PlatformSuperAdmin::check($legacy));
    }

    public function test_tenant_user_holding_a_super_admin_role_in_its_tenant_db_is_not_a_platform_operator(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn (): Role => $this->webRole('super_admin'));
        $user = $this->createTenantUser($tenant, 'super_admin');
        $this->assertTrue($this->inTenant($tenant, static fn (): bool => $user->hasRole('super_admin')), 'fixture: the tenant row really holds it');

        $this->assertFalse(PlatformSuperAdmin::check($user));
        $this->assertFalse($this->inTenant($tenant, static fn (): bool => PlatformSuperAdmin::check($user)));
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
        $legacy = $this->legacyUsersTableSuperAdmin();
        $tenant = $this->createTenant();
        $tenantAdmin = $this->tenantAdmin($tenant);

        foreach (CentralPermission::cases() as $permission) {
            $this->assertFalse(PlatformSuperAdmin::can($legacy, $permission), $permission->value);
            $this->assertFalse(PlatformSuperAdmin::can($tenantAdmin, $permission), $permission->value);
        }
    }

    private function operator(?string $role, bool $active = true): CentralUser
    {
        return $this->centralOperator($role, ['is_active' => $active]);
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
        $legacy = $this->legacyUsersTableSuperAdmin();
        $tenant = $this->createTenant();

        // Sanity outside tenancy, so the in-tenant false below is caused by the context.
        $this->assertTrue(PlatformSuperAdmin::check($operator));

        $this->inTenant($tenant, function () use ($operator, $legacy): void {
            $this->assertFalse(PlatformSuperAdmin::can($operator, CentralPermission::TenantsView));
            $this->assertFalse(PlatformSuperAdmin::check($operator));
            $this->assertFalse(PlatformSuperAdmin::check($legacy));
        });

        // Stub tenant too: only the initialized flag matters (no tenant DB behind it).
        tenancy()->initialized = true;
        tenancy()->tenant = new Tenant(['id' => 'stub-tenant']);

        try {
            $this->assertFalse(PlatformSuperAdmin::can($operator, CentralPermission::TenantsView));
            $this->assertFalse(PlatformSuperAdmin::check($operator));
        } finally {
            tenancy()->initialized = false;
            tenancy()->tenant = null;
        }
    }
}
