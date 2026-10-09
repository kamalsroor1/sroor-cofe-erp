<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Models\CentralAuditLog;
use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * IDEN-1.3: POST /api/v1/super-admin/auth/login, GET …/me, POST …/logout.
 *
 * Contract: 200 / 422 (uniform message) / 429 (central-login limiter, incl. the hourly
 * per-email cap that ignores the IP) / 401 (Bearer central token only, ability central:*,
 * unexpired) / 404 on a tenant host (EnsureCentralContext first).
 */
final class CentralAuthApiTest extends TenantTestCase
{
    private const LOGIN = '/api/v1/super-admin/auth/login';

    private const ME = '/api/v1/super-admin/auth/me';

    private const LOGOUT = '/api/v1/super-admin/auth/logout';

    private const PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralPermissionsSeeder::class);
    }

    private function operator(string $role = CentralPermission::ROLE_SUPER_ADMIN, array $attributes = []): CentralUser
    {
        $user = CentralUser::factory()->create(array_merge([
            'email' => 'operator-'.uniqid().'@central.test',
            'password' => Hash::make(self::PASSWORD),
            'is_active' => true,
        ], $attributes));
        $user->assignRole($role);

        return $user->refresh();
    }

    /** @return array<string, string> */
    private function bearer(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
    }

    private function auditCount(CentralAuditEvent $event): int
    {
        return CentralAuditLog::query()->where('event', $event->value)->count();
    }

    // ---------------------------------------------------------------- login 200

    public function test_login_issues_an_expiring_central_token_and_audits_success(): void
    {
        $this->freezeSecond();
        $user = $this->operator();

        $response = $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => self::PASSWORD, 'device_name' => 'ops-laptop'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('central_auth.login_success'))
            ->assertJsonPath('data.two_factor_required', false)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.roles', [CentralPermission::ROLE_SUPER_ADMIN])
            ->assertJsonStructure(['data' => ['token', 'expires_at', 'user' => ['id', 'name', 'email', 'is_active', 'two_factor_enabled', 'last_login_at', 'roles', 'permissions']]])
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.two_factor_secret');

        $token = CentralPersonalAccessToken::query()->sole();
        $this->assertSame($user->getKey(), $token->tokenable_id);
        $this->assertSame('ops-laptop', $token->name);
        $this->assertSame(['central:*'], $token->abilities);
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->equalTo(now()->addMinutes(240)));
        $this->assertSame($token->expires_at->toIso8601String(), $response->json('data.expires_at'));

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertSame('127.0.0.1', $user->last_login_ip);

        $this->assertSame(1, $this->auditCount(CentralAuditEvent::LoginSucceeded));
        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::LoginSucceeded->value)->sole();
        $this->assertSame($user->getKey(), $log->causer_id);

        // The issued token authenticates /me.
        $this->withHeaders($this->bearer((string) $response->json('data.token')))
            ->getJson(self::ME)
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_login_email_is_case_insensitive_and_trimmed(): void
    {
        $user = $this->operator(attributes: ['email' => 'mixed@central.test']);

        $this->postJson(self::LOGIN, ['email' => '  MIXED@Central.test ', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->getKey());
    }

    public function test_token_ttl_follows_config(): void
    {
        $this->freezeSecond();
        config(['central.token_ttl_minutes' => 30]);
        $user = $this->operator();

        $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => self::PASSWORD])->assertOk();

        $this->assertTrue(CentralPersonalAccessToken::query()->sole()->expires_at?->equalTo(now()->addMinutes(30)));
    }

    public function test_login_with_confirmed_two_factor_returns_two_factor_required_and_no_token(): void
    {
        $user = $this->operator();
        $user->forceFill(['two_factor_secret' => 'encrypted-secret', 'two_factor_confirmed_at' => now()])->save();

        $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => __('central_auth.two_factor_required'),
                'data' => ['two_factor_required' => true],
            ]);

        $this->assertSame(0, CentralPersonalAccessToken::query()->count());
        $this->assertSame(0, $this->auditCount(CentralAuditEvent::LoginSucceeded));
    }

    // ---------------------------------------------------------------- login 422

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): array
    {
        return [
            'missing email' => [['password' => self::PASSWORD], 'email'],
            'not an email' => [['email' => 'not-an-email', 'password' => self::PASSWORD], 'email'],
            'missing password' => [['email' => 'x@central.test'], 'password'],
            'overlong device name' => [['email' => 'x@central.test', 'password' => self::PASSWORD, 'device_name' => str_repeat('a', 101)], 'device_name'],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidPayloads')]
    public function test_login_validation_errors_are_422(array $payload, string $field): void
    {
        $this->postJson(self::LOGIN, $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(0, CentralPersonalAccessToken::query()->count());
    }

    public function test_wrong_password_unknown_email_and_inactive_account_get_the_same_422_and_are_audited(): void
    {
        $active = $this->operator();
        $inactive = $this->operator(attributes: ['is_active' => false]);

        $attempts = [
            [$active->email, 'wrong-password', 'invalid_password'],
            ['nobody@central.test', self::PASSWORD, 'unknown_email'],
            [$inactive->email, self::PASSWORD, 'inactive'],
        ];

        foreach ($attempts as [$email, $password, $reason]) {
            $this->postJson(self::LOGIN, ['email' => $email, 'password' => $password])
                ->assertStatus(422)
                ->assertJsonPath('errors.email.0', __('central_auth.failed'));

            $log = CentralAuditLog::query()->where('event', CentralAuditEvent::LoginFailed->value)->latest('id')->firstOrFail();
            $this->assertSame($reason, $log->properties['reason'] ?? null);
            $this->assertSame($email, $log->properties['email'] ?? null);
            $this->assertNull($log->causer_id);
        }

        $this->assertSame(3, $this->auditCount(CentralAuditEvent::LoginFailed));
        $this->assertSame(0, CentralPersonalAccessToken::query()->count());
    }

    public function test_a_tenant_user_cannot_sign_in_to_the_central_console(): void
    {
        $tenant = $this->createTenant();
        $tenantAdmin = $this->tenantAdmin($tenant);

        $this->postJson(self::LOGIN, ['email' => (string) $tenantAdmin->email, 'password' => 'password'])
            ->assertStatus(422);

        $this->assertSame(0, CentralPersonalAccessToken::query()->count());
    }

    // ---------------------------------------------------------------- login 429

    public function test_login_is_throttled_per_ip(): void
    {
        $user = $this->operator();
        $limit = (int) config('rate_limits.central_login.per_ip_per_minute');

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson(self::LOGIN, ['email' => 'spray'.$i.'@central.test', 'password' => 'x'])->assertStatus(422);
        }

        // Even the right credentials are refused once the IP budget is spent.
        $this->postJson(self::LOGIN, ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('auth.too_many_requests'));

        $this->assertSame(0, CentralPersonalAccessToken::query()->count());
    }

    public function test_hourly_per_email_cap_ignores_the_client_ip(): void
    {
        $user = $this->operator();
        $limit = (int) config('rate_limits.central_login.per_email_per_hour');
        $this->assertSame(20, $limit);

        for ($i = 0; $i < $limit; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.'.intdiv($i, 200).'.'.($i % 200 + 1)])
                ->postJson(self::LOGIN, ['email' => $user->email, 'password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '172.16.0.99'])
            ->postJson(self::LOGIN, ['email' => strtoupper($user->email), 'password' => self::PASSWORD])
            ->assertStatus(429);

        // Another operator on that fresh IP is not affected.
        $other = $this->operator();
        $this->withServerVariables(['REMOTE_ADDR' => '172.16.0.99'])
            ->postJson(self::LOGIN, ['email' => $other->email, 'password' => self::PASSWORD])
            ->assertOk();
    }

    // ---------------------------------------------------------------- me / 401

    public function test_me_returns_the_operator_with_central_roles_and_permissions(): void
    {
        $support = $this->operator(CentralPermission::ROLE_SUPPORT);
        $token = $support->createToken('t')->plainTextToken;

        $permissions = CentralPermission::readOnlyValues();
        sort($permissions);

        $this->withHeaders($this->bearer($token))
            ->getJson(self::ME)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $support->getKey())
            ->assertJsonPath('data.roles', [CentralPermission::ROLE_SUPPORT])
            ->assertJsonPath('data.permissions', $permissions)
            ->assertJsonPath('data.two_factor_enabled', false)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_me_without_token_is_401(): void
    {
        $this->getJson(self::ME)
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('central_auth.unauthenticated'));
    }

    public function test_token_outside_the_bearer_header_is_rejected(): void
    {
        $token = $this->operator()->createToken('t')->plainTextToken;

        $this->getJson(self::ME.'?token='.urlencode($token))->assertStatus(401);
        $this->getJson(self::ME.'?api_token='.urlencode($token))->assertStatus(401);
        $this->withHeaders(['Accept' => 'application/json', 'X-API-TOKEN' => $token])->getJson(self::ME)->assertStatus(401);
        $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Token '.$token])->getJson(self::ME)->assertStatus(401);
        $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Basic '.base64_encode($token)])->getJson(self::ME)->assertStatus(401);

        // Same token as Bearer works: the rejections above are about the transport only.
        $this->withHeaders($this->bearer($token))->getJson(self::ME)->assertOk();
    }

    public function test_expired_token_is_401_and_deleted(): void
    {
        $user = $this->operator();
        $new = $user->createToken('t', null, now()->subMinute());

        $this->withHeaders($this->bearer($new->plainTextToken))
            ->getJson(self::ME)
            ->assertStatus(401)
            ->assertJsonPath('message', __('central_auth.session_expired'));

        $this->assertNull(CentralPersonalAccessToken::query()->find($new->accessToken->getKey()));
    }

    public function test_token_without_the_central_ability_is_401(): void
    {
        $user = $this->operator();

        foreach ([['*'], ['central:read'], []] as $abilities) {
            $token = $user->createToken('t', $abilities)->plainTextToken;

            $this->withHeaders($this->bearer($token))->getJson(self::ME)->assertStatus(401);
        }
    }

    public function test_token_of_an_inactive_operator_is_401(): void
    {
        $user = $this->operator();
        $token = $user->createToken('t')->plainTextToken;
        $user->forceFill(['is_active' => false])->save();

        $this->withHeaders($this->bearer($token))->getJson(self::ME)->assertStatus(401);
    }

    public function test_garbage_token_is_401(): void
    {
        $this->withHeaders($this->bearer('1|not-a-real-token'))->getJson(self::ME)->assertStatus(401);
        $this->withHeaders($this->bearer('not-a-real-token'))->getJson(self::ME)->assertStatus(401);
    }

    // ---------------------------------------------------------------- logout

    public function test_logout_revokes_only_the_current_token_and_audits(): void
    {
        $user = $this->operator();
        $current = $user->createToken('current')->plainTextToken;
        $other = $user->createToken('other')->plainTextToken;

        $this->withHeaders($this->bearer($current))
            ->postJson(self::LOGOUT)
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => __('central_auth.logout_success')]);

        $this->assertSame(['other'], CentralPersonalAccessToken::query()->pluck('name')->all());
        $this->assertSame(1, $this->auditCount(CentralAuditEvent::Logout));

        $this->withHeaders($this->bearer($current))->getJson(self::ME)->assertStatus(401);
        $this->withHeaders($this->bearer($other))->getJson(self::ME)->assertOk();
    }

    public function test_logout_without_token_is_401(): void
    {
        $this->postJson(self::LOGOUT)->assertStatus(401);
        $this->assertSame(0, $this->auditCount(CentralAuditEvent::Logout));
    }

    // ---------------------------------------------------------------- central context

    public function test_central_auth_routes_are_404_on_a_tenant_host(): void
    {
        $tenant = $this->createTenant();
        $user = $this->operator();
        $token = $user->createToken('t')->plainTextToken;

        $this->postJson($this->tenantUrl($tenant, self::LOGIN), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertNotFound();
        $this->withHeaders($this->bearer($token))->getJson($this->tenantUrl($tenant, self::ME))->assertNotFound();
        $this->withHeaders($this->bearer($token))->postJson($this->tenantUrl($tenant, self::LOGOUT))->assertNotFound();

        $this->assertSame(1, CentralPersonalAccessToken::query()->count());
    }

    public function test_tenant_host_answers_404_even_after_the_login_limiter_is_exhausted(): void
    {
        $tenant = $this->createTenant();
        $limit = (int) config('rate_limits.central_login.per_ip_per_minute');

        for ($i = 0; $i <= $limit; $i++) {
            $this->postJson($this->tenantUrl($tenant, self::LOGIN), ['email' => 'x'.$i.'@central.test', 'password' => 'x'])
                ->assertNotFound();
        }
    }

    public function test_unknown_x_tenant_on_central_routes_is_404(): void
    {
        $this->withHeaders(['X-Tenant' => 'no-such-tenant'])
            ->postJson(self::LOGIN, ['email' => 'x@central.test', 'password' => 'x'])
            ->assertNotFound();
    }
}
