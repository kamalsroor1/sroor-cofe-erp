<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\MigratingDatabase;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TestCase;

/**
 * AUTH-1a: testing-only quick login (passwordless, pick-a-user).
 *
 * The routes are registered at boot only when QUICK_LOGIN_ENABLED=true and the app is not in
 * production, so this class flips the env var BEFORE the application boots. A runtime gate
 * (EnsureQuickLoginAllowed / QuickLoginGate) must still answer 404 when the config flag is off,
 * when the environment is production, or when no tenant context is initialised.
 *
 * Tenancy is switched by hand (no bootstrappers) because central and tenant schemas share one
 * sqlite :memory: database in the suite. Requests that do not need tenant context (token use
 * on /auth/me, /users) run with tenancy off so they never reach tenancy()->central().
 *
 * The default-env case (flag never set => routes not registered) is pinned in AuthApiTest.
 */
class QuickLoginApiTest extends TestCase
{
    use RefreshDatabase;

    private const ENV_KEY = 'QUICK_LOGIN_ENABLED';

    private Store $store;

    private Tenant $tenant;

    public function createApplication(): Application
    {
        // Laravel's env repository is static and immutable: it never overwrites a variable
        // set outside .env, EXCEPT one it loaded from .env itself in an earlier test. When
        // .env defines QUICK_LOGIN_ENABLED (CI copies .env.example, which has it empty), a
        // previous test already loaded that empty value and the next boot would overwrite
        // `true` with it. Clearing through the repository forgets that ownership first.
        Env::getRepository()->clear(self::ENV_KEY);

        putenv(self::ENV_KEY.'=true');
        $_ENV[self::ENV_KEY] = 'true';
        $_SERVER[self::ENV_KEY] = 'true';

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            TenantCreated::class,
            CreatingDatabase::class,
            DatabaseCreated::class,
            MigratingDatabase::class,
            DatabaseMigrated::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->store = Store::create([
            'name' => 'الفرع الرئيسي',
            'code' => 'MAIN',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->tenant = Tenant::create([
            'id' => 'quick-cafe',
            'name' => 'كافيه التجربة',
            'slug' => 'quick-cafe',
            'email' => 'quick@cafe.test',
            'status' => 'active',
        ]);

        ActivityLog::query()->delete();
    }

    protected function tearDown(): void
    {
        $this->leaveTenant();

        putenv(self::ENV_KEY);
        unset($_ENV[self::ENV_KEY], $_SERVER[self::ENV_KEY]);

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    private function enterTenant(): void
    {
        tenancy()->tenant = $this->tenant;
        tenancy()->initialized = true;
    }

    private function leaveTenant(): void
    {
        if (function_exists('tenancy')) {
            tenancy()->tenant = null;
            tenancy()->initialized = false;
        }
    }

    private function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
    }

    private function makeUser(string $name, string $phone, string $email, bool $active = true, ?string $role = 'cashier'): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password' => Hash::make('secret123'),
            'is_active' => $active,
            'default_store_id' => $this->store->id,
        ]);

        if ($role !== null) {
            $user->assignRole(Role::findOrCreate($role));
        }

