<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\ResolveApiTenancy;
use App\Http\Middleware\ThrottleTenantMisses;
use App\Models\Tenant;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TenantTestCase;

/**
 * IDEN-4.6: every rate limiter lives in AppServiceProvider (config/rate_limits.php):
 *  - tenant-login: per tenant + login + IP, plus a configurable per-IP ceiling;
 *  - central-login: per IP, per email + IP, and per email per hour ignoring the IP;
 *  - public-api: every public, unauthenticated endpoint;
 *  - ApiLoginRequest's failure counter is keyed by tenant as well;
 *  - a 429 is always a localized JSON envelope with Retry-After.
 */
final class LoginThrottleApiTest extends TenantTestCase
{
    private const SHARED_LOGIN = 'shared-login@throttle.test';

    private const CENTRAL_LOGIN_TEST_URI = '/api/v1/_iden46/central-login-probe';

    // ── tenant-login ──────────────────────────────────────────────────────

    public function test_failure_counter_of_one_tenant_does_not_lock_the_same_login_in_another_tenant(): void
    {
        [$a, $b] = $this->twoTenantsSharingALogin();

        for ($i = 1; $i <= 6; $i++) {
            $this->tenantLogin($a, self::SHARED_LOGIN, 'wrong-pass')
                ->assertStatus(422)
                ->assertJsonPath('errors.login.0', __('auth.failed'));
        }

        // Tenant A: the 7th attempt is refused by the failure counter (tenant scoped).
        $locked = $this->tenantLogin($a, self::SHARED_LOGIN, 'password')->assertStatus(422);
        $this->assertNotSame(__('auth.failed'), $locked->json('errors.login.0'));

        // Tenant B: the very same login string is unaffected.
        $this->tenantLogin($b, self::SHARED_LOGIN, 'password')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_per_login_limiter_is_scoped_by_tenant(): void
    {
        [$a, $b] = $this->twoTenantsSharingALogin();
        $perLogin = (int) config('rate_limits.tenant_login.per_login_per_minute');
        $this->assertGreaterThan(0, $perLogin);

        for ($i = 1; $i <= $perLogin; $i++) {
            $this->assertNotSame(429, $this->tenantLogin($a, self::SHARED_LOGIN, 'wrong-pass')->status(), "attempt #{$i}");
        }

        $this->assertThrottled($this->tenantLogin($a, self::SHARED_LOGIN, 'wrong-pass'));

        $this->tenantLogin($b, self::SHARED_LOGIN, 'password')->assertStatus(200);
    }

    public function test_per_ip_ceiling_spans_tenants_and_is_configurable(): void
    {
        config(['rate_limits.tenant_login.per_ip_per_minute' => 4]);

        $a = $this->createTenant();
        $b = $this->createTenant();

        $this->tenantLogin($a, 'spray-1@throttle.test', 'x')->assertStatus(422);
        $this->tenantLogin($b, 'spray-2@throttle.test', 'x')->assertStatus(422);
        $this->tenantLogin($a, 'spray-3@throttle.test', 'x')->assertStatus(422);
        $this->tenantLogin($b, 'spray-4@throttle.test', 'x')->assertStatus(422);

        $this->assertThrottled($this->tenantLogin($a, 'spray-5@throttle.test', 'x'));

        // Another client IP (another shop behind its own NAT) keeps its own bucket.
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40']);
        $this->tenantLogin($b, 'spray-6@throttle.test', 'x')->assertStatus(422);
    }

    public function test_default_per_ip_ceiling_is_thirty_per_minute(): void
    {
        $this->assertSame(30, (int) config('rate_limits.tenant_login.per_ip_per_minute'));
    }

    // ── public-api ────────────────────────────────────────────────────────

    public function test_every_public_route_carries_a_named_throttle(): void
    {
        $expected = [
            'api.ping' => 'throttle:public-api',
            'api.app.version' => 'throttle:public-api',
            'api.app.check_update' => 'throttle:public-api',
            'api.app.download_apk' => 'throttle:public-api',
            'api.app.download_latest_apk' => 'throttle:public-api',
            'api.system.translations' => 'throttle:public-api',
            'api.auth.options' => 'throttle:public-api',
            'api.central.tenants.resolve' => 'throttle:tenant-resolve',
            'api.central.tenants.resolve.alias' => 'throttle:tenant-resolve',
            'api.auth.login' => 'throttle:tenant-login',
        ];

        foreach ($expected as $name => $throttle) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertInstanceOf(RoutingRoute::class, $route, "route [{$name}] is missing");
            $this->assertContains($throttle, $route->gatherMiddleware(), "route [{$name}] must use {$throttle}");
        }
    }

