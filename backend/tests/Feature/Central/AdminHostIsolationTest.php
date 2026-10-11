<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use Database\Seeders\CentralPermissionsSeeder;
use Tests\TenantTestCase;

/**
 * IDEN-1.11: the platform console lives on its own host, config('central.admin_domains')
 * (env CENTRAL_ADMIN_DOMAINS only).
 *
 *  - /api/v1/super-admin/* answers only on an admin host: any other host (central or
 *    tenant) is 404, before authentication; an X-Tenant / ?tenant= there is 404 too;
 *  - the tenant API and the tenant SPA are refused on the admin host; the SPA served there
 *    carries <meta name="app-context" content="central"> (and only there);
 *  - responses on the admin host carry the strict security headers (CSP with a per-request
 *    script nonce, frame-ancestors 'none', Referrer-Policy, HSTS…); other hosts do not;
 *  - with no admin host configured, production fails closed (404) and local/testing fall
 *    back to the central domains.
 */
final class AdminHostIsolationTest extends TenantTestCase
{
    private const ADMIN_HOST = 'admin.platform-harness.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralPermissionsSeeder::class);
        config(['central.admin_domains' => [self::ADMIN_HOST]]);
    }

    private function adminUrl(string $path): string
    {
        return 'http://'.self::ADMIN_HOST.'/'.ltrim($path, '/');
    }

    public function test_control_plane_answers_on_the_admin_host(): void
    {
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->getJson($this->adminUrl('/api/v1/super-admin/dashboard'), $headers)->assertOk();
        $this->getJson($this->adminUrl('/api/v1/super-admin/dashboard'))->assertUnauthorized();
    }

    public function test_control_plane_is_404_on_a_non_admin_central_host_even_for_an_operator(): void
    {
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->getJson('http://localhost/api/v1/super-admin/dashboard', $headers)->assertNotFound();
        $this->getJson('http://localhost/api/v1/super-admin/dashboard')->assertNotFound();
        $this->postJson('http://localhost/api/v1/super-admin/auth/login', [
            'email' => 'nobody@central.test',
            'password' => 'whatever-password',
        ])->assertNotFound();
    }

    public function test_control_plane_is_404_on_a_tenant_host(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->getJson($this->tenantUrl($tenant, '/api/v1/super-admin/dashboard'), $headers)->assertNotFound();
    }

    public function test_a_tenant_identifier_on_the_admin_host_is_404(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->getJson($this->adminUrl('/api/v1/super-admin/dashboard'), $headers + ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertNotFound();
        $this->getJson($this->adminUrl('/api/v1/super-admin/dashboard?tenant='.$tenant->getTenantKey()), $headers)
            ->assertNotFound();
    }

    public function test_the_tenant_api_is_refused_on_the_admin_host(): void
    {
        $tenant = $this->createTenant();

        $this->postJson($this->adminUrl('/api/v1/auth/login'), [
            'login' => 'someone',
            'password' => 'password',
        ], ['X-Tenant' => (string) $tenant->getTenantKey()])->assertNotFound();

        $this->getJson($this->adminUrl('/api/v1/auth/me'), $this->tenantHeaders($tenant))->assertNotFound();
        $this->getJson($this->adminUrl('/api/v1/items'))->assertNotFound();
    }

    public function test_central_safe_public_endpoints_still_answer_on_the_admin_host(): void
    {
        $this->getJson($this->adminUrl('/api/v1/ping'))->assertOk();
        $this->getJson($this->adminUrl('/api/v1/system/translations'))->assertOk();
    }

    public function test_the_admin_host_serves_the_central_spa_with_strict_security_headers(): void
    {
        $response = $this->get($this->adminUrl('/super-admin/login'))->assertOk();

        $html = (string) $response->getContent();
        $this->assertStringContainsString('<meta name="app-context" content="central">', $html);

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9+\\/=]+)'/", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);

        preg_match("/'nonce-([^']+)'/", $csp, $matches);
        $nonce = $matches[1] ?? '';
        $this->assertNotSame('', $nonce);

        // Every inline <script> of the shell carries the request's nonce.
        preg_match_all('/<script(?![^>]*\bsrc=)([^>]*)>/i', $html, $inline);
        $this->assertNotEmpty($inline[1]);
        foreach ($inline[1] as $attributes) {
            $this->assertStringContainsString('nonce="'.$nonce.'"', $attributes);
        }

        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('max-age=', (string) $response->headers->get('Strict-Transport-Security'));
    }

    public function test_every_admin_host_response_carries_the_headers_and_a_fresh_nonce(): void
    {
        $first = $this->getJson($this->adminUrl('/api/v1/super-admin/dashboard'));
        $second = $this->getJson($this->adminUrl('/api/v1/super-admin/dashboard'));

        $this->assertStringContainsString("frame-ancestors 'none'", (string) $first->headers->get('Content-Security-Policy'));
        $this->assertNotSame(
            $first->headers->get('Content-Security-Policy'),
            $second->headers->get('Content-Security-Policy'),
        );
    }

    public function test_a_non_admin_host_serves_the_tenant_spa_without_the_admin_policy(): void
    {
        $response = $this->get('http://localhost/login')->assertOk();

        $this->assertStringNotContainsString('name="app-context"', (string) $response->getContent());
        $this->assertStringNotContainsString(' nonce="', (string) $response->getContent());
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function test_super_admin_spa_paths_are_404_off_the_admin_host(): void
    {
        $this->get('http://localhost/super-admin/login')->assertNotFound();
        $this->get('http://localhost/super-admin')->assertNotFound();
    }

    public function test_production_without_admin_hosts_fails_closed(): void
    {
        config(['central.admin_domains' => []]);
        $headers = $this->centralHeaders($this->centralSuperAdmin());
        $this->app->detectEnvironment(static fn (): string => 'production');

        try {
            $this->getJson('http://localhost/api/v1/super-admin/dashboard', $headers)->assertNotFound();
        } finally {
            $this->app->detectEnvironment(static fn (): string => 'testing');
        }
    }

    public function test_local_without_admin_hosts_falls_back_to_the_central_domains(): void
    {
        config(['central.admin_domains' => []]);
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        $this->getJson('http://localhost/api/v1/super-admin/dashboard', $headers)->assertOk();

        $response = $this->get('http://localhost/login')->assertOk();
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }
}
