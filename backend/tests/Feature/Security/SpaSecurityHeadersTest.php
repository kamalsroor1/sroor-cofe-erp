<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\SpaSecurityHeaders;
use Tests\TenantTestCase;

/**
 * SEC-07: strict Content-Security-Policy on the tenant and central-hub SPA shell only.
 */
final class SpaSecurityHeadersTest extends TenantTestCase
{
    private function scriptSrc(string $csp): string
    {
        preg_match('/(?:^|; )script-src ([^;]*)/', $csp, $m);

        return $m[1] ?? '';
    }

    public function test_tenant_shell_has_a_csp_and_inline_scripts_carry_its_nonce(): void
    {
        $tenant = $this->createTenant();

        $response = $this->get($this->tenantUrl($tenant, '/login'))->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9+\\/=]+)'/", $csp);

        $scriptSrc = $this->scriptSrc($csp);
        $this->assertStringNotContainsString('unsafe-eval', $scriptSrc);
        $this->assertStringNotContainsString('unsafe-inline', $scriptSrc);
        $this->assertStringNotContainsString('unsafe-eval', $csp);

        foreach ([
            "default-src 'self'", "frame-src 'none'", "frame-ancestors 'none'", "object-src 'none'",
            "base-uri 'self'", "form-action 'self'", "worker-src 'self'", "manifest-src 'self'",
            "connect-src 'self'", 'font-src', 'https://fonts.gstatic.com',
        ] as $expected) {
            $this->assertStringContainsString($expected, $csp);
        }

        preg_match("/'nonce-([^']+)'/", $csp, $matches);
        $nonce = $matches[1] ?? '';
        $this->assertNotSame('', $nonce);

        $html = (string) $response->getContent();
        preg_match_all('/<script(?![^>]*\bsrc=)([^>]*)>/i', $html, $inline);
        $this->assertNotEmpty($inline[1]);
        foreach ($inline[1] as $attributes) {
            $this->assertStringContainsString('nonce="'.$nonce.'"', $attributes);
        }
        preg_match_all('/<style([^>]*)>/i', $html, $styles);
        foreach ($styles[1] as $attributes) {
            $this->assertStringContainsString('nonce="'.$nonce.'"', $attributes);
        }

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));
        $this->assertFalse($response->headers->has('Strict-Transport-Security'), 'HSTS is sent by nginx');
    }

    public function test_every_response_gets_a_fresh_nonce(): void
    {
        $tenant = $this->createTenant();
        $url = $this->tenantUrl($tenant, '/login');

        $this->assertNotSame(
            $this->get($url)->headers->get('Content-Security-Policy'),
            $this->get($url)->headers->get('Content-Security-Policy'),
        );
    }

    public function test_tenant_catch_all_shell_path_gets_the_policy(): void
    {
        $tenant = $this->createTenant();

        $response = $this->get($this->tenantUrl($tenant, '/invoices/123/print'))->assertOk();

        $this->assertStringContainsString("script-src 'self' 'nonce-", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_central_hub_shell_gets_the_policy_with_cross_origin_logo_images(): void
    {
        config(['app.url' => 'https://platform.example.test']);

        $response = $this->get('http://localhost/connect')->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'/", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        preg_match('/img-src ([^;]*)/', $csp, $m);
        $this->assertStringContainsString('https:', $m[1]);
        $this->assertStringContainsString('https://platform.example.test', $m[1]);
        $this->assertStringContainsString('blob:', $m[1]);
    }

    public function test_tenant_host_images_do_not_allow_arbitrary_https(): void
    {
        config(['app.url' => 'https://platform.example.test']);
        $tenant = $this->createTenant();

        $csp = (string) $this->get($this->tenantUrl($tenant, '/login'))->headers->get('Content-Security-Policy');

        preg_match('/img-src ([^;]*)/', $csp, $m);
        $this->assertSame("'self' data: blob: https://platform.example.test", $m[1]);
    }

    public function test_report_only_switch_sends_the_report_only_header(): void
    {
        config(['security.csp.report_only' => true]);
        $tenant = $this->createTenant();

        $response = $this->get($this->tenantUrl($tenant, '/login'))->assertOk();

        $this->assertFalse($response->headers->has('Content-Security-Policy'));
        $this->assertStringContainsString("script-src 'self' 'nonce-", (string) $response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_api_json_blade_print_and_static_routes_do_not_get_the_spa_csp(): void
    {
        $tenant = $this->createTenant();

        $api = $this->getJson($this->tenantUrl($tenant, '/api/v1/system/translations'));
        $this->assertFalse($api->headers->has('Content-Security-Policy'));

        $manifest = $this->get('http://localhost/app.webmanifest');
        $this->assertFalse($manifest->headers->has('Content-Security-Policy'));

        $brochure = $this->get('http://localhost/brochure');
        $this->assertFalse($brochure->headers->has('Content-Security-Policy'));
    }

    public function test_blade_print_routes_and_excluded_routes_are_not_wrapped_by_the_spa_middleware(): void
    {
        // The tenant routes are mounted after the web.php catch-all in the test kernel (see
        // InvoicePrintRoutesTest probes), so assert the real route definitions instead.
        $routes = app('router')->getRoutes();

        foreach (['invoices.print.thermal', 'invoices.print.a4', 'daily.journal.print', 'items.movements.print', 'reports.print', 'marketing.brochure', 'web_manifest'] as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertNotContains(SpaSecurityHeaders::class, app('router')->gatherRouteMiddleware($route), $name);
        }

        foreach (['login', 'dashboard', 'pos.index', 'invoices.create', 'tenant.spa', 'stock-transfers', 'app'] as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains(SpaSecurityHeaders::class, app('router')->gatherRouteMiddleware($route), $name);
        }
    }

    public function test_admin_host_keeps_a_single_admin_policy(): void
    {
        config(['central.admin_domains' => ['admin.csp-harness.test']]);

        $response = $this->get('http://admin.csp-harness.test/super-admin/login')->assertOk();

        $this->assertCount(1, (array) $response->headers->all('Content-Security-Policy'));
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringNotContainsString('frame-src', (string) $response->headers->get('Content-Security-Policy'));
    }
}
