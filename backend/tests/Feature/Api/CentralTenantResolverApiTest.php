<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralTenantResolverApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Event::fake([
            \Stancl\Tenancy\Events\TenantCreated::class,
            \Stancl\Tenancy\Events\CreatingDatabase::class,
            \Stancl\Tenancy\Events\DatabaseCreated::class,
            \Stancl\Tenancy\Events\MigratingDatabase::class,
            \Stancl\Tenancy\Events\DatabaseMigrated::class,
        ]);
    }

    public function test_resolves_active_tenant_by_id(): void
    {
        $tenant = Tenant::create([
            'id'     => '2m',
            'name'   => '2M Coffee Roastery',
            'slug'   => '2m',
            'email'  => 'info@2m.com',
            'status' => 'active',
        ]);
        $tenant->domains()->create(['domain' => '2m.baraa-solutions.com']);

        $response = $this->getJson('/api/v1/central/tenants/resolve?code=2m');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'tenant_id'  => '2m',
                    'name'       => '2M Coffee Roastery',
                    'slug'       => '2m',
                    'domain'     => '2m.baraa-solutions.com',
                    'server_url' => 'https://2m.baraa-solutions.com',
                    'status'     => 'active',
                ],
            ]);
    }

    public function test_resolves_tenant_case_insensitively(): void
    {
        $tenant = Tenant::create([
            'id'     => 'wadi-elbon',
            'name'   => 'Wadi Elbon Roasters',
            'slug'   => 'wadi-elbon',
            'email'  => 'wadi@elbon.com',
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
            'id'     => 'test-cafe',
            'name'   => 'Test Cafe',
            'slug'   => 'test-cafe',
            'email'  => 'cafe@test.com',
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
            'id'     => 'suspended-shop',
            'name'   => 'Suspended Shop',
            'slug'   => 'suspended-shop',
            'email'  => 'suspended@shop.com',
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
            'id'     => 'alias-test',
            'name'   => 'Alias Cafe',
            'slug'   => 'alias-test',
            'email'  => 'alias@cafe.com',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/central/tenants/resolve?code=alias-test');

        $response->assertStatus(200)
            ->assertJsonPath('data.tenant_id', 'alias-test');
    }
}