        return $user;
    }

    private function ttlMinutes(): int
    {
        return (int) config('auth.quick_login.token_ttl_minutes', 480);
    }

    // ------------------------------------------------------------------
    // (a) config flag off at runtime => 404 everywhere, nothing issued
    // ------------------------------------------------------------------

    public function test_quick_login_returns_404_when_config_flag_is_off(): void
    {
        config(['auth.quick_login.enabled' => false]);
        $user = $this->makeUser('كاشير', '01000001001', 'c1@sroor.test');
        $this->enterTenant();

        $this->postJson('/api/v1/auth/quick-login', ['user_id' => $user->id, 'device_name' => 'pos-1'])
            ->assertStatus(404);
        $this->getJson('/api/v1/auth/quick-login/users')->assertStatus(404);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'api_quick_login']);
    }

    public function test_auth_options_reports_quick_login_false_when_flag_is_off(): void
    {
        config(['auth.quick_login.enabled' => false]);
        $this->enterTenant();

        $this->getJson('/api/v1/auth/options')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quick_login', false)
            ->assertJsonPath('data.default_method', 'password');
    }

    // ------------------------------------------------------------------
    // (b) flag on but production => 404 (runtime gate, route:cache safe)
    // ------------------------------------------------------------------

    public function test_quick_login_returns_404_in_production_even_with_flag_on(): void
    {
        config(['auth.quick_login.enabled' => true]);
        $user = $this->makeUser('كاشير', '01000001002', 'c2@sroor.test');
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->assertTrue($this->app->isProduction());
        $this->enterTenant();

        $this->postJson('/api/v1/auth/quick-login', ['user_id' => $user->id, 'device_name' => 'pos-1'])
            ->assertStatus(404);
        $this->getJson('/api/v1/auth/quick-login/users')->assertStatus(404);

        $this->getJson('/api/v1/auth/options')
            ->assertStatus(200)
            ->assertJsonPath('data.quick_login', false);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ------------------------------------------------------------------
    // (c) flag on in testing => scoped, expiring token; no plaintext copy; audited
    // ------------------------------------------------------------------

    public function test_auth_options_reports_quick_login_true_when_allowed(): void
    {
        $this->enterTenant();

        $this->getJson('/api/v1/auth/options')
            ->assertStatus(200)
            ->assertJsonPath('data.quick_login', true)
            ->assertJsonPath('data.default_method', 'password');
    }

    public function test_quick_login_issues_scoped_expiring_token_and_logs_activity(): void
    {
        $this->freezeSecond();
        $user = $this->makeUser('كاشير الوردية', '01000001003', 'c3@sroor.test');
        $this->enterTenant();

        $response = $this->postJson('/api/v1/auth/quick-login', [
            'user_id' => $user->id,
            'device_name' => 'pos-terminal',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', 'كاشير الوردية')
            ->assertJsonStructure([
                'success',
                'data' => ['token', 'user', 'store', 'stores', 'system', 'expires_at'],
            ]);

        $plain = $response->json('data.token');
        $this->assertIsString($plain);
        $this->assertNotSame('', $plain);
        $this->assertNotNull($response->json('data.expires_at'));

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $pat = PersonalAccessToken::findToken($plain);
        $this->assertNotNull($pat, 'returned token must be a Sanctum personal access token');
        $this->assertSame($user->id, (int) $pat->tokenable_id);
        $this->assertSame(['quick-login'], $pat->abilities, 'quick-login token must carry only the quick-login ability');
        $this->assertFalse($pat->can('*'));
        $this->assertNotNull($pat->expires_at, 'quick-login token must expire');
        $this->assertTrue(
            $pat->expires_at->equalTo(now()->addMinutes($this->ttlMinutes())),
            'expires_at must be now + token_ttl_minutes, got '.$pat->expires_at->toDateTimeString()
        );

        $this->assertNull($user->fresh()->api_token, 'quick-login must never write users.api_token');
        $this->assertDatabaseMissing('users', ['api_token' => $plain]);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'auth',
            'action' => 'api_quick_login',
            'user_id' => $user->id,
        ]);
    }

    // ------------------------------------------------------------------
    // (d) throttle
    // ------------------------------------------------------------------

    public function test_quick_login_is_throttled_per_ip(): void
    {
        $perMinute = (int) config('auth.quick_login.per_minute', 5);
        $this->assertGreaterThan(0, $perMinute);
        $this->enterTenant();

        for ($i = 0; $i < $perMinute; $i++) {
            $status = $this->postJson('/api/v1/auth/quick-login', ['user_id' => 987654 + $i])->status();
            $this->assertNotSame(429, $status, "request #{$i} must not be throttled yet");
            $this->assertNotSame(404, $status, 'quick-login route must be reachable when allowed');
        }

        $this->postJson('/api/v1/auth/quick-login', ['user_id' => 987000])
            ->assertStatus(429);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ------------------------------------------------------------------
    // (e) super_admin refused / (f) inactive refused / validation
    // ------------------------------------------------------------------

    public function test_quick_login_refuses_super_admin_role(): void
    {
        $super = $this->makeUser('مدير المنصة', '01000001004', 'super@sroor.test', true, 'super_admin');
        $this->enterTenant();

        $response = $this->postJson('/api/v1/auth/quick-login', ['user_id' => $super->id, 'device_name' => 'pos-1']);

        $this->assertContains($response->status(), [403, 422], 'super_admin must not be quick-logged-in');
        $this->assertNull($response->json('data.token'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'api_quick_login']);
    }

    public function test_quick_login_refuses_inactive_user(): void
    {
        $inactive = $this->makeUser('موظف موقوف', '01000001005', 'off@sroor.test', false);
        $this->enterTenant();

        $this->postJson('/api/v1/auth/quick-login', ['user_id' => $inactive->id, 'device_name' => 'pos-1'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_quick_login_validation_rejects_bad_payloads(): void
    {
        $this->enterTenant();

        $this->postJson('/api/v1/auth/quick-login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);

        $this->postJson('/api/v1/auth/quick-login', ['user_id' => 'abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);

        $this->postJson('/api/v1/auth/quick-login', ['user_id' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);

        $user = $this->makeUser('كاشير', '01000001006', 'c6@sroor.test');
        $this->postJson('/api/v1/auth/quick-login', ['user_id' => $user->id, 'device_name' => str_repeat('x', 101)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['device_name']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_quick_login_cannot_be_driven_by_phone_or_email(): void
    {
        $user = $this->makeUser('كاشير', '01000001007', 'c7@sroor.test');
        $this->enterTenant();

        $this->postJson('/api/v1/auth/quick-login', ['login' => '01000001007', 'device_name' => 'pos-1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);

        $this->postJson('/api/v1/auth/quick-login', ['user_id' => '01000001007'])
            ->assertStatus(422);

        $this->postJson('/api/v1/auth/quick-login', ['user_id' => 'c7@sroor.test'])
            ->assertStatus(422);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNull($user->fresh()->api_token);
    }

    public function test_quick_login_unknown_user_gets_422(): void
    {
        $this->enterTenant();
        $missingId = ((int) User::query()->max('id')) + 1000;

        $this->postJson('/api/v1/auth/quick-login', ['user_id' => $missingId])
            ->assertStatus(422);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ------------------------------------------------------------------
    // (g) picker list: id + name only, no super admins, no inactive users
    // ------------------------------------------------------------------

    public function test_quick_login_users_list_exposes_only_id_and_name(): void
    {
        $ahmed = $this->makeUser('أحمد الكاشير', '01000002001', 'ahmed-leak@sroor.test');
        $mohamed = $this->makeUser('محمد أمين المخزن', '01000002002', 'mohamed-leak@sroor.test', true, 'storekeeper');
        $this->makeUser('حساب موقوف', '01000002003', 'inactive-leak@sroor.test', false);
        $this->makeUser('مدير المنصة', '01000002004', 'super-leak@sroor.test', true, 'super_admin');
        $this->enterTenant();

        $response = $this->getJson('/api/v1/auth/quick-login/users');

        $response->assertStatus(200)->assertJsonPath('success', true);

        $rows = $response->json('data');
        $this->assertIsArray($rows);
        $this->assertSame([$ahmed->id, $mohamed->id], array_column($rows, 'id'), 'only active non-super-admin users, ordered by name');

        foreach ($rows as $row) {
            $keys = array_keys($row);
            sort($keys);
            $this->assertSame(['id', 'name'], $keys, 'picker rows must expose only id and name');
        }

        $body = $response->getContent();
        foreach (['01000002001', '01000002002', '01000002003', '01000002004',
            'ahmed-leak@sroor.test', 'mohamed-leak@sroor.test', 'super-leak@sroor.test', 'مدير المنصة', 'حساب موقوف'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    // ------------------------------------------------------------------
    // (h) central context => 404
    // ------------------------------------------------------------------

    public function test_quick_login_returns_404_in_central_context(): void
    {
        $user = $this->makeUser('كاشير', '01000001008', 'c8@sroor.test');
        $this->assertFalse(tenancy()->initialized);

        $this->postJson('/api/v1/auth/quick-login', ['user_id' => $user->id, 'device_name' => 'pos-1'])
            ->assertStatus(404);
        $this->getJson('/api/v1/auth/quick-login/users')->assertStatus(404);
        $this->getJson('/api/v1/auth/options')
            ->assertStatus(200)
            ->assertJsonPath('data.quick_login', false);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ------------------------------------------------------------------
    // (i) expired quick-login token => 401 and row deleted
    // ------------------------------------------------------------------

    public function test_quick_login_token_is_rejected_after_ttl(): void
    {
        $user = $this->makeUser('كاشير', '01000001009', 'c9@sroor.test');
        $this->enterTenant();

        $token = $this->postJson('/api/v1/auth/quick-login', ['user_id' => $user->id, 'device_name' => 'pos-1'])
            ->assertStatus(200)
            ->json('data.token');
        $this->assertIsString($token);

        $this->leaveTenant();
        $this->resetAuth();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id);

        $this->resetAuth();
        $this->travel($this->ttlMinutes() + 1)->minutes();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ------------------------------------------------------------------
    // (j) quick-login token cannot manage users (DenyQuickLoginToken)
    // ------------------------------------------------------------------

    public function test_quick_login_token_cannot_reach_user_management(): void
    {
        $admin = $this->makeUser('مدير الفرع', '01000001010', 'admin-ql@sroor.test', true, 'admin');
        $this->enterTenant();

        $quickToken = $this->postJson('/api/v1/auth/quick-login', ['user_id' => $admin->id, 'device_name' => 'pos-1'])
            ->assertStatus(200)
            ->json('data.token');
        $this->assertIsString($quickToken);

        $this->leaveTenant();
        $this->resetAuth();

        $this->withHeader('Authorization', 'Bearer '.$quickToken)
            ->getJson('/api/v1/users')
            ->assertStatus(403);

        $this->resetAuth();

        // Same admin with a full-ability token is allowed, proving the 403 is the token scope.
        $fullToken = $admin->createToken('full')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$fullToken)
            ->getJson('/api/v1/users')
            ->assertStatus(200);
    }
}
