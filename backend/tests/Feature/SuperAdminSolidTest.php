<?php

namespace Tests\Feature;

use App\Actions\Plans\UpdatePlanAction;
use App\Actions\Tenants\GetTenantsIndexDataAction;
use App\Actions\Tenants\OverrideTenantFeatureAction;
use App\Actions\Tenants\ToggleTenantStatusAction;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\Catalog\PlanCatalog;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\MigratingDatabase;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TestCase;

class SuperAdminSolidTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected Plan $basicPlan;

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

        $role = Role::firstOrCreate(['name' => 'admin']);
        $this->superAdmin = User::factory()->create([
            'name' => 'مدير المنصة المركزي',
            'email' => 'super@test.com',
            'phone' => '01000000000',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole($role);

        $this->basicPlan = Plan::create([
            'name' => 'الباقة الأساسية',
            'slug' => 'basic',
            'description' => 'باقة المحلات الفردية',
            'price_monthly' => '499.000',
            'price_yearly' => '4990.000',
            'max_users' => 3,
            'max_stores' => 1,
            'max_items' => 500,
            'max_invoices_per_month' => 2000,
            'is_active' => true,
            'features' => ['pos.access' => true, 'mixes.manage' => false],
        ]);
    }

    public function test_tenants_index_action_with_pipeline_filters(): void
    {
        Tenant::create([
            'id' => 'cairo-market',
            'name' => 'سوبر ماركت القاهرة',
            'slug' => 'cairo-market',
            'plan_id' => $this->basicPlan->id,
            'email' => 'cairo@market.test',
            'phone' => '01011111111',
            'status' => 'active',
        ]);

        Tenant::create([
            'id' => 'alex-spices',
            'name' => 'عطارة الإسكندرية',
            'slug' => 'alex-spices',
            'plan_id' => $this->basicPlan->id,
            'email' => 'alex@spices.test',
            'phone' => '01022222222',
            'status' => 'suspended',
        ]);

        $action = app(GetTenantsIndexDataAction::class);

        // Test Filter 1: Status = active
        $requestActive = Request::create('/admin/super/tenants', 'GET', ['status' => 'active']);
        app()->instance('request', $requestActive);
        $dataActive = $action->execute($requestActive);
        $this->assertEquals(1, $dataActive['tenants']->total());
        $this->assertEquals('سوبر ماركت القاهرة', $dataActive['tenants']->items()[0]['name']);

        // Test Filter 2: Search = الإسكندرية
        $requestSearch = Request::create('/admin/super/tenants', 'GET', ['search' => 'الإسكندرية']);
        app()->instance('request', $requestSearch);
        $dataSearch = $action->execute($requestSearch);
        $this->assertEquals(1, $dataSearch['tenants']->total());
        $this->assertEquals('عطارة الإسكندرية', $dataSearch['tenants']->items()[0]['name']);
    }

    public function test_toggle_tenant_status_action(): void
    {
        $tenant = Tenant::create([
            'id' => 'test-store',
            'name' => 'متجر تجريبي',
            'slug' => 'test-store',
            'plan_id' => $this->basicPlan->id,
            'email' => 'store@test.com',
            'status' => 'active',
        ]);

        $action = app(ToggleTenantStatusAction::class);
        $action->execute($tenant, 'suspended', 0);

        $tenant->refresh();
        $this->assertEquals('suspended', $tenant->status);
    }

    public function test_override_tenant_feature_action(): void
    {
        $tenant = Tenant::create([
            'id' => 'test-store-2',
            'name' => 'متجر تجريبي 2',
            'slug' => 'test-store-2',
            'plan_id' => $this->basicPlan->id,
            'email' => 'store2@test.com',
            'status' => 'active',
            'enabled_features' => [],
        ]);

        $action = app(OverrideTenantFeatureAction::class);
        $action->execute($tenant, 'mixes.manage');

        $tenant->refresh();
        $this->assertContains('mixes.manage', $tenant->enabled_features);

        // Toggle again should remove override
        $action->execute($tenant, 'mixes.manage');
        $tenant->refresh();
        $this->assertNotContains('mixes.manage', $tenant->enabled_features);
    }

    public function test_update_plan_action(): void
    {
        $action = app(UpdatePlanAction::class);
        $action->execute($this->basicPlan, [
            'name' => 'الباقة الأساسية بلس',
            'price_monthly' => 599.000,
            'price_yearly' => 5990.000,
            'max_users' => 5,
            'max_stores' => 2,
            'max_items' => 1000,
            'max_invoices_per_month' => 5000,
            'is_active' => true,
            'is_popular' => true,
            'features' => ['pos.access' => true, 'mixes.manage' => true],
        ]);

        $this->basicPlan->refresh();
        $this->assertEquals('الباقة الأساسية بلس', $this->basicPlan->name);
        $this->assertEquals(599.0, (float) $this->basicPlan->price_monthly);
        $this->assertTrue($this->basicPlan->is_popular);
        $this->assertTrue($this->basicPlan->features['mixes.manage']);
    }

    public function test_super_admin_plan_edits_survive_the_catalog_seeder(): void
    {
        // ENTI-1.8: the seeder runs on every deploy; it may add missing feature keys but
        // must never overwrite what the super-admin edited.
        app(UpdatePlanAction::class)->execute($this->basicPlan, [
            'name' => 'الباقة الأساسية المعدلة',
            'price_monthly' => '525.000',
            'max_users' => 7,
            'max_items' => null,
            'features' => ['pos.access' => true, 'mixes.manage' => true, 'reports.advanced' => true],
        ]);

        $this->seed(PlansAndFeaturesSeeder::class);
        $this->seed(PlansAndFeaturesSeeder::class);

        $plan = Plan::query()->findOrFail($this->basicPlan->id);
        $this->assertSame('الباقة الأساسية المعدلة', $plan->name);
        $this->assertSame('525.000', $plan->price_monthly);
        $this->assertSame(7, $plan->max_users);
        $this->assertNull($plan->max_items);
        $this->assertTrue($plan->features['mixes.manage'], 'An edited feature value is kept.');
        $this->assertTrue($plan->features['reports.advanced'], 'An edited feature value is kept.');
        $this->assertArrayHasKey('quotations.manage', $plan->features, 'Missing catalog keys are merged in.');
        $this->assertEqualsCanonicalizing(PlanCatalog::SLUGS, Plan::query()->whereIn('slug', PlanCatalog::SLUGS)->pluck('slug')->all(), 'The missing catalog plans are created, the edited one is not duplicated.');
    }
}
