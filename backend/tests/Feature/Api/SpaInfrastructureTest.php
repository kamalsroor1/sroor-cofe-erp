<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class SpaInfrastructureTest extends TestCase
{
    public function test_spa_root_host_route_renders_successfully(): void
    {
        $response = $this->get('/spa');

        $response->assertStatus(200)
            ->assertSee('id="spa-app"', false);
    }

    public function test_spa_subpaths_fallback_to_spa_container_for_client_side_routing(): void
    {
        $response = $this->get('/spa/login');
        $response->assertStatus(200)
            ->assertSee('id="spa-app"', false);

        $responseDashboard = $this->get('/spa/dashboard');
        $responseDashboard->assertStatus(200)
            ->assertSee('id="spa-app"', false);
    }
}
