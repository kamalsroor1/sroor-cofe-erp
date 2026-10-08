<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Services\Branding\PlatformBranding;
use Illuminate\Support\Facades\Cache;
use Tests\TenantTestCase;

/**
 * BRND-1 acceptance: a platform rename done in central context (super-admin) is
 * visible inside a tenant request (`/system/context` with X-Tenant) on the very
 * next request, without waiting for any cache TTL.
 *
 * Before BRND-1 the platform name lived in the tenant `settings` table / an
 * implicitly-scoped cache, so a central change never reached tenant requests.
 */
final class PlatformRenameReachesTenantContextTest extends TenantTestCase
{
    public function test_central_rename_is_visible_in_the_next_tenant_request(): void
    {
        Cache::flush();
        config(['branding.name' => 'Neutral Platform']);
        $tenant = $this->createTenant();

        // First request primes the branding cache inside the tenant context.
        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.system.platform_name', 'Neutral Platform');

        // Super-admin renames the platform from the central context.
        $this->assertFalse(tenancy()->initialized);
        $this->app->make(PlatformBranding::class)->update(['name' => 'منصة جديدة']);

        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.system.platform_name', 'منصة جديدة');
    }

    public function test_rename_reaches_every_tenant(): void
    {
        Cache::flush();
        $a = $this->createTenant();
        $b = $this->createTenant();

        $this->getJson('/api/v1/system/context', $this->tenantHeaders($a))->assertOk();
        $this->getJson('/api/v1/system/context', $this->tenantHeaders($b))->assertOk();

        $this->app->make(PlatformBranding::class)->update(['name' => 'Shared Platform']);

        foreach ([$a, $b] as $tenant) {
            $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
                ->assertOk()
                ->assertJsonPath('data.system.platform_name', 'Shared Platform');
        }
    }
}
