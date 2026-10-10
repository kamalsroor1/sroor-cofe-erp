<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Models\CentralAuditLog;
use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\TenantRateLimitOverride;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * IDEN-4.6 ext (CTO W1 Q1): a platform operator temporarily raises one tenant's rate limits
 * (e.g. installing several devices behind one shop NAT).
 *
 *  - POST /api/v1/super-admin/tenants/{id}/rate-limits: tenants.manage + step-up 2FA,
 *    expiry mandatory and capped, audited `tenant_rate_limit_raised`;
 *  - GET  …/rate-limits: tenants.view, current override + config defaults;
 *  - the tenant-login and tenant-resolve limiters read the override for THAT tenant only,
 *    never lower a limit, and ignore it once expired.
 */
final class TenantRateLimitOverrideTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralPermissionsSeeder::class);

        config([
            'rate_limits.tenant_login.per_ip_per_minute' => 2,
            'rate_limits.tenant_login.per_login_per_minute' => 100,
            'rate_limits.tenant_login.max_failed_attempts' => 100,
            'rate_limits.tenant_resolve.per_minute' => 2,
        ]);
    }

    private function url(Tenant $tenant): string
    {
        return '/api/v1/super-admin/tenants/'.$tenant->getTenantKey().'/rate-limits';
    }

    /** @return array<string, string> */
    private function steppedUp(CentralUser $user): array
    {
        $headers = $this->centralHeaders($user);

        CentralPersonalAccessToken::query()
            ->where('tokenable_id', $user->getKey())
            ->update(['two_factor_verified_at' => now()]);

        return $headers;
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tenant_login_per_ip_per_minute' => 5,
            'tenant_resolve_per_minute' => 5,
            'duration_minutes' => 120,
            'reason' => 'تركيب 6 أجهزة في الفرع',
        ], $overrides);
    }

    private function raise(Tenant $tenant, array $payload = []): void
    {
        $this->postJson($this->url($tenant), $this->payload($payload), $this->steppedUp($this->centralSuperAdmin()))
            ->assertCreated();
    }

    private function failedLogin(Tenant $tenant): int
    {
        return $this->postJson('/api/v1/auth/login', [
            'login' => 'nobody-'.Str::lower(Str::random(4)),
            'password' => 'wrong-password',
        ], ['X-Tenant' => (string) $tenant->getTenantKey()])->status();
    }

    public function test_super_admin_raises_limits_with_expiry_and_it_is_audited(): void
    {
        $this->freezeSecond();
        $tenant = $this->createTenant();
        $operator = $this->centralSuperAdmin();

        $response = $this->postJson($this->url($tenant), $this->payload(), $this->steppedUp($operator))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('super.rate_limit_raised_success'))
            ->assertJsonPath('data.tenant_id', (string) $tenant->getTenantKey())
            ->assertJsonPath('data.limits.tenant_login_per_ip_per_minute', 5)
            ->assertJsonPath('data.limits.tenant_login_per_login_per_minute', null)
            ->assertJsonPath('data.limits.tenant_resolve_per_minute', 5)
            ->assertJsonPath('data.central_user_id', (int) $operator->getKey());

        $override = TenantRateLimitOverride::query()->findOrFail($response->json('data.id'));
        $this->assertTrue($override->expires_at->equalTo(now()->addMinutes(120)));
        $this->assertNull($override->revoked_at);

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantRateLimitRaised->value)->sole();
        $this->assertSame((string) $tenant->getTenantKey(), $audit->tenant_id);
        $this->assertSame(5, $audit->properties['tenant_login_per_ip_per_minute'] ?? null);
        $this->assertSame(120, $audit->properties['duration_minutes'] ?? null);

        $this->getJson($this->url($tenant), $this->centralHeaders($operator))
            ->assertOk()
            ->assertJsonPath('data.defaults.tenant_login_per_ip_per_minute', 2)
            ->assertJsonPath('data.override.id', $override->getKey());
    }

    public function test_a_new_override_revokes_the_previous_one(): void
    {
        $tenant = $this->createTenant();

        $this->raise($tenant);
        $this->raise($tenant, ['tenant_login_per_ip_per_minute' => 9]);

        $this->assertSame(2, TenantRateLimitOverride::query()->where('tenant_id', $tenant->getTenantKey())->count());
        $this->assertSame(1, TenantRateLimitOverride::query()->where('tenant_id', $tenant->getTenantKey())->active()->count());
        $this->assertSame(9, TenantRateLimitOverride::query()->where('tenant_id', $tenant->getTenantKey())->active()->value('tenant_login_per_ip_per_minute'));
    }

    public function test_raise_requires_a_recent_second_factor(): void
    {
        $tenant = $this->createTenant();

        $this->postJson($this->url($tenant), $this->payload(), $this->centralHeaders($this->centralSuperAdmin()))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->assertSame(0, TenantRateLimitOverride::query()->count());
    }

    public function test_support_can_read_but_not_raise(): void
    {
        $tenant = $this->createTenant();
        $support = CentralUser::factory()->create(['email' => 'support-'.Str::lower(Str::random(5)).'@central.test', 'is_active' => true]);
        $support->assignRole(CentralPermission::ROLE_SUPPORT);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->getJson($this->url($tenant), $this->centralHeaders($support))->assertOk();
        $this->postJson($this->url($tenant), $this->payload(), $this->steppedUp($support))->assertForbidden();
    }

    public function test_guest_and_tenant_tokens_are_401(): void
    {
        $tenant = $this->createTenant();

        $this->postJson($this->url($tenant), $this->payload())->assertUnauthorized();
        $this->postJson($this->url($tenant), $this->payload(), ['Authorization' => 'Bearer '.$this->tenantToken($tenant)])
            ->assertUnauthorized();
    }

    public function test_unknown_tenant_is_404(): void
    {
        $headers = $this->steppedUp($this->centralSuperAdmin());

        $this->postJson('/api/v1/super-admin/tenants/no-such-tenant/rate-limits', $this->payload(), $headers)
            ->assertNotFound()
            ->assertJsonPath('message', __('super.tenant_not_found'));
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidPayloads(): array
    {
        return [
            'no limit at all' => [['tenant_login_per_ip_per_minute' => null, 'tenant_resolve_per_minute' => null], 'tenant_login_per_ip_per_minute'],
            'zero limit' => [['tenant_login_per_ip_per_minute' => 0], 'tenant_login_per_ip_per_minute'],
            'limit above the cap' => [['tenant_resolve_per_minute' => 100000], 'tenant_resolve_per_minute'],
            'missing duration' => [['duration_minutes' => null], 'duration_minutes'],
            'duration above the cap' => [['duration_minutes' => 100000], 'duration_minutes'],
            'missing reason' => [['reason' => ''], 'reason'],
            'overlong reason' => [['reason' => str_repeat('a', 501)], 'reason'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payloads_are_422(array $overrides, string $field): void
    {
        $tenant = $this->createTenant();

        $this->postJson($this->url($tenant), $this->payload($overrides), $this->steppedUp($this->centralSuperAdmin()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_the_login_limiter_uses_the_override_for_that_tenant_only(): void
    {
        $raised = $this->createTenant();
        $other = $this->createTenant();

        $this->raise($raised, ['tenant_login_per_ip_per_minute' => 4]);

        // Other tenant: the default ceiling of 2 per IP.
        $this->assertNotSame(429, $this->failedLogin($other));
        $this->assertNotSame(429, $this->failedLogin($other));
        $this->assertSame(429, $this->failedLogin($other));

        // Raised tenant: its own bucket with the raised ceiling of 4.
        for ($i = 0; $i < 4; $i++) {
            $this->assertNotSame(429, $this->failedLogin($raised), 'attempt '.($i + 1));
        }
        $this->assertSame(429, $this->failedLogin($raised));
    }

    public function test_the_resolver_limiter_uses_the_override_for_that_workspace_code_only(): void
    {
        $raised = $this->createTenant();
        $other = $this->createTenant();

        $this->raise($raised, ['tenant_resolve_per_minute' => 4]);

        for ($i = 0; $i < 4; $i++) {
            $this->getJson('/api/v1/central/tenants/resolve?code='.$raised->getTenantKey())->assertOk();
        }
        $this->getJson('/api/v1/central/tenants/resolve?code='.$raised->getTenantKey())->assertStatus(429);

        // Probing other codes still spends the default bucket (2): no enumeration through the raise.
        $this->getJson('/api/v1/central/tenants/resolve?code='.$other->getTenantKey())->assertOk();
        $this->getJson('/api/v1/central/tenants/resolve?code=unknown-code')->assertNotFound();
        $this->getJson('/api/v1/central/tenants/resolve?code='.$other->getTenantKey())->assertStatus(429);
    }

    public function test_an_override_never_lowers_a_limit(): void
    {
        $tenant = $this->createTenant();

        $this->raise($tenant, ['tenant_login_per_ip_per_minute' => 1, 'tenant_resolve_per_minute' => null]);

        $this->assertNotSame(429, $this->failedLogin($tenant));
        $this->assertNotSame(429, $this->failedLogin($tenant));
        $this->assertSame(429, $this->failedLogin($tenant));
    }

    public function test_an_expired_override_no_longer_applies(): void
    {
        $tenant = $this->createTenant();

        $this->raise($tenant, ['tenant_login_per_ip_per_minute' => 10, 'duration_minutes' => 30]);

        $this->travel(31)->minutes();

        $this->assertNotSame(429, $this->failedLogin($tenant));
        $this->assertNotSame(429, $this->failedLogin($tenant));
        $this->assertSame(429, $this->failedLogin($tenant));

        $this->getJson($this->url($tenant), $this->centralHeaders($this->centralSuperAdmin()))
            ->assertOk()
            ->assertJsonPath('data.override', null);
    }
}