    public function test_tenant_is_resolved_before_the_login_limiter_runs(): void
    {
        $route = Route::getRoutes()->getByName('api.auth.login');
        $this->assertInstanceOf(RoutingRoute::class, $route);

        $ordered = array_values(app('router')->gatherRouteMiddleware($route));
        $tenancy = array_search(ResolveApiTenancy::class, $ordered, true);
        $throttle = array_search(ThrottleRequests::class.':tenant-login', $ordered, true);

        $this->assertIsInt($tenancy);
        $this->assertIsInt($throttle);
        $this->assertLessThan($throttle, $tenancy, 'ResolveApiTenancy must run before throttle:tenant-login.');
    }

    public function test_public_api_buckets_are_per_endpoint(): void
    {
        config(['rate_limits.public_api.per_minute' => 3]);

        for ($i = 1; $i <= 3; $i++) {
            $this->getJson('/api/v1/ping')->assertStatus(200);
        }
        $this->assertThrottled($this->getJson('/api/v1/ping'));

        // The heartbeat being throttled never starves another public endpoint.
        $this->getJson('/api/v1/auth/options')->assertStatus(200);
    }

    public function test_public_api_limiter_returns_localized_429_with_retry_after(): void
    {
        $perMinute = (int) config('rate_limits.public_api.per_minute');
        $this->assertSame(60, $perMinute);

        for ($i = 1; $i <= $perMinute; $i++) {
            $this->getJson('/api/v1/ping')->assertStatus(200);
        }

        $this->assertThrottled($this->getJson('/api/v1/ping'));
    }

    // ── tenant-miss (unknown workspace codes) ────────────────────────────

    public function test_unknown_tenant_probes_on_ping_are_throttled_per_ip(): void
    {
        // CTO W1 Q1 (2026-10-09): the tenant-miss bucket shares the tenant-resolve budget, 30/min.
        $budget = (int) config('rate_limits.tenant_resolve.per_minute');
        $this->assertSame(30, $budget);

        for ($i = 1; $i <= $budget; $i++) {
            $this->getJson('/api/v1/ping', ['X-Tenant' => "guess-{$i}"])->assertStatus(404);
        }

        $this->assertThrottled($this->getJson('/api/v1/ping', ['X-Tenant' => 'guess-last']));
    }

    public function test_unknown_tenant_probes_on_login_are_throttled_per_ip(): void
    {
        $budget = (int) config('rate_limits.tenant_resolve.per_minute');

        for ($i = 1; $i <= $budget; $i++) {
            $this->postJson('/api/v1/auth/login', ['login' => 'x@probe.test', 'password' => 'x'], ['X-Tenant' => "guess-{$i}"])
                ->assertStatus(404);
        }

        $this->assertThrottled(
            $this->postJson('/api/v1/auth/login', ['login' => 'x@probe.test', 'password' => 'x'], ['X-Tenant' => 'guess-last'])
        );
    }

    public function test_query_string_tenant_probes_share_the_miss_budget(): void
    {
        config(['rate_limits.tenant_resolve.per_minute' => 2]);

        $this->getJson('/api/v1/ping?tenant=guess-a')->assertStatus(404);
        $this->getJson('/api/v1/system/translations', ['X-Tenant' => 'guess-b'])->assertStatus(404);

        $this->assertThrottled($this->getJson('/api/v1/ping?tenant=guess-c'));
    }

    public function test_exhausted_miss_budget_hides_whether_a_real_tenant_exists(): void
    {
        config(['rate_limits.tenant_resolve.per_minute' => 2]);
        $real = $this->createTenant();

        $this->getJson('/api/v1/ping', ['X-Tenant' => 'guess-1'])->assertStatus(404);
        $this->getJson('/api/v1/ping', ['X-Tenant' => 'guess-2'])->assertStatus(404);

        // No oracle: a real workspace code from the same IP is refused the same way.
        $this->assertThrottled($this->getJson('/api/v1/ping', ['X-Tenant' => (string) $real->getTenantKey()]));

        // Another client IP keeps its own bucket.
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.41']);
        $this->getJson('/api/v1/ping', ['X-Tenant' => (string) $real->getTenantKey()])->assertStatus(200);
    }

    public function test_requests_to_a_known_tenant_do_not_consume_the_miss_budget(): void
    {
        config(['rate_limits.tenant_resolve.per_minute' => 2]);
        $real = $this->createTenant();

        for ($i = 1; $i <= 5; $i++) {
            $this->getJson('/api/v1/ping', ['X-Tenant' => (string) $real->getTenantKey()])->assertStatus(200);
        }

        $this->getJson('/api/v1/ping', ['X-Tenant' => 'guess-1'])->assertStatus(404);
    }

