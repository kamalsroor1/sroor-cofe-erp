<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Plan $plan;
    protected Store $store;

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

        $role = Role::create(['name' => 'super_admin']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'super_admin.access']);
        $role->givePermissionTo('super_admin.access');

        $this->store = Store::create([
            'name'      => 'المحمصة المركزية',
            'code'      => 'CENTRAL-01',
            'type'      => 'retail',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'name'             => 'سوبر أدمن المنصة',
            'phone'            => '01000000001',
            'email'            => 'superadmin@sroor.com',
            'password'         => Hash::make('password123'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($role);
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;

        $this->plan = Plan::create([
            'name'                   => 'باقة المحامص الاحترافية',
            'slug'                   => 'pro-roastery',
            'price_monthly'          => '500.000',
            'price_yearly'           => '5000.000',
            'max_users'              => 10,
            'max_stores'             => 3,
            'max_items'              => 500,
            'max_invoices_per_month' => 5000,
            'is_active'              => true,
            'is_popular'             => true,
            'sort_order'             => 1,
            'features'               => ['coffee_blender' => true, 'smart_reorder' => true],
        ]);
    }

    public function test_can_get_super_admin_dashboard_metrics(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/super-admin/dashboard');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['metrics', 'plan_stats', 'recent_tenants']);
    }

    public function test_can_get_tenants_and_provision_new_tenant(): void
    {
        $getResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/super-admin/tenants');

        $getResponse->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['plans', 'tenants']);

        $payload = [
            'name'       => 'محمصة وادي البن',
            'slug'       => 'wadi-elbon',
            'email'      => 'wadi@elbon.com',
            'phone'      => '01011112222',
            'password'   => 'secret1234',
            'plan_id'    => $this->plan->id,
            'trial_days' => 14,
        ];

        $postResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/super-admin/tenants', $payload);

        $postResponse->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tenants', [
            'slug'  => 'wadi-elbon',
            'email' => 'wadi@elbon.com',
        ]);
    }

    public function test_can_toggle_tenant_status_and_override_feature(): void
    {
        $tenant = Tenant::create([
            'id'      => 'test-tenant-01',
            'name'    => 'مطاحن الفخامة',
            'slug'    => 'fakhamah-roast',
            'email'   => 'fakhamah@roast.com',
            'plan_id' => $this->plan->id,
            'status'  => 'active',
        ]);

        // Toggle Status
        $statusResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/super-admin/tenants/{$tenant->id}/toggle-status", [
                'status'      => 'suspended',
                'extend_days' => 0,
            ]);

        $statusResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('suspended', $tenant->fresh()->status);

        // Override Feature
        $featureResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/super-admin/tenants/{$tenant->id}/override-feature", [
                'feature_key' => 'custom_branding',
            ]);

        $featureResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_can_get_plans_and_update_plan(): void
    {
        $getResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/super-admin/plans');

        $getResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $updatePayload = [
            'name'                   => 'باقة المحامص الذهبية المطورة',
            'price_monthly'          => 750.00,
            'price_yearly'           => 7500.00,
            'max_users'              => 20,
            'max_stores'             => 5,
            'max_items'              => 1000,
            'max_invoices_per_month' => 10000,
            'is_active'              => true,
            'is_popular'             => true,
            'features'               => ['coffee_blender' => true, 'smart_reorder' => true, 'pos_offline' => true],
        ];

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson("/api/v1/super-admin/plans/{$this->plan->id}", $updatePayload);

        $updateResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('باقة المحامص الذهبية المطورة', $this->plan->fresh()->name);
        $this->assertEquals(20, $this->plan->fresh()->max_users);
    }
}
