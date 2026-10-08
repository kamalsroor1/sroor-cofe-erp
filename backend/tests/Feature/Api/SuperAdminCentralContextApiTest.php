<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsureCentralContext;
use App\Http\Middleware\ResolveApiTenancy;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\MigratingDatabase;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TestCase;

/**
 * P0-AUTH-5: the /api/v1/super-admin/* control plane is reachable ONLY in central context.
 * A request arriving on a tenant host (or that would initialise tenancy) must get 404,
 * regardless of who is holding the token.
 */
class SuperAdminCentralContextApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCentralPlatformRoles;

    private const TENANT_HOST_URL = 'http://acme.tenant-host.test';

    protected Store $store;

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

        $this->seed(PermissionsSeeder::class);
        $this->seedCentralPlatformRoles();

        $this->store = Store::create([
            'name' => 'الفرع الرئيسي',
            'code' => 'MAIN',
            'is_main' => true,
            'is_active' => true,
        ]);
    }

    private function makeUser(string $phone, string $role): User
    {
        $user = User::factory()->create([
            'name' => 'مستخدم '.$role,
            'phone' => $phone,
            'email' => $role.$phone.'@sroor.test',
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    /** @return array<string, array{string}> */
    public static function superAdminGetEndpoints(): array
    {
        return [
            'dashboard' => ['/api/v1/super-admin/dashboard'],
            'tenants' => ['/api/v1/super-admin/tenants'],
            'plans' => ['/api/v1/super-admin/plans'],
            'settings' => ['/api/v1/super-admin/settings'],
        ];
    }

    public function test_guest_on_central_host_gets_401(): void
    {
        $this->getJson('/api/v1/super-admin/dashboard')->assertStatus(401);
    }

    public function test_guest_on_tenant_host_gets_404(): void
    {
        $this->getJson(self::TENANT_HOST_URL.'/api/v1/super-admin/dashboard')->assertStatus(404);
    }

    #[DataProvider('superAdminGetEndpoints')]
    public function test_super_admin_token_on_tenant_host_gets_404(string $uri): void
    {
        $super = $this->makeUser('01000007025', 'super_admin');

        $this->withHeaders($this->bearer($super))
            ->getJson(self::TENANT_HOST_URL.$uri)
            ->assertStatus(404);
    }

    public function test_super_admin_token_on_tenant_host_cannot_mutate_tenants(): void
    {
        $super = $this->makeUser('01000007026', 'super_admin');
        $plan = Plan::create([
            'name' => 'باقة أساسية',
            'slug' => 'basic-central-ctx',
            'price_monthly' => '100.000',
            'price_yearly' => '1000.000',
            'max_users' => 5,
            'max_stores' => 1,
            'max_items' => 100,
            'max_invoices_per_month' => 1000,
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
            'features' => [],
        ]);
        $tenant = Tenant::create([
            'id' => 'victim-tenant',
            'name' => 'مستأجر ضحية',
            'slug' => 'victim-tenant',
            'email' => 'victim@sroor.test',
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $headers = $this->bearer($super);

        $this->withHeaders($headers)
            ->postJson(self::TENANT_HOST_URL."/api/v1/super-admin/tenants/{$tenant->id}/toggle-status", [
                'status' => 'suspended',
                'extend_days' => 0,
            ])
            ->assertStatus(404);

        $this->withHeaders($headers)
            ->postJson(self::TENANT_HOST_URL."/api/v1/super-admin/tenants/{$tenant->id}/override-feature", [
                'feature_key' => 'custom_branding',
            ])
            ->assertStatus(404);

        $this->assertSame('active', $tenant->fresh()->status);
    }

    public function test_super_admin_with_unknown_x_tenant_header_gets_404(): void
    {
        $super = $this->makeUser('01000007027', 'super_admin');

        $this->withHeaders($this->bearer($super) + ['X-Tenant' => 'no-such-tenant'])
            ->getJson('/api/v1/super-admin/dashboard')
            ->assertStatus(404);
    }

    public function test_super_admin_on_central_host_gets_200(): void
    {
        $super = $this->makeUser('01000007028', 'super_admin');
        $headers = $this->bearer($super);

        $this->withHeaders($headers)
            ->getJson('/api/v1/super-admin/dashboard')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_cashier_and_tenant_admin_on_central_host_get_403(): void
    {
        foreach (['cashier' => '01000007029', 'admin' => '01000007030'] as $role => $phone) {
            $user = $this->makeUser($phone, $role);
            $headers = $this->bearer($user);

            $this->withHeaders($headers)
                ->getJson('/api/v1/super-admin/dashboard')
                ->assertStatus(403);

            $this->withHeaders($headers)
                ->getJson('/api/v1/super-admin/tenants')
                ->assertStatus(403);
        }
    }

    public function test_route_list_has_no_resolve_api_tenancy(): void
    {
        $names = [
            'api.super_admin.dashboard',
            'api.super_admin.tenants',
            'api.super_admin.tenants.store',
            'api.super_admin.tenants.toggle_status',
            'api.super_admin.tenants.update_db_config',
            'api.super_admin.tenants.run_migrations',
            'api.super_admin.plans.update',
            'api.super_admin.settings.update',
            'api.super_admin.app_versions.store',
        ];

        foreach ($names as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "route {$name} must exist");

            $middleware = $route->gatherMiddleware();
            $this->assertContains(EnsureCentralContext::class, $middleware, "{$name} must run EnsureCentralContext");
            $this->assertNotContains(ResolveApiTenancy::class, $middleware, "{$name} must not run ResolveApiTenancy");
        }
    }
}
