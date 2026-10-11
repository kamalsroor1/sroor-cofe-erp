<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\LoginAction;
use App\DTOs\Auth\LoginDTO;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * Security audit (W2 lane 3I), HIGH: ApiLoginAction / LoginAction used to fall back to the
 * CENTRAL `users` table, accept the password of any row holding the `admin` role, then
 * firstOrCreate a tenant user with that phone, syncRoles([admin]) and sign it in: a master
 * key into every tenant. The fallback is gone:
 *  - a central `users` admin gets the uniform tenant 422 (`auth.failed`) and no token;
 *  - no tenant user is created for it;
 *  - an existing tenant user sharing its phone is neither promoted nor re-passworded;
 *  - the web LoginAction refuses it too.
 */
final class CentralAdminTenantLoginFallbackRemovedTest extends TenantTestCase
{
    // Fixture credentials only (the `fixture` prefix marks them as fake for gitleaks).
    private const CENTRAL_PASSWORD = 'fixtureCentralAdminPassword';

    private const TENANT_PASSWORD = 'fixtureTenantCashierPassword';

    private function centralUsersAdmin(string $phone): User
    {
        $this->endTenancy();

        $role = Role::findOrCreate('admin', 'web');
        $user = new User;
        $user->forceFill([
            'name' => 'central admin '.Str::lower(Str::random(4)),
            'email' => 'central-admin-'.Str::lower(Str::random(8)).'@central.harness.test',
            'phone' => $phone,
            'password' => Hash::make(self::CENTRAL_PASSWORD),
            'is_active' => true,
        ])->save();
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /** Dummy, never-real phone in the harness 0100xxxxxxx range. */
    private function dummyPhone(): string
    {
        return '01009'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function tenantUserCount(Tenant $tenant): int
    {
        return $this->inTenant($tenant, static fn (): int => User::query()->count());
    }

    public function test_a_central_users_admin_cannot_log_into_a_tenant_api(): void
    {
        $tenant = $this->createTenant();
        $phone = $this->dummyPhone();
        $central = $this->centralUsersAdmin($phone);
        $before = $this->tenantUserCount($tenant);

        foreach ([$phone, (string) $central->email] as $login) {
            $this->postJson('/api/v1/auth/login', [
                'login' => $login,
                'password' => self::CENTRAL_PASSWORD,
            ], ['X-Tenant' => (string) $tenant->getTenantKey()])
                ->assertStatus(422)
                ->assertJsonPath('errors.login.0', __('auth.failed'))
                ->assertJsonMissingPath('data.token');
        }

        $this->assertSame($before, $this->tenantUserCount($tenant), 'No tenant user may be minted from a central row.');
        $this->assertFalse(
            $this->inTenant($tenant, static fn (): bool => User::query()->where('phone', $phone)->exists()),
        );
    }

    public function test_an_existing_tenant_user_with_the_same_phone_is_not_promoted(): void
    {
        $tenant = $this->createTenant();
        $phone = $this->dummyPhone();
        $cashier = $this->createTenantUser($tenant, 'cashier', [], [
            'phone' => $phone,
            'password' => Hash::make(self::TENANT_PASSWORD),
        ]);
        $hashBefore = $this->inTenant($tenant, static fn (): string => (string) User::query()->findOrFail($cashier->getKey())->getRawOriginal('password'));
        $this->centralUsersAdmin($phone);

        $this->postJson('/api/v1/auth/login', [
            'login' => $phone,
            'password' => self::CENTRAL_PASSWORD,
        ], ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', __('auth.failed'));

        // The web action's old fallback ran on ANY failed attempt and promoted the existing row.
        $this->assertFalse($this->inTenant($tenant, static fn (): bool => app(LoginAction::class)->execute(
            new LoginDTO(phone: $phone, password: self::CENTRAL_PASSWORD),
        )));

        $this->inTenant($tenant, function () use ($cashier, $hashBefore): void {
            $fresh = User::query()->findOrFail($cashier->getKey());
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->assertFalse($fresh->hasRole('admin'), 'The tenant user must not be promoted to admin.');
            $this->assertTrue($fresh->hasRole('cashier'));
            $this->assertSame($hashBefore, (string) $fresh->getRawOriginal('password'));
        });

        // The tenant user's own credentials keep working.
        $this->postJson('/api/v1/auth/login', [
            'login' => $phone,
            'password' => self::TENANT_PASSWORD,
        ], ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_the_web_login_action_refuses_a_central_users_admin(): void
    {
        $tenant = $this->createTenant();
        $phone = $this->dummyPhone();
        $this->centralUsersAdmin($phone);
        $before = $this->tenantUserCount($tenant);

        $result = $this->inTenant($tenant, static fn (): bool => app(LoginAction::class)->execute(
            new LoginDTO(phone: $phone, password: self::CENTRAL_PASSWORD),
        ));

        $this->assertFalse($result);
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertSame($before, $this->tenantUserCount($tenant));
    }
}
