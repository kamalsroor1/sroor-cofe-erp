<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralPermission;
use App\Models\CentralUser;
use App\Models\User;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * Security audit (W2 lane 3I): `central:migrate-super-admins --execute` retires the legacy
 * central `users` row it moved: no role or direct permission is left on ANY guard (the old
 * `admin` role used to be a tenant master key), the password is replaced by an unknown
 * hash, the row is deactivated and its API tokens are revoked. The new CentralUser keeps
 * the copied legacy hash (must_reset_password flow, MustResetPasswordTest).
 */
final class MigrateLegacySuperAdminRetirementTest extends TenantTestCase
{
    private const COMMAND = 'central:migrate-super-admins';

    // Fake legacy password; the `fixture` prefix marks it as fake for gitleaks (.gitleaks.toml).
    private const LEGACY_PASSWORD = 'fixtureLegacyRetiredOperatorPassword';

    protected function setUp(): void
    {
        parent::setUp();

        $this->endTenancy();
        $this->seed(CentralPermissionsSeeder::class);
        Role::findOrCreate('super_admin', 'web');
        Role::findOrCreate('admin', 'web');
        Permission::findOrCreate('fixture.legacy.permission', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function legacyOperator(): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'legacy '.Str::lower(Str::random(4)),
            'email' => 'legacy-retire-'.Str::lower(Str::random(8)).'@central.test',
            'password' => Hash::make(self::LEGACY_PASSWORD),
            'is_active' => true,
        ])->save();
        $user->assignRole('super_admin', 'admin');
        $user->givePermissionTo('fixture.legacy.permission');
        $user->createToken('legacy-device');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /** @return array{roles: int, permissions: int, tokens: int} */
    private function leftovers(User $user): array
    {
        $morph = $user->getMorphClass();

        return [
            'roles' => DB::table('model_has_roles')->where('model_type', $morph)->where('model_id', $user->getKey())->count(),
            'permissions' => DB::table('model_has_permissions')->where('model_type', $morph)->where('model_id', $user->getKey())->count(),
            'tokens' => DB::table('personal_access_tokens')->where('tokenable_type', $morph)->where('tokenable_id', $user->getKey())->count(),
        ];
    }

    public function test_a_migrated_legacy_row_has_no_roles_no_permissions_no_tokens_and_is_disabled(): void
    {
        $legacy = $this->legacyOperator();
        $legacyHash = (string) DB::table('users')->where('id', $legacy->getKey())->value('password');
        $this->assertSame(['roles' => 2, 'permissions' => 1, 'tokens' => 1], $this->leftovers($legacy), 'fixture');

        $this->artisan(self::COMMAND, ['--email' => [$legacy->email], '--execute' => true])->assertSuccessful();

        $this->assertSame(['roles' => 0, 'permissions' => 0, 'tokens' => 0], $this->leftovers($legacy));

        $row = DB::table('users')->where('id', $legacy->getKey())->first();
        $this->assertNotNull($row);
        $this->assertFalse((bool) $row->is_active);
        $this->assertNotSame($legacyHash, $row->password);
        $this->assertFalse(Hash::check(self::LEGACY_PASSWORD, (string) $row->password), 'The legacy password must stop working.');

        // The central account still received the ORIGINAL hash (copied before the scramble).
        $central = CentralUser::query()->where('email', $legacy->email)->sole();
        $this->assertSame($legacyHash, $central->getRawOriginal('password'));
        $this->assertTrue($central->hasRole(CentralPermission::ROLE_SUPER_ADMIN, CentralPermission::GUARD));
    }

    public function test_a_legacy_row_that_was_not_selected_is_left_untouched(): void
    {
        $chosen = $this->legacyOperator();
        $other = $this->legacyOperator();

        $this->artisan(self::COMMAND, ['--email' => [$chosen->email], '--execute' => true])->assertSuccessful();

        $this->assertSame(['roles' => 2, 'permissions' => 1, 'tokens' => 1], $this->leftovers($other));
        $this->assertTrue((bool) DB::table('users')->where('id', $other->getKey())->value('is_active'));
    }

    public function test_a_dry_run_retires_nothing(): void
    {
        $legacy = $this->legacyOperator();

        $this->artisan(self::COMMAND, ['--email' => [$legacy->email]])->assertSuccessful();

        $this->assertSame(['roles' => 2, 'permissions' => 1, 'tokens' => 1], $this->leftovers($legacy));
        $this->assertTrue((bool) DB::table('users')->where('id', $legacy->getKey())->value('is_active'));
    }
}