    public function test_tenant_miss_throttle_runs_before_tenant_resolution(): void
    {
        foreach (['api.auth.login', 'api.ping'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertInstanceOf(RoutingRoute::class, $route);

            $ordered = array_values(app('router')->gatherRouteMiddleware($route));
            $misses = array_search(ThrottleTenantMisses::class, $ordered, true);
            $tenancy = array_search(ResolveApiTenancy::class, $ordered, true);

            $this->assertIsInt($misses, "route [{$name}] must use ThrottleTenantMisses");
            $this->assertIsInt($tenancy);
            $this->assertLessThan($tenancy, $misses, "ThrottleTenantMisses must run before ResolveApiTenancy on [{$name}].");
        }
    }

    // ── central-login ─────────────────────────────────────────────────────

    public function test_central_login_per_email_hourly_cap_ignores_the_ip(): void
    {
        $this->registerCentralLoginProbe();
        config(['rate_limits.central_login.per_email_per_hour' => 3]);

        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip]);
            $this->postJson(self::CENTRAL_LOGIN_TEST_URI, ['email' => 'Owner@Platform.test'])->assertStatus(200);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4']);
        $this->assertThrottled($this->postJson(self::CENTRAL_LOGIN_TEST_URI, ['email' => ' owner@platform.test ']));

        // Another operator from the same new IP is not affected.
        $this->postJson(self::CENTRAL_LOGIN_TEST_URI, ['email' => 'other@platform.test'])->assertStatus(200);
    }

    public function test_central_login_per_ip_cap_across_emails(): void
    {
        $this->registerCentralLoginProbe();
        $perIp = (int) config('rate_limits.central_login.per_ip_per_minute');
        $this->assertGreaterThan(0, $perIp);

        for ($i = 1; $i <= $perIp; $i++) {
            $this->postJson(self::CENTRAL_LOGIN_TEST_URI, ['email' => "op{$i}@platform.test"])->assertStatus(200);
        }

        $this->assertThrottled($this->postJson(self::CENTRAL_LOGIN_TEST_URI, ['email' => 'op-last@platform.test']));
    }

    public function test_central_login_per_email_and_ip_cap(): void
    {
        $this->registerCentralLoginProbe();
        $perEmail = (int) config('rate_limits.central_login.per_email_per_minute');
        $this->assertGreaterThan(0, $perEmail);
        $this->assertLessThan((int) config('rate_limits.central_login.per_ip_per_minute'), $perEmail);

        for ($i = 1; $i <= $perEmail; $i++) {
            $this->postJson(self::CENTRAL_LOGIN_TEST_URI, ['email' => 'owner@platform.test'])->assertStatus(200);
        }

        $this->assertThrottled($this->postJson(self::CENTRAL_LOGIN_TEST_URI, ['email' => 'owner@platform.test']));
    }

    // ── 429 renderer ──────────────────────────────────────────────────────

    public function test_429_message_is_translated_in_both_locales(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $message = trans('auth.too_many_requests', [], $locale);
            $this->assertNotSame('auth.too_many_requests', $message, "auth.too_many_requests missing in lang/{$locale}");
        }
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /**
     * @return array{0: Tenant, 1: Tenant}
     */
    private function twoTenantsSharingALogin(): array
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        foreach ([$a, $b] as $tenant) {
            $this->createTenantUser($tenant, 'admin', [], ['email' => self::SHARED_LOGIN]);
        }

        return [$a, $b];
    }

    /**
     * @return TestResponse<Response>
     */
    private function tenantLogin(Tenant $tenant, string $login, string $password): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', [
            'login' => $login,
            'password' => $password,
        ], ['X-Tenant' => (string) $tenant->getTenantKey()]);
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertThrottled(TestResponse $response): void
    {
        $response->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('auth.too_many_requests'))
            ->assertHeader('Retry-After');

        $retryAfter = $response->json('retry_after');
        $this->assertIsInt($retryAfter);
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertSame((string) $retryAfter, (string) $response->headers->get('Retry-After'));
    }

    /**
     * The central login route arrives with IDEN-1.3; this probe exercises the
     * `central-login` limiter it will use without depending on that work.
     */
    private function registerCentralLoginProbe(): void
    {
        Route::post(self::CENTRAL_LOGIN_TEST_URI, fn () => response()->json(['success' => true]))
            ->middleware('throttle:central-login');
    }
}
