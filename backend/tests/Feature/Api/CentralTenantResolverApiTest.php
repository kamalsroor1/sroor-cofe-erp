<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\MigratingDatabase;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TenantTestCase;

/**
 * Central, public route: it never initialises tenancy. QA-4: runs on the tenant harness
 * base class, so the central database holds ONLY central tables (as in production); the
 * tenant rows are created with the provisioning events faked, so no tenant database is made.
 */
class CentralTenantResolverApiTest extends TenantTestCase
{
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
    }

    public function test_resolves_active_tenant_by_id(): void
    {
        config(['app.url' => 'https://baraa-solutions.com']);

        $tenant = Tenant::create([
            'id' => '2m',
            'name' => '2M Coffee Roastery',
            'slug' => '2m',
            'email' => 'info@2m.com',
            'status' => 'active',
        ]);
        $tenant->domains()->create(['domain' => '2m.baraa-solutions.com']);

        $response = $this->getJson('/api/v1/central/tenants/resolve?code=2m');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'tenant_id' => '2m',
                    'name' => '2M Coffee Roastery',
                    'slug' => '2m',
                    'domain' => '2m.baraa-solutions.com',
                    'server_url' => 'https://2m.baraa-solutions.com',
                    'status' => 'active',
                ],
            ]);
    }

    public function test_resolves_tenant_case_insensitively(): void
    {
        $tenant = Tenant::create([
            'id' => 'wadi-elbon',
            'name' => 'Wadi Elbon Roasters',
            'slug' => 'wadi-elbon',
            'email' => 'wadi@elbon.com',
            'status' => 'active',
        ]);
        $tenant->domains()->create(['domain' => 'wadi-elbon.baraa-solutions.com']);

        $response = $this->getJson('/api/v1/central/tenants/resolve?code=WADI-ELBON');

        $response->assertStatus(200)
            ->assertJsonPath('data.tenant_id', 'wadi-elbon')
            ->assertJsonPath('data.name', 'Wadi Elbon Roasters');
    }

    public function test_resolves_tenant_using_tenant_query_parameter(): void
    {
        $tenant = Tenant::create([
            'id' => 'test-cafe',
            'name' => 'Test Cafe',
            'slug' => 'test-cafe',
            'email' => 'cafe@test.com',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/central/tenants/resolve?tenant=test-cafe');

        $response->assertStatus(200)
            ->assertJsonPath('data.tenant_id', 'test-cafe');
    }

    public function test_returns_404_when_tenant_does_not_exist(): void
    {
        $response = $this->getJson('/api/v1/central/tenants/resolve?code=non-existent-shop');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_returns_403_when_tenant_is_suspended(): void
    {
        Tenant::create([
            'id' => 'suspended-shop',
            'name' => 'Suspended Shop',
            'slug' => 'suspended-shop',
            'email' => 'suspended@shop.com',
            'status' => 'suspended',
        ]);

        $response = $this->getJson('/api/v1/central/tenants/resolve?code=suspended-shop');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_returns_422_when_code_is_missing(): void
    {
        $response = $this->getJson('/api/v1/central/tenants/resolve');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_direct_unversioned_alias_route_works(): void
    {
        Tenant::create([
            'id' => 'alias-test',
            'name' => 'Alias Cafe',
            'slug' => 'alias-test',
            'email' => 'alias@cafe.com',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/central/tenants/resolve?code=alias-test');

        $response->assertStatus(200)
            ->assertJsonPath('data.tenant_id', 'alias-test');
    }

    public function test_resolving_one_workspace_while_sending_another_tenants_header_returns_only_the_requested_one(): void
    {
        Tenant::create(['id' => 'alpha-shop', 'name' => 'Alpha Shop', 'slug' => 'alpha-shop', 'email' => 'alpha@shop.test', 'status' => 'active']);
        $beta = Tenant::create(['id' => 'beta-shop', 'name' => 'Beta Shop', 'slug' => 'beta-shop', 'email' => 'beta@shop.test', 'status' => 'active']);
        $beta->domains()->create(['domain' => 'beta-shop.baraa-solutions.com']);

        $response = $this->getJson('/api/v1/central/tenants/resolve?code=beta-shop', ['X-Tenant' => 'alpha-shop']);

        $response->assertStatus(200)
            ->assertJsonPath('data.tenant_id', 'beta-shop')
            ->assertJsonPath('data.name', 'Beta Shop');

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('alpha', strtolower($body), 'The resolver leaked the X-Tenant workspace.');
        $this->assertStringNotContainsString('tenancy_db', $body, 'The resolver exposed tenant database settings.');
        $this->assertStringNotContainsString('beta@shop.test', $body, 'The resolver exposed the tenant contact email.');
        $this->assertFalse(tenancy()->initialized, 'The central resolver must never initialise tenancy.');
    }

    /**
     * AUTH-2 + CTO W1 Q1 (2026-10-09): the public workspace resolver is rate limited
     * (tenant-resolve, 30/min per IP, was 10) so workspace codes cannot be enumerated.
     */
    public function test_tenant_resolve_budget_is_thirty_per_minute(): void
    {
        $this->assertSame(30, config('rate_limits.tenant_resolve.per_minute'));
    }

    public function test_v1_resolver_is_throttled_after_thirty_requests_per_minute(): void
    {
        $this->assertResolverThrottled('/api/v1/central/tenants/resolve');
    }

    /**
     * AUTH-2: the unversioned alias must carry the same limiter; it is easy to forget.
     */
    public function test_unversioned_resolver_alias_is_throttled_after_thirty_requests_per_minute(): void
    {
        $this->assertResolverThrottled('/api/central/tenants/resolve');
    }

    private function assertResolverThrottled(string $path): void
    {
        $budget = (int) config('rate_limits.tenant_resolve.per_minute');

        for ($i = 1; $i <= $budget; $i++) {
            $this->getJson($path.'?code=probe-'.$i)->assertStatus(404);
        }

        $this->getJson($path.'?code=probe-'.($budget + 1))
            ->assertStatus(429)
            ->assertJsonPath('message', __('auth.too_many_requests'));

        $this->assertNotSame('auth.too_many_requests', __('auth.too_many_requests'), 'auth.too_many_requests lang key must exist');
    }
}
