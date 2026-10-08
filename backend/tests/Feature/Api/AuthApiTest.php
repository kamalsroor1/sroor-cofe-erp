<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected Store $mainStore;

    protected Store $branchStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->mainStore = Store::create([
            'name' => 'الفرع الرئيسي',
            'code' => 'MAIN',
            'is_main' => true,
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
    }
}
