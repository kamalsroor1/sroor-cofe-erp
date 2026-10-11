<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Http\Middleware\ResolveApiTenancy;
use App\Http\Middleware\ThrottleTenantMisses;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;
use Tests\TenantTestCase;

/**
 * BRND-5: GET /api/v1/branding/logo/{light|dark}, the public shop logo.
 *
 * Bound to the tenant of the request HOST only: X-Tenant / ?tenant= are ignored, a central
 * or platform-console host is 404, an unknown or not-ready host is 404. Served with a fixed
 * Content-Type (stored extension), nosniff, inline, cacheable 5 minutes with a sha256 ETag.
 */
#[Group('harness')]
final class TenantLogoRouteTest extends TenantTestCase
{
    private const ADMIN_HOST = 'console.branding-admin.test';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['central.admin_domains' => [self::ADMIN_HOST]]);
        $this->tenant = $this->createTenant();
    }

    public function test_serves_the_logo_on_the_tenant_host_with_safe_headers(): void
    {
        $sha256 = $this->uploadLogo($this->tenant, 'light', UploadedFile::fake()->image('logo.png', 200, 100));

        $response = $this->get($this->tenantUrl($this->tenant, '/api/v1/branding/logo/light'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'inline')
            ->assertHeader('ETag', '"'.$sha256.'"');

        $cache = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('max-age=300', $cache);
        $this->assertSame($sha256, hash('sha256', $this->body($response)));
        $this->assertStringStartsWith("\x89PNG", $this->body($response));
    }

    public function test_jpeg_logo_is_served_as_image_jpeg(): void
    {
        $this->uploadLogo($this->tenant, 'dark', UploadedFile::fake()->image('logo.jpg', 200, 100));

        $this->get($this->tenantUrl($this->tenant, '/api/v1/branding/logo/dark'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_matching_if_none_match_returns_304(): void
    {
        $sha256 = $this->uploadLogo($this->tenant, 'light', UploadedFile::fake()->image('logo.png', 200, 100));

        $response = $this->get($this->tenantUrl($this->tenant, '/api/v1/branding/logo/light'), ['If-None-Match' => '"'.$sha256.'"']);

        $response->assertStatus(304);
        $this->assertSame('', $this->body($response));

        $this->get($this->tenantUrl($this->tenant, '/api/v1/branding/logo/light'), ['If-None-Match' => '"stale"'])->assertOk();
    }

    public function test_tenant_without_a_logo_is_404(): void
    {
        $this->get($this->tenantUrl($this->tenant, '/api/v1/branding/logo/light'))
            ->assertNotFound()
            ->assertJsonPath('message', __('branding.logo_not_found'));
    }

    public function test_only_the_requested_variant_is_served(): void
    {
        $this->uploadLogo($this->tenant, 'light', UploadedFile::fake()->image('logo.png', 200, 100));

        $this->get($this->tenantUrl($this->tenant, '/api/v1/branding/logo/dark'))->assertNotFound();
        $this->get($this->tenantUrl($this->tenant, '/api/v1/branding/logo/favicon'))->assertNotFound();
    }

    public function test_central_host_is_404_even_with_x_tenant_or_query(): void
    {
        $this->uploadLogo($this->tenant, 'light', UploadedFile::fake()->image('logo.png', 200, 100));
        $central = 'http://'.config('tenancy.central_domains.0').'/api/v1/branding/logo/light';
        $key = (string) $this->tenant->getTenantKey();

        $this->get($central)->assertNotFound();
        $this->get($central, ['X-Tenant' => $key])->assertNotFound();
        $this->get($central.'?tenant='.$key)->assertNotFound();
    }

    public function test_platform_console_host_is_404(): void
    {
        $this->uploadLogo($this->tenant, 'light', UploadedFile::fake()->image('logo.png', 200, 100));

        $this->get('http://'.self::ADMIN_HOST.'/api/v1/branding/logo/light', ['X-Tenant' => (string) $this->tenant->getTenantKey()])
            ->assertNotFound();
    }

    public function test_unknown_host_is_404(): void
    {
        $this->get('http://no-such-shop.harness.test/api/v1/branding/logo/light')->assertNotFound();
    }

    public function test_x_tenant_header_never_selects_another_shop(): void
    {
        $other = $this->createTenant();
        $this->uploadLogo($this->tenant, 'light', UploadedFile::fake()->image('logo.png', 200, 100));

        // Tenant B's host asking for tenant A's logo through the header: B has none → 404.
        $this->get($this->tenantUrl($other, '/api/v1/branding/logo/light'), ['X-Tenant' => (string) $this->tenant->getTenantKey()])
            ->assertNotFound();
        $this->get($this->tenantUrl($other, '/api/v1/branding/logo/light?tenant='.$this->tenant->getTenantKey()))
            ->assertNotFound();
    }

    public function test_each_host_serves_its_own_tenant_logo(): void
    {
        $other = $this->createTenant();
        $shaA = $this->uploadLogo($this->tenant, 'light', UploadedFile::fake()->image('a.png', 200, 100));
        $shaB = $this->uploadLogo($other, 'light', UploadedFile::fake()->image('b.png', 300, 100));

        $this->assertNotSame($shaA, $shaB);
        $this->assertSame($shaA, hash('sha256', $this->body($this->get($this->tenantUrl($this->tenant, '/api/v1/branding/logo/light'))->assertOk())));
        $this->assertSame($shaB, hash('sha256', $this->body($this->get($this->tenantUrl($other, '/api/v1/branding/logo/light'))->assertOk())));
    }

    public function test_default_subdomain_host_without_a_domain_record_resolves(): void
    {
        config(['tenancy.central_domain' => 'shops.branding-base.test']);
        $sha256 = $this->uploadLogo($this->tenant, 'light', UploadedFile::fake()->image('logo.png', 200, 100));

        $response = $this->get('http://'.$this->tenant->slug.'.shops.branding-base.test/api/v1/branding/logo/light');

        $response->assertOk();
        $this->assertSame($sha256, hash('sha256', $this->body($response)));
    }

    public function test_tenant_that_is_not_ready_is_404(): void
    {
        $this->uploadLogo($this->tenant, 'light', UploadedFile::fake()->image('logo.png', 200, 100));

        $this->tenant->setAttribute('provisioning_status', 'pending');
        $this->tenant->save();

        $this->get($this->tenantUrl($this->tenant, '/api/v1/branding/logo/light'))->assertNotFound();
    }

    public function test_public_api_limiter_answers_429(): void
    {
        config(['rate_limits.public_api.per_minute' => 2]);
        $this->uploadLogo($this->tenant, 'light', UploadedFile::fake()->image('logo.png', 200, 100));
        $url = $this->tenantUrl($this->tenant, '/api/v1/branding/logo/light');

        $this->get($url)->assertOk();
        $this->get($url)->assertOk();
        $this->get($url)->assertStatus(429);
    }

    public function test_route_runs_outside_resolve_api_tenancy_with_both_throttles(): void
    {
        $route = Route::getRoutes()->getByName('api.branding.logo');
        $this->assertInstanceOf(RoutingRoute::class, $route);

        $middleware = $route->gatherMiddleware();
        $this->assertNotContains(ResolveApiTenancy::class, $middleware, 'No header may select the shop of the public logo.');
        $this->assertContains(ThrottleTenantMisses::class, $middleware);
        $this->assertContains('throttle:public-api', $middleware);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /** Upload through the real endpoint and return the stored sha256. */
    private function uploadLogo(Tenant $tenant, string $variant, UploadedFile $file): string
    {
        $this->post('/api/v1/settings/branding/logo/'.$variant, ['file' => $file], $this->tenantHeaders($tenant))->assertOk();

        return (string) $this->inTenant(
            $tenant,
            fn (): mixed => Media::query()->where('collection_name', 'logo_'.$variant)->latest('id')->firstOrFail()->getCustomProperty('sha256'),
        );
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function body(TestResponse $response): string
    {
        return (string) $response->baseResponse->getContent();
    }
}
