<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\CentralPermission;
use App\Models\CentralUser;
use App\Models\User;
use Database\Seeders\CentralPermissionsSeeder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * IDEN-1.2: AppServiceProvider's Gate::before.
 *  - a CentralUser is denied every tenant ability, whatever its central role;
 *  - its granular CentralPermission abilities follow the central role matrix;
 *  - tenant users keep today's behaviour (store admin: ERP yes, platform no).
 */
final class GateBeforeCentralTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tenant-guard roles (`admin`) come from PermissionsSeeder; CentralPermissionsSeeder only seeds the central guard.
        $this->seed(PermissionsSeeder::class);
        $this->seed(CentralPermissionsSeeder::class);
    }

    private function operator(?string $role): CentralUser
    {
        $user = CentralUser::factory()->create();

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user->refresh();
    }

    private function webUser(string $role): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'web '.$role,
            'email' => $role.'-'.Str::lower(Str::random(8)).'@gate.test',
            'password' => 'not-used',
            'is_active' => true,
        ])->save();
        $user->assignRole($role);

        return $user;
    }

    /** @return array<string, array{string}> */
    public static function tenantAbilities(): array
    {
        return [
            'customers.manage' => ['customers.manage'],
            'invoices.create' => ['invoices.create'],
            'pos.access' => ['pos.access'],
            'settings.manage' => ['settings.manage'],
            'users.manage' => ['users.manage'],
            'legacy super_admin.access' => ['super_admin.access'],
            'unknown ability' => ['anything.at.all'],
        ];
    }

    /** @return array<string, array{?string}> */
    public static function centralRoles(): array
    {
        return [
            'super_admin' => [CentralPermission::ROLE_SUPER_ADMIN],
            'support' => [CentralPermission::ROLE_SUPPORT],
            'no role' => [null],
        ];
    }

    #[DataProvider('tenantAbilities')]
    public function test_central_super_admin_is_denied_every_tenant_ability(string $ability): void
    {
        $operator = $this->operator(CentralPermission::ROLE_SUPER_ADMIN);

        $this->assertFalse(Gate::forUser($operator)->allows($ability));
        $this->assertFalse($operator->can($ability));
    }

    #[DataProvider('centralRoles')]
    public function test_no_central_role_unlocks_tenant_abilities(?string $role): void
    {
        $operator = $this->operator($role);

        foreach (self::tenantAbilities() as [$ability]) {
            $this->assertFalse(Gate::forUser($operator)->allows($ability), $ability);
        }
    }

    public function test_central_abilities_follow_the_role_matrix(): void
    {
        $superAdmin = $this->operator(CentralPermission::ROLE_SUPER_ADMIN);
        $support = $this->operator(CentralPermission::ROLE_SUPPORT);
        $nobody = $this->operator(null);

        foreach (CentralPermission::cases() as $permission) {
            $this->assertTrue(Gate::forUser($superAdmin)->allows($permission->value), $permission->value);
            $this->assertSame($permission->isReadOnly(), Gate::forUser($support)->allows($permission->value), $permission->value);
            $this->assertFalse(Gate::forUser($nobody)->allows($permission->value), $permission->value);
        }
    }

    public function test_store_admin_keeps_erp_abilities_but_never_central_ones(): void
    {
        $admin = $this->webUser('admin');

        $this->assertTrue(Gate::forUser($admin)->allows('customers.manage'));

        foreach (CentralPermission::values() as $ability) {
            $this->assertFalse(Gate::forUser($admin)->allows($ability), $ability);
        }

        foreach (['viewTelescope', 'viewPulse', 'viewHorizon'] as $ability) {
            $this->assertFalse(Gate::forUser($admin)->allows($ability), $ability);
        }
    }

    /** IDEN-1.4: an App\Models\User holding the legacy `web` super_admin role gets no bypass any more. */
    public function test_legacy_web_super_admin_role_no_longer_passes(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $legacy = $this->webUser('super_admin');

        $this->assertFalse(Gate::forUser($legacy)->allows('super_admin.access'));
        $this->assertFalse(Gate::forUser($legacy)->allows('customers.manage'));

        foreach (CentralPermission::values() as $ability) {
            $this->assertFalse(Gate::forUser($legacy)->allows($ability), $ability);
        }
    }

    public function test_guest_is_denied(): void
    {
        $this->assertFalse(Gate::forUser(null)->allows(CentralPermission::TenantsView->value));
        $this->assertFalse(Gate::forUser(null)->allows('customers.manage'));
    }
}
