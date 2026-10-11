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
 * The public workspace resolver (GET /api/v1/central/tenants/resolve) builds `server_url`
 * from configuration, never a hardcoded scheme or domain:
 *  - scheme (and port) from config('app.url'): http on the local *.test setup, https otherwise;
 *  - production is always https, whatever APP_URL says;
 *  - a tenant without a domain row falls back to "<id>.<tenancy.central_domain>".
 *
 * Tenant rows are created with the provisioning events faked: no tenant database is made.
 */
final class ResolveTenantWorkspaceUrlTest extends TenantTestCase
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

    private function makeTenant(string $id, ?string $domain): void
    {
        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Workspace '.$id,
            'slug' => $id,
            'email' => $id.'@workspace.test',
            'status' => 'active',
        ]);

        if ($domain !== null) {
            $tenant->domains()->create(['domain' => $domain]);
        }
    }

    public function test_server_url_uses_http_when_the_app_url_is_http(): void
    {
        config(['app.url' => 'http://sroor.test']);
        $this->makeTenant('local-shop', 'local-shop.sroor.test');

        $this->getJson('/api/v1/central/tenants/resolve?code=local-shop')
            ->assertOk()
            ->assertJsonPath('data.domain', 'local-shop.sroor.test')
            ->assertJsonPath('data.server_url', 'http://local-shop.sroor.test');
    }

    public function test_server_url_uses_https_when_the_app_url_is_https(): void
    {
        config(['app.url' => 'https://platform.example.test']);
        $this->makeTenant('secure-shop', 'secure-shop.platform.example.test');

        $this->getJson('/api/v1/central/tenants/resolve?code=secure-shop')
            ->assertOk()
            ->assertJsonPath('data.server_url', 'https://secure-shop.platform.example.test');
    }

    public function test_server_url_keeps_the_app_url_port(): void
    {
        config(['app.url' => 'http://localhost:8000']);
        $this->makeTenant('port-shop', 'port-shop.localhost');

        $this->getJson('/api/v1/central/tenants/resolve?code=port-shop')
            ->assertOk()
            ->assertJsonPath('data.server_url', 'http://port-shop.localhost:8000');
    }

    public function test_production_always_answers_https(): void
    {
        config(['app.url' => 'http://misconfigured.example.test']);
        $this->makeTenant('prod-shop', 'prod-shop.platform.example.test');

        $this->app['env'] = 'production';

        try {
            $this->getJson('/api/v1/central/tenants/resolve?code=prod-shop')
                ->assertOk()
                ->assertJsonPath('data.server_url', 'https://prod-shop.platform.example.test');
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_a_tenant_without_a_domain_falls_back_to_the_configured_central_domain(): void
    {
        config(['app.url' => 'http://sroor.test', 'tenancy.central_domain' => 'sroor.test']);
        $this->makeTenant('bare-shop', null);

        $this->getJson('/api/v1/central/tenants/resolve?code=bare-shop')
            ->assertOk()
            ->assertJsonPath('data.domain', 'bare-shop.sroor.test')
            ->assertJsonPath('data.server_url', 'http://bare-shop.sroor.test');
    }

    public function test_an_unknown_workspace_is_still_404(): void
    {
        config(['app.url' => 'http://sroor.test']);

        $this->getJson('/api/v1/central/tenants/resolve?code=missing-shop')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }
}
