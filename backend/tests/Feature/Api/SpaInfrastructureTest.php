<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TenantTestCase;

class SpaInfrastructureTest extends TenantTestCase
{
    public function test_spa_root_host_route_renders_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee('id="app"', false);
    }

    public function test_spa_subpaths_fallback_to_spa_container_for_client_side_routing(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200)
            ->assertSee('id="app"', false);

        $responseDashboard = $this->get('/dashboard');
        $responseDashboard->assertStatus(200)
            ->assertSee('id="app"', false);
    }

    public function test_spa_shell_renders_on_a_tenant_host(): void
    {
        $tenant = $this->createTenant();

        foreach (['/', '/login', '/dashboard'] as $path) {
            $this->get($this->tenantUrl($tenant, $path))
                ->assertStatus(200)
                ->assertSee('id="app"', false);
        }
    }

    public function test_unknown_api_path_on_a_tenant_host_is_a_json_404_not_the_spa_shell(): void
    {
        $tenant = $this->createTenant();

        $response = $this->getJson($this->tenantUrl($tenant, '/api/v1/does-not-exist'), ['X-Tenant' => (string) $tenant->getTenantKey()]);

        $response->assertNotFound();
        $this->assertStringNotContainsString('id="app"', (string) $response->getContent());
    }

    public function test_spa_shell_of_one_tenant_never_embeds_another_tenants_identity(): void
    {
        $tenantA = $this->createTenant(['name' => 'محمصة ألفا للاختبار']);
        $tenantB = $this->createTenant(['name' => 'محمصة بيتا للاختبار']);

        $content = (string) $this->get($this->tenantUrl($tenantA, '/login'))->assertOk()->getContent();

        $this->assertStringNotContainsString('محمصة بيتا للاختبار', $content);
        $this->assertStringNotContainsString((string) $tenantB->getTenantKey(), $content);
    }
}
