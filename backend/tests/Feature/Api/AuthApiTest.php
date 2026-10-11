<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

class AuthApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected Store $mainStore;

    protected Store $branchStore;

    protected function setUp(): void
    {
        parent::setUp();

        // QA-4: the tenant DB comes from the harness (PermissionsSeeder matrix, main store,
        // admin user). Fixtures and DB assertions run inside it; requests send X-Tenant.
        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        $this->mainStore = $this->adoptMainStore([
            'name' => 'الفرع الرئيسي',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $this->branchStore = Store::create([
            'name' => 'فرع المعادي',
            'code' => 'MAADI',
            'is_main' => false,
            'is_active' => true,
        ]);

        ActivityLog::query()->delete();
    }

    public function test_api_login_validation_fails_when_fields_are_missing(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors'])
            ->assertJson(['success' => false]);
    }

    public function test_api_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'phone' => '01012345678',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '01012345678',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors'])
            ->assertJson(['success' => false]);
    }

    public function test_api_login_fails_for_inactive_account(): void
    {
        User::factory()->create([
            'phone' => '01012345678',
            'password' => Hash::make('secret123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '01012345678',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_api_login_succeeds_with_phone_and_returns_sanctum_token_and_user_profile(): void
    {
        $role = Role::findByName('admin');

        $user = User::factory()->create([
            'name' => 'كمال سرور',
            'phone' => '01012345678',
            'email' => 'kamal@sroor.test',
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->mainStore->id,
            'theme_preference' => 'dark',
        ]);
        $user->assignRole($role);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '01012345678',
            'password' => 'secret123',
            'device_name' => 'test-spa',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'phone',
                        'email',
                        'roles',
                        'permissions',
                        'theme_preference',
                    ],
                    'store',
                    'stores',
                    'system',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'كمال سرور',
                        'phone' => '01012345678',
                    ],
                ],
            ]);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);
    }

    public function test_api_login_succeeds_with_email(): void
    {
        $user = User::factory()->create([
            'name' => 'أحمد محاسب',
            'phone' => '01000007012',
            'email' => 'ahmed@sroor.test',
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'ahmed@sroor.test',
            'password' => 'secret123',
            'device_name' => 'desktop-spa',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'أحمد محاسب');
    }

    public function test_api_login_rate_limiting_throttles_after_too_many_failures(): void
    {
        User::factory()->create([
            'phone' => '01000007006',
            'password' => Hash::make('correct-pass'),
            'is_active' => true,
        ]);

        // Attempt 6 failed logins
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'login' => '01000007006',
                'password' => 'wrong-pass',
            ]);
        }

        // 7th attempt should hit rate limit
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '01000007006',
            'password' => 'wrong-pass',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login']);
    }

    public function test_api_me_returns_unauthorized_without_token(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_api_me_succeeds_with_valid_bearer_token(): void
    {
        $role = Role::findByName('admin');
        $user = User::factory()->create([
            'name' => 'كمال سرور',
            'phone' => '01012345678',
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $user->assignRole($role);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => ['id', 'name', 'phone', 'roles', 'permissions'],
                    'store',
                    'stores',
                    'system',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'كمال سرور',
                    ],
                ],
            ]);
    }

    public function test_api_me_respects_x_store_id_header(): void
    {
        $user = User::factory()->create([
            'name' => 'كمال سرور',
            'phone' => '01012345678',
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $user->assignRole('admin');

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Store-Id' => (string) $this->branchStore->id,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.store.id', $this->branchStore->id)
            ->assertJsonPath('data.store.name', 'فرع المعادي');
    }

    public function test_api_logout_revokes_token_successfully(): void
    {
        $user = User::factory()->create([
            'name' => 'كمال سرور',
            'phone' => '01012345678',
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->mainStore->id,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * P0-AUTH-1: passwordless quick-login must not exist. Anyone who knows (or enumerates)
     * a phone number must not be able to obtain a Sanctum token without a password.
     */
    public function test_quick_login_endpoint_is_unavailable_when_flag_off(): void
    {
        User::factory()->create([
            'phone' => '01055555555',
            'name' => 'كاشير سريع',
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->mainStore->id,
        ]);

        $response = $this->postJson('/api/v1/auth/quick-login', [
            'login' => '01055555555',
            'device_name' => 'pos-terminal',
        ]);

        $this->assertNotSame(200, $response->status(), 'quick-login must not authenticate without a password');
        $this->assertContains($response->status(), [404, 405]);
        $this->assertNull($response->json('data.token'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'api_quick_login']);
    }

    /**
     * P0-AUTH-1: guests must not be able to enumerate workspace users (names + phones).
     */
    public function test_workspace_users_endpoint_is_not_public(): void
    {
        User::factory()->create([
            'phone' => '01077777777',
            'email' => 'enum-target@sroor.test',
            'name' => 'موظف مستهدف',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/auth/workspace-users');

        $this->assertNotSame(200, $response->status(), 'workspace-users must not be publicly listable');
        $this->assertStringNotContainsString('01077777777', $response->getContent());
        $this->assertStringNotContainsString('enum-target@sroor.test', $response->getContent());
    }

    /**
     * P0-AUTH-1: login must not persist the plaintext bearer token in users.api_token.
     */
    public function test_login_does_not_persist_plaintext_api_token(): void
    {
        $user = User::factory()->create([
            'phone' => '01000007013',
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $user->assignRole('cashier');

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '01000007013',
            'password' => 'secret123',
            'device_name' => 'test-spa',
        ]);

        $response->assertStatus(200);
        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        $this->assertNull($user->fresh()->api_token, 'plaintext token must not be stored on the users row');
        $this->assertDatabaseMissing('users', ['api_token' => $token]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id);
    }

    /**
     * P0-AUTH-1: after logout the same bearer token must be dead. While the plaintext
     * users.api_token copy exists, ApiTokenAuth's column fallback resurrects it.
     */
    public function test_logged_out_login_token_cannot_be_reused(): void
    {
        $user = User::factory()->create([
            'phone' => '01000007014',
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $user->assignRole('cashier');

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => '01000007014',
            'password' => 'secret123',
            'device_name' => 'test-spa',
        ])->assertStatus(200)->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Fresh application state so no auth user is cached between the two requests.
        $this->refreshApplicationAuth();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    /**
     * AUTH-1a: with QUICK_LOGIN_ENABLED unset (phpunit default) the picker list is not
     * registered and /auth/options advertises password login only.
     */
    public function test_quick_login_users_endpoint_is_unavailable_when_flag_off(): void
    {
        User::factory()->create([
            'phone' => '01000003000',
            'email' => 'picker-target@sroor.test',
            'name' => 'موظف القائمة',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/auth/quick-login/users');

        $response->assertStatus(404);
        $this->assertStringNotContainsString('01000003000', $response->getContent());
        $this->assertStringNotContainsString('موظف القائمة', $response->getContent());
    }

    public function test_auth_options_is_public_and_defaults_to_password_when_flag_off(): void
    {
        $this->getJson('/api/v1/auth/options')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quick_login', false)
            ->assertJsonPath('data.default_method', 'password');
    }

    /**
     * AUTH-2 / IDEN-4.6: the tenant-login limiter caps one IP (default 30 attempts/minute,
     * config rate_limits.tenant_login.per_ip_per_minute) even when every attempt uses a
     * different login (the failure counter in ApiLoginRequest is per login).
     */
    public function test_api_login_is_throttled_per_ip_across_different_logins(): void
    {
        $cap = (int) config('rate_limits.tenant_login.per_ip_per_minute');
        $this->assertSame(30, $cap);

        for ($i = 1; $i <= $cap; $i++) {
            $status = $this->postJson('/api/v1/auth/login', [
                'login' => "spray{$i}@sroor.test",
                'password' => 'wrong-pass',
            ])->status();

            $this->assertNotSame(429, $status, "attempt #{$i} must not be throttled yet");
        }

        $this->postJson('/api/v1/auth/login', [
            'login' => 'spray-over-cap@sroor.test',
            'password' => 'wrong-pass',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', __('auth.too_many_requests'));

        $this->assertNotSame('auth.too_many_requests', __('auth.too_many_requests'), 'auth.too_many_requests lang key must exist');
    }

    /**
     * AUTH-3: the token must never be accepted from the query string (it leaks into logs,
     * history and Referer headers).
     */
    public function test_api_token_query_string_is_not_accepted(): void
    {
        $user = $this->makeActiveUser('01000003001');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->getJson('/api/v1/auth/me?api_token='.urlencode($token))
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    /**
     * AUTH-3: the plaintext users.api_token column is no longer an authentication source.
     */
    public function test_plaintext_users_api_token_column_is_not_accepted(): void
    {
        $user = $this->makeActiveUser('01000003002');
        $user->forceFill(['api_token' => 'plain-xyz'])->save();

        $this->withHeader('Authorization', 'Bearer plain-xyz')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        $this->refreshApplicationAuth();

        $this->withHeader('X-API-TOKEN', 'plain-xyz')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    /**
     * AUTH-3: Sanctum expires_at is enforced and the dead token row is deleted.
     */
    public function test_expired_sanctum_token_is_rejected_and_deleted(): void
    {
        $user = $this->makeActiveUser('01000003003');
        $newToken = $user->createToken('expired', ['*'], now()->subMinute());
        $tokenId = $newToken->accessToken->getKey();

        $this->withHeader('Authorization', 'Bearer '.$newToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_sanctum_token_with_future_expiry_is_accepted(): void
    {
        $user = $this->makeActiveUser('01000003004');
        $token = $user->createToken('fresh', ['*'], now()->addHour())->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_x_api_token_header_with_sanctum_token_is_accepted(): void
    {
        $user = $this->makeActiveUser('01000003005');
        $token = $user->createToken('header-token')->plainTextToken;

        $this->withHeader('X-API-TOKEN', $token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id);
    }

    /**
     * QA-4 isolation: a second tenant, created after useTenantForTest() so tenant A stays
     * the test's context (createTenant() ends tenancy).
     */
    private function createOtherTenant(): Tenant
    {
        $other = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        return $other;
    }

    /** @return array<string, string> */
    private function otherTenantHeaders(Tenant $other, ?string $token = null): array
    {
        $headers = ['X-Tenant' => (string) $other->getTenantKey()];

        if ($token !== null) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return $headers;
    }

    public function test_tenant_a_credentials_are_rejected_on_tenant_b(): void
    {
        $tenantB = $this->createOtherTenant();
        $this->makeActiveUser('01000007020');

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '01000007020',
            'password' => 'secret123',
            'device_name' => 'test-spa',
        ], $this->otherTenantHeaders($tenantB));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['login']);
        $this->assertNull($response->json('data.token'));
        $this->assertSame(0, $this->inTenant($tenantB, fn (): int => PersonalAccessToken::query()->count()));
        $this->assertSame(0, PersonalAccessToken::query()->count(), 'a failed login on B must not mint a token in A');

        // Control: the same credentials are valid on their own tenant.
        $this->postJson('/api/v1/auth/login', [
            'login' => '01000007020',
            'password' => 'secret123',
            'device_name' => 'test-spa',
        ])->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_tenant_a_token_is_rejected_by_tenant_b_me(): void
    {
        $tenantB = $this->createOtherTenant();
        $user = $this->makeActiveUser('01000007021');
        $token = $user->createToken('tenant-a')->plainTextToken;

        $this->getJson('/api/v1/auth/me', $this->otherTenantHeaders($tenantB, $token))
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('data.user');

        // Control: the token is alive on its own tenant.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_tenant_a_token_cannot_log_out_on_tenant_b(): void
    {
        $tenantB = $this->createOtherTenant();
        $user = $this->makeActiveUser('01000007022');
        $token = $user->createToken('tenant-a')->plainTextToken;
        $tokensInB = $this->inTenant($tenantB, fn (): int => PersonalAccessToken::query()->count());

        $this->postJson('/api/v1/auth/logout', [], $this->otherTenantHeaders($tenantB, $token))
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->assertSame(1, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count(), "B's logout must not revoke A's token");
        $this->assertSame($tokensInB, $this->inTenant($tenantB, fn (): int => PersonalAccessToken::query()->count()));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);
    }

    /**
     * IDEN-4.6: ApiLoginRequest's failure counter (6 failures -> 422) is keyed by tenant +
     * login + IP, so locking a login string on tenant A leaves the same login on B usable.
     */
    public function test_failed_login_lockout_on_tenant_a_does_not_lock_the_same_login_on_tenant_b(): void
    {
        $tenantB = $this->createOtherTenant();
        $this->makeActiveUser('01000007023');
        $this->createTenantUser($tenantB, 'cashier', attributes: [
            'phone' => '01000007023',
            'password' => Hash::make('secret-b'),
        ]);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', ['login' => '01000007023', 'password' => 'wrong-pass']);
        }

        // A is locked even for the right password…
        $this->postJson('/api/v1/auth/login', ['login' => '01000007023', 'password' => 'secret123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['login']);

        // …while B serves the same login string normally.
        $this->postJson('/api/v1/auth/login', [
            'login' => '01000007023',
            'password' => 'secret-b',
            'device_name' => 'test-spa',
        ], $this->otherTenantHeaders($tenantB))
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * IDEN-4.6: the tenant-login per-login limiter (default 10/min -> 429) is keyed by tenant
     * + login + IP, so exhausting it on A does not 429 the same login on B.
     */
    public function test_login_429_throttle_on_tenant_a_does_not_throttle_tenant_b(): void
    {
        $tenantB = $this->createOtherTenant();
        $this->createTenantUser($tenantB, 'cashier', attributes: [
            'phone' => '01000007024',
            'password' => Hash::make('secret-b'),
        ]);

        $cap = (int) config('rate_limits.tenant_login.per_login_per_minute');
        $this->assertGreaterThan(0, $cap);
        $this->assertLessThan((int) config('rate_limits.tenant_login.per_ip_per_minute') - 1, $cap, 'test needs the per-login cap to answer before the per-IP cap');

        for ($i = 0; $i < $cap; $i++) {
            $this->postJson('/api/v1/auth/login', ['login' => '01000007024', 'password' => 'wrong-pass']);
        }

        $this->postJson('/api/v1/auth/login', ['login' => '01000007024', 'password' => 'wrong-pass'])
            ->assertStatus(429);

        $this->postJson('/api/v1/auth/login', [
            'login' => '01000007024',
            'password' => 'secret-b',
            'device_name' => 'test-spa',
        ], $this->otherTenantHeaders($tenantB))
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    private function makeActiveUser(string $phone): User
    {
        $user = User::factory()->create([
            'name' => 'مستخدم توكن',
            'phone' => $phone,
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function refreshApplicationAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        // flushHeaders() also drops the X-Tenant header set by useTenantForTest().
        $this->withHeaders(['X-Tenant' => (string) $this->tenant->getTenantKey()]);
    }
}
