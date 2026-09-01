<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdminUser;
    protected string $superAdminToken;
    protected User $unauthorizedUser;
    protected string $unauthorizedToken;
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

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $superRole = Role::findByName('super_admin');

        $this->store = Store::create([
            'name'      => 'المحمصة المركزية',
            'code'      => 'CENTRAL-01',
            'type'      => 'retail',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $this->superAdminUser = User::factory()->create([
            'name'             => 'سوبر أدمن المنصة',
            'phone'            => '01000000001',
            'email'            => 'superadmin@sroor.com',
            'password'         => Hash::make('password123'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->superAdminUser->assignRole($superRole);
        $this->superAdminToken = $this->superAdminUser->createToken('super-admin-token')->plainTextToken;

        $this->unauthorizedUser = User::factory()->create([
            'name'             => 'مستخدم عادي',
            'phone'            => '01000000000',
            'email'            => 'user@sroor.com',
            'password'         => Hash::make('password123'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->unauthorizedToken = $this->unauthorizedUser->createToken('unauth-token')->plainTextToken;

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

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/super-admin/dashboard');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_super_admin(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unauthorizedToken)
            ->getJson('/api/v1/super-admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_can_get_super_admin_dashboard_metrics(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/super-admin/dashboard');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['metrics', 'plan_stats', 'recent_tenants', 'system_info']);
    }

    public function test_can_get_tenants_and_provision_new_tenant(): void
    {
        $getResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
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

        $postResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->postJson('/api/v1/super-admin/tenants', $payload);

        $postResponse->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tenants', [
            'slug'  => 'wadi-elbon',
            'email' => 'wadi@elbon.com',
        ]);
    }

    public function test_store_tenant_fails_validation_on_missing_fields(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->postJson('/api/v1/super-admin/tenants', [
                'name' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'slug', 'email', 'plan_id', 'password']);
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
        $statusResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->postJson("/api/v1/super-admin/tenants/{$tenant->id}/toggle-status", [
                'status'      => 'suspended',
                'extend_days' => 0,
            ]);

        $statusResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('suspended', $tenant->fresh()->status);

        // Override Feature
        $featureResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->postJson("/api/v1/super-admin/tenants/{$tenant->id}/override-feature", [
                'feature_key' => 'custom_branding',
            ]);

        $featureResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_can_get_plans_and_update_plan(): void
    {
        $getResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
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

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->putJson("/api/v1/super-admin/plans/{$this->plan->id}", $updatePayload);

        $updateResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('باقة المحامص الذهبية المطورة', $this->plan->fresh()->name);
        $this->assertEquals(20, $this->plan->fresh()->max_users);
    }

    public function test_can_get_and_update_platform_settings(): void
    {
        $getResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/super-admin/settings');

        $getResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->postJson('/api/v1/super-admin/settings', [
                'platform_name'     => 'منظومة سرور كلاود ERP',
                'platform_subtitle' => 'المنصة السحابية الموحدة للمحامص والمقاهي',
                'support_email'     => 'support@sroor-erp.com',
                'support_phone'     => '01012316954',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'platform_name' => 'منظومة سرور كلاود ERP',
                ],
            ]);

        $this->assertEquals('منظومة سرور كلاود ERP', Setting::get('platform_name'));
    }

    public function test_can_get_and_update_system_units(): void
    {
        $getResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/super-admin/units');

        $getResponse->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['units']);

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->postJson('/api/v1/super-admin/units', [
                'units' => ['كجم', 'جرام', 'شيكارة', 'علبة', 'طرد', 'دستة', 'باكت'],
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('كجم,جرام,شيكارة,علبة,طرد,دستة,باكت', Setting::get('global_system_units'));
    }
}
