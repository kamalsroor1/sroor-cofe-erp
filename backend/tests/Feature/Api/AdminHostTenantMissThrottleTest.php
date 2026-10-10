<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TenantTestCase;

/**
 * W2 3F (IDEN-1.11 follow-up): the platform-console host is never a tenant lookup.
 *
 *  - config/tenancy.php merges CENTRAL_ADMIN_DOMAINS into tenancy.central_domains (after the
 *    existing entries, whose indexes some code reads);
 *  - ThrottleTenantMisses does not count admin-host requests as unknown-tenant misses (it did:
 *    the admin host was not a central domain, so every 404 there burnt the per-IP budget),
 *    while an explicit X-Tenant probe on that host is still counted.
 */
final class AdminHostTenantMissThrottleTest extends TenantTestCase
{
    private const ADMIN_HOST = 'admin.platform-3f.test';

    public function test_admin_domains_from_env_are_appended_to_the_central_domains(): void
    {
        $before = $_ENV['CENTRAL_ADMIN_DOMAINS'] ?? null;
        $beforeServer = $_SERVER['CENTRAL_ADMIN_DOMAINS'] ?? null;
        $_ENV['CENTRAL_ADMIN_DOMAINS'] = $_SERVER['CENTRAL_ADMIN_DOMAINS'] = ' Admin.Platform-3F.test , ,console.platform-3f.test';

        try {
            $central = (require config_path('tenancy.php'))['central_domains'];
        } finally {
            unset($_ENV['CENTRAL_ADMIN_DOMAINS'], $_SERVER['CENTRAL_ADMIN_DOMAINS']);
            if ($before !== null) {
                $_ENV['CENTRAL_ADMIN_DOMAINS'] = $before;
            }
            if ($beforeServer !== null) {
                $_SERVER['CENTRAL_ADMIN_DOMAINS'] = $beforeServer;
            }
        }

        $this->assertIsArray($central);
        $this->assertSame(array_values($central), $central, 'central_domains must stay a list');
        $this->assertSame(['127.0.0.1', 'localhost', 'baraa-solutions.com'], array_slice($central, 0, 3), 'existing indexes must not move');
        $this->assertContains('admin.platform-3f.test', $central);
        $this->assertContains('console.platform-3f.test', $central);
        $this->assertNotContains('', $central);
        $this->assertSame(count(array_unique($central)), count($central));
    }

    public function test_admin_host_requests_do_not_burn_the_tenant_miss_budget(): void
    {
        config(['central.admin_domains' => [self::ADMIN_HOST], 'rate_limits.tenant_resolve.per_minute' => 2]);

        // The tenant API is 404 on the admin host (ResolveApiTenancy): not a workspace probe.
        for ($i = 1; $i <= 4; $i++) {
            $this->getJson('http://'.self::ADMIN_HOST.'/api/v1/auth/options')->assertStatus(404);
        }
    }

    public function test_explicit_tenant_probes_on_the_admin_host_are_still_throttled(): void
    {
        config(['central.admin_domains' => [self::ADMIN_HOST], 'rate_limits.tenant_resolve.per_minute' => 2]);

        $this->getJson('http://'.self::ADMIN_HOST.'/api/v1/ping', ['X-Tenant' => 'guess-1'])->assertStatus(404);
        $this->getJson('http://'.self::ADMIN_HOST.'/api/v1/ping', ['X-Tenant' => 'guess-2'])->assertStatus(404);

        $this->getJson('http://'.self::ADMIN_HOST.'/api/v1/ping', ['X-Tenant' => 'guess-3'])
            ->assertStatus(429)
            ->assertJsonPath('message', __('auth.too_many_requests'));
    }

    public function test_public_workspace_resolver_is_not_counted_as_a_miss(): void
    {
        config(['rate_limits.tenant_resolve.per_minute' => 2]);

        // The resolver has its own `tenant-resolve` limiter; an unknown code never feeds the
        // tenant-miss bucket shared with the tenant API.
        for ($i = 1; $i <= 2; $i++) {
            $this->getJson('/api/v1/central/tenants/resolve?code=nope-'.$i.'&tenant=nope-'.$i);
        }

        $this->getJson('/api/v1/ping', ['X-Tenant' => 'guess-a'])->assertStatus(404);
    }
}
