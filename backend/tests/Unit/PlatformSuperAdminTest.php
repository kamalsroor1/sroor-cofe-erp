<?php

declare(strict_types=1);

namespace Tests\Unit;

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
 * True only for an App\Models\User holding the central super_admin role.
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
}
