<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Models\Setting;
use App\Services\Branding\PlatformBranding;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * BRND-3: GET /api/v1/branding — public (before login), throttle:public-api.
 *
 * Allowlist only: platform {name, short_name, subtitle, logos, favicon, app_icon,
 * primary_color, website_url, support_email, support_phone} and, when the request resolved
 * a shop, tenant {name, subtitle, logos, theme_color}. Never secrets, legal ids, contact data.
 */
#[Group('harness')]
final class BrandingApiTest extends TenantTestCase
{
    private const PLATFORM_KEYS = ['name', 'short_name', 'subtitle', 'logos', 'favicon', 'app_icon', 'primary_color', 'website_url', 'support_email', 'support_phone'];

    private const TENANT_KEYS = ['name', 'subtitle', 'logos', 'theme_color'];

    public function test_central_request_returns_the_platform_brand_and_no_tenant(): void
    {
        app(PlatformBranding::class)->update(['name' => 'منصة التجزئة', 'primary_color' => '#112233', 'legal_name' => 'Legal Co LLC']);

        $response = $this->getJson('http://'.config('tenancy.central_domains.0').'/api/v1/branding');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.platform.name', 'منصة التجزئة')
            ->assertJsonPath('data.platform.primary_color', '#112233')
            ->assertJsonPath('data.tenant', null);

        $this->assertSame(self::PLATFORM_KEYS, array_keys((array) $response->json('data.platform')));
        $this->assertSame(['light', 'dark'], array_keys((array) $response->json('data.platform.logos')));
        $this->assertStringNotContainsString('Legal Co LLC', (string) $response->getContent());
    }

    public function test_tenant_request_adds_the_public_shop_brand_only(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, function (): void {
            Setting::set('company_name', 'محمصة الأمل');
            Setting::set('company_subtitle', 'بن طازج يوميا');
            Setting::set('system_theme_color', 'teal');
            Setting::set('telegram_bot_token', '123456:VERY-SECRET');
            Setting::set('telegram_chat_id', '-100777');
            Setting::set('company_phone', '01000000999');
            Setting::set('commercial_register', 'CR-55555');
        });

        $response = $this->getJson('/api/v1/branding', ['X-Tenant' => (string) $tenant->getTenantKey()]);

        $response->assertOk()
            ->assertJsonPath('data.tenant.name', 'محمصة الأمل')
            ->assertJsonPath('data.tenant.subtitle', 'بن طازج يوميا')
            ->assertJsonPath('data.tenant.theme_color', 'teal')
            ->assertJsonPath('data.tenant.logos.light', null)
            ->assertJsonPath('data.tenant.logos.dark', null);

        $this->assertSame(self::TENANT_KEYS, array_keys((array) $response->json('data.tenant')));

        $body = (string) $response->getContent();
        foreach (['VERY-SECRET', '-100777', '01000000999', 'CR-55555', 'telegram'] as $leak) {
            $this->assertStringNotContainsString($leak, $body);
        }
    }

    public function test_tenant_resolved_by_host_gets_its_logo_url(): void
    {
        $tenant = $this->createTenant();
        $this->post('/api/v1/settings/branding/logo/light', ['file' => UploadedFile::fake()->image('a.png', 100, 100)], $this->tenantHeaders($tenant))->assertOk();

        $light = (string) $this->getJson($this->tenantUrl($tenant, '/api/v1/branding'))
            ->assertOk()
            ->json('data.tenant.logos.light');

        $this->assertStringStartsWith('http://'.$this->tenantDomain($tenant).'/api/v1/branding/logo/light?v=', $light);
    }

    public function test_shop_name_falls_back_to_the_tenant_name(): void
    {
        $tenant = $this->createTenant(['name' => 'Fallback Roastery']);
        // The tenant settings migration seeds a generic company_name; a shop that clears it gets its tenant name.
        $this->inTenant($tenant, fn () => Setting::set('company_name', ''));

        $this->getJson('/api/v1/branding', ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertOk()
            ->assertJsonPath('data.tenant.name', 'Fallback Roastery')
            ->assertJsonPath('data.tenant.theme_color', 'emerald');
    }

    public function test_tenants_never_see_each_others_brand(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();
        $this->inTenant($a, fn () => Setting::set('company_name', 'محل أ'));
        $this->inTenant($b, fn () => Setting::set('company_name', 'محل ب'));
        $this->post('/api/v1/settings/branding/logo/light', ['file' => UploadedFile::fake()->image('a.png', 100, 100)], $this->tenantHeaders($a))->assertOk();

        $this->getJson('/api/v1/branding', ['X-Tenant' => (string) $b->getTenantKey()])
            ->assertOk()
            ->assertJsonPath('data.tenant.name', 'محل ب')
            ->assertJsonPath('data.tenant.logos.light', null);
    }

    public function test_unknown_tenant_is_404(): void
    {
        $this->getJson('/api/v1/branding', ['X-Tenant' => 'no-such-shop'])->assertNotFound();
    }

    public function test_platform_console_host_never_resolves_a_tenant(): void
    {
        config(['central.admin_domains' => ['console.branding-admin.test']]);
        $tenant = $this->createTenant();

        // ResolveApiTenancy refuses any tenant selection on the admin host.
        $this->getJson('http://console.branding-admin.test/api/v1/branding', ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertNotFound();

        // api.branding is one of the central-safe ResolveApiTenancy::ADMIN_HOST_ROUTES: platform brand only.
        $this->getJson('http://console.branding-admin.test/api/v1/branding')
            ->assertOk()
            ->assertJsonPath('data.tenant', null);
    }

    public function test_public_api_limiter_answers_429(): void
    {
        config(['rate_limits.public_api.per_minute' => 2]);
        $url = 'http://'.config('tenancy.central_domains.0').'/api/v1/branding';

        $this->getJson($url)->assertOk();
        $this->getJson($url)->assertOk();
        $this->getJson($url)->assertStatus(429);
    }

    public function test_is_public_and_needs_no_token(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/branding', $this->tenantGuestHeaders($tenant))->assertOk();
    }
}
