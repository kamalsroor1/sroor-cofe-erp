<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Actions\Auth\ChangePasswordAction;
use App\Actions\Profile\UpdateProfileAction;
use App\DTOs\Auth\ChangePasswordDTO;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Models\ActivityLog;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\CurrentPasswordVerifier;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Telescope\Telescope;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * SELL-01: forced password change (EnsurePasswordChanged + POST /auth/change-password) and the
 * in-shop reset of a forgotten password (POST /users/{id}/reset-password).
 */
final class PasswordChangeAndResetApiTest extends TenantTestCase
{
    // Fake passwords; the `fixture` prefix marks them as fake for gitleaks (.gitleaks.toml).
    private const OLD_PASSWORD = 'fixtureOld123';

    private const NEW_PASSWORD = 'fixtureNew456';

    private const CHANGE = '/api/v1/auth/change-password';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->tenant = $this->createTenant();
    }

    // ------------------------------------------------------------------ helpers

    private function flaggedUser(string $role = 'cashier'): User
    {
        $user = $this->createTenantUser($this->tenant, $role, attributes: ['password' => Hash::make(self::OLD_PASSWORD)]);
        $this->setFlag((int) $user->id, true);

        return $user;
    }

    private function setFlag(int $userId, bool $flag): void
    {
        $this->inTenant($this->tenant, fn () => User::query()->findOrFail($userId)->forceFill(['must_change_password' => $flag])->save());
    }

    private function freshUser(int $userId): User
    {
        return $this->inTenant($this->tenant, fn (): User => User::query()->findOrFail($userId));
    }

    private function tokenCount(int $userId): int
    {
        return $this->inTenant($this->tenant, fn (): int => User::query()->findOrFail($userId)->tokens()->count());
    }

    private function login(string $login, string $password, ?Tenant $tenant = null): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', [
            'login' => $login,
            'password' => $password,
            'device_name' => 'spa',
        ], ['Accept' => 'application/json', 'X-Tenant' => (string) ($tenant ?? $this->tenant)->getTenantKey()]);
    }

    /** @return array<string, string> */
    private function bearer(string $token): array
    {
        $headers = $this->tenantHeaders($this->tenant);
        $headers['Authorization'] = 'Bearer '.$token;

        return $headers;
    }

    /** @return array<string, string> */
    private function changePayload(array $overrides = []): array
    {
        return array_merge([
            'current_password' => self::OLD_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ], $overrides);
    }

    private function makeRole(string $name, array $permissions): void
    {
        $this->inTenant($this->tenant, function () use ($name, $permissions): void {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    private function assertPasswordChangeRequired(TestResponse $response): void
    {
        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', EnsurePasswordChanged::ERROR_CODE)
            ->assertJsonPath('message', __('auth.password_change_required'));
    }

    // ------------------------------------------------------------------ forced change gate

    public function test_login_and_me_expose_the_flag_and_every_other_endpoint_is_blocked_until_changed(): void
    {
        $user = $this->flaggedUser();

        $login = $this->login((string) $user->phone, self::OLD_PASSWORD)
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonPath('data.user.must_change_password', true);
        $token = (string) $login->json('data.token');
        $headers = $this->bearer($token);

        // A second device signed in before the change: must die with it.
        $otherToken = (string) $this->login((string) $user->phone, self::OLD_PASSWORD)->json('data.token');

        // Allowed: who am I + the SPA bootstrap.
        $this->getJson('/api/v1/auth/me', $headers)->assertOk()
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonPath('data.user.must_change_password', true);
        $this->getJson('/api/v1/system/context', $headers)->assertOk();

        // Blocked: a representative read and write (and the profile endpoints).
        $this->assertPasswordChangeRequired($this->getJson('/api/v1/items', $headers));
        $this->assertPasswordChangeRequired($this->postJson('/api/v1/customers', ['name' => 'عميل'], $headers));
        $this->assertPasswordChangeRequired($this->getJson('/api/v1/profile', $headers));
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Customer::query()->count()));

        $this->postJson(self::CHANGE, $this->changePayload(), $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.must_change_password', false)
            ->assertJsonPath('data.user.must_change_password', false);

        $fresh = $this->freshUser((int) $user->id);
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));

        // The current token keeps working, everything is open again; the other token is revoked.
        $this->getJson('/api/v1/items', $headers)->assertOk();
        $this->getJson('/api/v1/auth/me', $this->bearer($otherToken))->assertStatus(401);
        $this->assertSame(1, $this->tokenCount((int) $user->id));
        $this->login((string) $user->phone, self::NEW_PASSWORD)->assertOk()->assertJsonPath('data.must_change_password', false);

        $log = $this->inTenant($this->tenant, fn (): string => (string) json_encode(ActivityLog::query()->where('module', 'auth')->where('action', 'password_changed')->sole()->toArray()));
        $this->assertStringNotContainsString(self::NEW_PASSWORD, $log);
        $this->assertStringNotContainsString(self::OLD_PASSWORD, $log);
    }

    public function test_logout_stays_open_to_a_flagged_user(): void
    {
        $user = $this->flaggedUser();
        $headers = $this->tenantHeaders($this->tenant, $user);

        $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();
        $this->assertSame(0, $this->tokenCount((int) $user->id));
    }

    public function test_an_unflagged_user_is_never_blocked(): void
    {
        $user = $this->createTenantUser($this->tenant, 'cashier');

        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($this->tenant, $user))
            ->assertOk()
            ->assertJsonPath('data.must_change_password', false);
        $this->getJson('/api/v1/items', $this->tenantHeaders($this->tenant, $user))->assertOk();
    }

    public function test_the_flag_of_a_user_in_another_tenant_never_blocks_this_tenant(): void
    {
        $other = $this->createTenant();
        $this->inTenant($other, fn () => User::query()->whereKey($this->tenantAdmin($other)->id)->update(['must_change_password' => true]));

        $this->getJson('/api/v1/items', $this->tenantHeaders($this->tenant))->assertOk();
        $this->assertPasswordChangeRequired($this->getJson('/api/v1/items', $this->tenantHeaders($other)));
    }

    // ------------------------------------------------------------------ change-password validation

    public function test_change_password_validation(): void
    {
        $user = $this->createTenantUser($this->tenant, 'cashier', attributes: ['password' => Hash::make(self::OLD_PASSWORD)]);
        $headers = $this->tenantHeaders($this->tenant, $user);

        $this->postJson(self::CHANGE, [], $headers)->assertStatus(422)
            ->assertJsonValidationErrors(['current_password', 'password']);

        // SEC-05 policy: at least 8 characters.
        $this->postJson(self::CHANGE, $this->changePayload(['password' => 'short7x', 'password_confirmation' => 'short7x']), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Same as the current one.
        $this->postJson(self::CHANGE, $this->changePayload(['password' => self::OLD_PASSWORD, 'password_confirmation' => self::OLD_PASSWORD]), $headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.password.0', __('auth.password_must_differ'));

        // Confirmation mismatch.
        $this->postJson(self::CHANGE, $this->changePayload(['password_confirmation' => 'fixtureOther789']), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Wrong current password.
        $this->postJson(self::CHANGE, $this->changePayload(['current_password' => 'fixtureWrong000']), $headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password.0', __('auth.current_password_incorrect'));

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->freshUser((int) $user->id)->password));
    }

    public function test_change_password_without_a_token_is_401(): void
    {
        $this->postJson(self::CHANGE, $this->changePayload(), $this->tenantGuestHeaders($this->tenant))->assertStatus(401);
    }

    public function test_wrong_current_passwords_lock_the_change_like_login(): void
    {
        $user = $this->createTenantUser($this->tenant, 'cashier', attributes: ['password' => Hash::make(self::OLD_PASSWORD)]);
        $headers = $this->tenantHeaders($this->tenant, $user);
        $max = (int) config('rate_limits.tenant_login.account_lockout_attempts', 5);

        for ($i = 0; $i < $max; $i++) {
            $this->postJson(self::CHANGE, $this->changePayload(['current_password' => 'fixtureWrong000']), $headers)->assertStatus(422);
        }

        // Locked: even the right current password is refused for now.
        $this->postJson(self::CHANGE, $this->changePayload(), $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->freshUser((int) $user->id)->password));
    }

    // ------------------------------------------------------------------ in-shop reset

    public function test_admin_reset_returns_a_temporary_password_once_flags_the_user_and_revokes_tokens(): void
    {
        $target = $this->createTenantUser($this->tenant, 'cashier', attributes: ['password' => Hash::make(self::OLD_PASSWORD)]);
        $targetHeaders = $this->tenantHeaders($this->tenant, $target);
        $this->tenantToken($this->tenant, $target);
        $this->assertSame(2, $this->tokenCount((int) $target->id));

        $response = $this->postJson("/api/v1/users/{$target->id}/reset-password", [], $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('users.password_reset_success'))
            ->assertJsonPath('data.user_id', (int) $target->id)
            ->assertJsonPath('data.must_change_password', true);
        $temporary = (string) $response->json('data.temporary_password');
        $this->assertGreaterThanOrEqual(8, strlen($temporary));

        $fresh = $this->freshUser((int) $target->id);
        $this->assertTrue($fresh->must_change_password);
        $this->assertTrue(Hash::check($temporary, $fresh->password));
        $this->assertNotSame($temporary, $fresh->password);
        $this->assertSame(0, $this->tokenCount((int) $target->id));
        $this->getJson('/api/v1/auth/me', $targetHeaders)->assertStatus(401);

        // Never readable again.
        $this->getJson("/api/v1/users/{$target->id}", $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonMissingPath('data.temporary_password');
        $this->assertStringNotContainsString($temporary, (string) $this->getJson('/api/v1/users', $this->tenantHeaders($this->tenant))->getContent());

        $log = $this->inTenant($this->tenant, fn (): string => (string) json_encode(ActivityLog::query()
            ->where('module', 'users')->where('action', 'password_reset')->where('subject_id', $target->id)->sole()->toArray()));
        $this->assertStringNotContainsString($temporary, $log);

        // The user signs in with it and is sent straight to the change screen.
        $this->login((string) $target->phone, $temporary)->assertOk()->assertJsonPath('data.must_change_password', true);
    }

    public function test_a_second_reset_issues_a_different_password(): void
    {
        $target = $this->createTenantUser($this->tenant, 'cashier');
        $uri = "/api/v1/users/{$target->id}/reset-password";

        $first = (string) $this->postJson($uri, [], $this->tenantHeaders($this->tenant))->assertOk()->json('data.temporary_password');
        $second = (string) $this->postJson($uri, [], $this->tenantHeaders($this->tenant))->assertOk()->json('data.temporary_password');

        $this->assertNotSame($first, $second);
        $this->assertTrue(Hash::check($second, $this->freshUser((int) $target->id)->password));
    }

    public function test_reset_is_403_for_a_cashier_and_401_without_a_token(): void
    {
        $target = $this->createTenantUser($this->tenant, 'cashier', attributes: ['password' => Hash::make(self::OLD_PASSWORD)]);
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $this->postJson("/api/v1/users/{$target->id}/reset-password", [], $this->tenantHeaders($this->tenant, $cashier))->assertStatus(403);
        $this->postJson("/api/v1/users/{$target->id}/reset-password", [], $this->tenantGuestHeaders($this->tenant))->assertStatus(401);

        $fresh = $this->freshUser((int) $target->id);
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $fresh->password));
    }

    public function test_a_delegate_failing_the_takeover_guard_or_touching_an_admin_is_403(): void
    {
        $this->makeRole('sell01_user_manager', ['roles.manage']);
        $this->makeRole('sell01_billing_clerk', ['billing.manage']);
        $delegate = $this->createTenantUser($this->tenant, 'sell01_user_manager');
        $clerk = $this->createTenantUser($this->tenant, 'sell01_billing_clerk', attributes: ['password' => Hash::make(self::OLD_PASSWORD)]);
        $headers = $this->tenantHeaders($this->tenant, $delegate);

        $this->postJson("/api/v1/users/{$clerk->id}/reset-password", [], $headers)
            ->assertStatus(403)
            ->assertJsonPath('message', __('users.cannot_change_credentials_of_more_privileged_user'));

        $admin = $this->tenantAdmin($this->tenant);
        $this->postJson("/api/v1/users/{$admin->id}/reset-password", [], $headers)
            ->assertStatus(403)
            ->assertJsonPath('message', __('auth.cannot_modify_admin_account'));

        $this->assertFalse($this->freshUser((int) $clerk->id)->must_change_password);
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->freshUser((int) $clerk->id)->password));
        $this->assertFalse($this->freshUser((int) $admin->id)->must_change_password);
        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => ActivityLog::query()->where('action', 'password_reset')->exists()));

        // A user holding nothing the delegate lacks can be reset by the delegate.
        $peer = $this->createTenantUser($this->tenant);
        $this->postJson("/api/v1/users/{$peer->id}/reset-password", [], $headers)->assertOk();
        $this->assertTrue($this->freshUser((int) $peer->id)->must_change_password);
    }

    public function test_resetting_ones_own_account_is_refused(): void
    {
        $admin = $this->tenantAdmin($this->tenant);

        $this->postJson("/api/v1/users/{$admin->id}/reset-password", [], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user']);
        $this->assertFalse($this->freshUser((int) $admin->id)->must_change_password);
    }

    public function test_a_user_id_from_another_tenant_is_404_and_untouched(): void
    {
        $other = $this->createTenant();
        $foreign = $this->createTenantUser($other, 'cashier', attributes: ['password' => Hash::make(self::OLD_PASSWORD)]);

        // The id only exists in the other tenant's database.
        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => User::query()->whereKey($foreign->id)->exists()));

        $this->postJson("/api/v1/users/{$foreign->id}/reset-password", [], $this->tenantHeaders($this->tenant))->assertStatus(404);

        $fresh = $this->inTenant($other, fn (): User => User::query()->findOrFail($foreign->id));
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $fresh->password));
    }

    public function test_an_admin_setting_another_users_password_on_edit_also_forces_a_change(): void
    {
        $target = $this->createTenantUser($this->tenant, 'cashier');

        $this->putJson("/api/v1/users/{$target->id}", [
            'name' => $target->name,
            'phone' => $target->phone,
            'email' => $target->email,
            'role' => 'cashier',
            'default_store_id' => $target->default_store_id,
            'is_active' => true,
            'password' => 'fixtureTyped123',
        ], $this->tenantHeaders($this->tenant))->assertOk()->assertJsonPath('data.must_change_password', true);

        $this->assertTrue($this->freshUser((int) $target->id)->must_change_password);
    }

    public function test_telescope_never_records_temporary_or_typed_passwords(): void
    {
        // RequestWatcher stores request params AND JSON response bodies (non-local environments).
        foreach (['data.temporary_password', 'owner_credentials.temporary_password'] as $path) {
            $this->assertContains($path, Telescope::$hiddenResponseParameters);
        }
        foreach (['password', 'password_confirmation', 'current_password', 'owner_password'] as $field) {
            $this->assertContains($field, Telescope::$hiddenRequestParameters);
        }
    }

    // ------------------------------------------------------------------ SELL-01 security review

    /**
     * Point the rate limiter at a DATABASE cache store on the TENANT connection (the cache table
     * of the tenant DB), the setup where a hit written inside a rolled-back tenant transaction is
     * lost. Must run inside the tenant context.
     */
    private function useTenantDatabaseLimiter(): void
    {
        config([
            'cache.stores.sell01_tenant_db' => ['driver' => 'database', 'connection' => 'tenant', 'table' => 'cache', 'lock_connection' => 'tenant'],
            'cache.limiter' => 'sell01_tenant_db',
        ]);
        app('cache')->forgetDriver('sell01_tenant_db');
        app()->forgetInstance(CacheRateLimiter::class);
        Facade::clearResolvedInstance(CacheRateLimiter::class);
    }

    private function restoreLimiter(): void
    {
        config(['cache.limiter' => null]);
        app()->forgetInstance(CacheRateLimiter::class);
        Facade::clearResolvedInstance(CacheRateLimiter::class);
    }

    public function test_a_wrong_current_password_is_counted_even_when_the_limiter_lives_on_the_tenant_connection(): void
    {
        $user = $this->createTenantUser($this->tenant, 'cashier', attributes: ['password' => Hash::make(self::OLD_PASSWORD)]);

        try {
            $this->inTenant($this->tenant, function () use ($user): void {
                $this->useTenantDatabaseLimiter();
                $fresh = User::query()->findOrFail($user->id);

                // Control: on this store a hit written inside a rolled-back tenant transaction is
                // lost. This is what made the lockout never trip before the fix.
                try {
                    DB::transaction(function (): void {
                        RateLimiter::hit('sell01-control', 60);
                        throw new \RuntimeException('rollback');
                    });
                } catch (\RuntimeException) {
                }
                $this->assertSame(0, RateLimiter::attempts('sell01-control'));

                $key = app(CurrentPasswordVerifier::class)->key($fresh);

                foreach ([
                    fn () => app(ChangePasswordAction::class)->execute(new ChangePasswordDTO('fixtureWrong000', self::NEW_PASSWORD), $fresh),
                    fn () => app(UpdateProfileAction::class)->execute($fresh, [
                        'name' => $fresh->name, 'phone' => $fresh->phone, 'theme_preference' => 'dark',
                        'current_password' => 'fixtureWrong000', 'new_password' => self::NEW_PASSWORD,
                    ]),
                ] as $i => $attempt) {
                    try {
                        $attempt();
                        $this->fail('A wrong current password must be refused.');
                    } catch (ValidationException $e) {
                        $this->assertArrayHasKey('current_password', $e->errors());
                    }
                    // The hit survives the failed request, in the tenant DB cache table.
                    $this->assertSame($i + 1, RateLimiter::attempts($key));
                }
                $this->assertTrue(DB::connection('tenant')->table('cache')->where('key', 'like', '%profile-current-password%')->exists());
                $this->assertTrue(Hash::check(self::OLD_PASSWORD, User::query()->findOrFail($user->id)->password));
            });
        } finally {
            $this->restoreLimiter();
        }
    }

    public function test_the_lockout_is_shared_between_change_password_and_profile(): void
    {
        $user = $this->createTenantUser($this->tenant, 'cashier', attributes: ['password' => Hash::make(self::OLD_PASSWORD)]);
        $headers = $this->tenantHeaders($this->tenant, $user);
        $max = (int) config('rate_limits.tenant_login.account_lockout_attempts', 5);
        $profile = fn (string $current): TestResponse => $this->putJson('/api/v1/profile', [
            'name' => $user->name, 'phone' => $user->phone, 'theme_preference' => 'dark',
            'current_password' => $current, 'new_password' => self::NEW_PASSWORD, 'new_password_confirmation' => self::NEW_PASSWORD,
        ], $headers);

        for ($i = 0; $i < $max - 1; $i++) {
            $this->postJson(self::CHANGE, $this->changePayload(['current_password' => 'fixtureWrong000']), $headers)
                ->assertStatus(422)->assertJsonPath('errors.current_password.0', __('auth.current_password_incorrect'));
        }
        // The last allowed failure is spent on the other endpoint.
        $profile('fixtureWrong000')->assertStatus(422)->assertJsonPath('errors.current_password.0', __('auth.current_password_incorrect'));

        // Both endpoints are locked now, even with the right current password.
        foreach ([$profile(self::OLD_PASSWORD), $this->postJson(self::CHANGE, $this->changePayload(), $headers)] as $response) {
            $response->assertStatus(422)->assertJsonValidationErrors(['current_password']);
            $this->assertNotSame(__('auth.current_password_incorrect'), $response->json('errors.current_password.0'));
        }
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->freshUser((int) $user->id)->password));
    }

    public function test_profile_password_change_on_the_locked_row_works_and_ends_other_sessions(): void
    {
        $user = $this->createTenantUser($this->tenant, 'cashier', attributes: ['password' => Hash::make(self::OLD_PASSWORD)]);
        $headers = $this->tenantHeaders($this->tenant, $user);
        $this->tenantToken($this->tenant, $user);

        $this->putJson('/api/v1/profile', [
            'name' => 'اسم جديد', 'phone' => $user->phone, 'theme_preference' => 'light',
            'current_password' => self::OLD_PASSWORD, 'new_password' => self::NEW_PASSWORD, 'new_password_confirmation' => self::NEW_PASSWORD,
        ], $headers)->assertOk()->assertJsonPath('data.name', 'اسم جديد');

        $fresh = $this->freshUser((int) $user->id);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertSame('light', $fresh->theme_preference);
        $this->assertSame(1, $this->tokenCount((int) $user->id));
    }

    public function test_profile_update_with_a_new_password_is_blocked_while_flagged(): void
    {
        $user = $this->flaggedUser();

        $this->assertPasswordChangeRequired($this->putJson('/api/v1/profile', [
            'name' => $user->name, 'phone' => $user->phone, 'theme_preference' => 'dark',
            'current_password' => self::OLD_PASSWORD, 'new_password' => self::NEW_PASSWORD, 'new_password_confirmation' => self::NEW_PASSWORD,
        ], $this->tenantHeaders($this->tenant, $user)));

        $fresh = $this->freshUser((int) $user->id);
        $this->assertTrue($fresh->must_change_password);
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $fresh->password));
    }

    public function test_a_user_created_by_an_admin_must_change_the_password(): void
    {
        $response = $this->postJson('/api/v1/users', [
            'name' => 'كاشير جديد',
            'phone' => '01000007701',
            'email' => 'new7701@sell01.test',
            'password' => 'fixtureTyped123',
            'role' => 'cashier',
            'default_store_id' => $this->tenantStore($this->tenant)->id,
            'is_active' => true,
        ], $this->tenantHeaders($this->tenant))->assertCreated()->assertJsonPath('data.must_change_password', true);

        $this->assertTrue($this->freshUser((int) $response->json('data.id'))->must_change_password);
        $this->login('01000007701', 'fixtureTyped123')->assertOk()->assertJsonPath('data.must_change_password', true);
    }

    public function test_a_flagged_session_gets_no_business_telemetry_from_me_or_context(): void
    {
        $user = $this->flaggedUser('admin');
        $headers = $this->tenantHeaders($this->tenant, $user);
        $store = $this->tenantStore($this->tenant);
        $this->inTenant($this->tenant, fn () => CashShift::query()->forceCreate([
            'store_id' => $store->id, 'user_id' => $user->id, 'shift_number' => 1, 'status' => 'open', 'opened_at' => now(), 'opening_cash_balance' => '750.000',
        ]));

        $this->getJson('/api/v1/auth/me', $headers)->assertOk()
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonPath('data.active_shift', null)
            ->assertJsonPath('data.user.id', (int) $user->id)
            ->assertJsonStructure(['data' => ['user', 'store', 'stores', 'system', 'subscription']]);

        $this->getJson('/api/v1/system/context', $headers)->assertOk()
            ->assertJsonPath('data.active_shift', null)
            ->assertJsonPath('data.notifications', [])
            ->assertJsonPath('data.setup_progress', null)
            ->assertJsonPath('data.auth.user.must_change_password', true)
            ->assertJsonStructure(['data' => ['auth', 'tenant', 'active_store', 'stores', 'system', 'branding', 'entitlements', 'locale', 'translations_hash']]);

        // Same admin once the password is changed: the shift is back.
        $this->setFlag((int) $user->id, false);
        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($this->tenant, $user))->assertOk()
            ->assertJsonPath('data.active_shift.opening_cash_balance', '750.000');
        $this->assertNotNull($this->getJson('/api/v1/system/context', $this->tenantHeaders($this->tenant, $user))->json('data.active_shift'));
    }

    public function test_responses_carrying_a_temporary_password_are_not_cacheable(): void
    {
        $target = $this->createTenantUser($this->tenant, 'cashier');

        $response = $this->postJson("/api/v1/users/{$target->id}/reset-password", [], $this->tenantHeaders($this->tenant))->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_session_guard_pages_are_closed_to_a_flagged_user_except_the_spa_shell(): void
    {
        // Fresh from the DB, as the session guard would load it.
        $flagged = $this->freshUser((int) $this->flaggedUser('admin')->id);
        $admin = $this->tenantAdmin($this->tenant);
        $export = $this->tenantUrl($this->tenant, '/activity-logs/export-csv');

        $this->actingAs($flagged, 'web')->get($export)->assertForbidden();
        $this->actingAs($flagged, 'web')->get($this->tenantUrl($this->tenant, '/'))->assertOk();
        $this->actingAs($admin, 'web')->get($export)->assertOk();
    }
}
