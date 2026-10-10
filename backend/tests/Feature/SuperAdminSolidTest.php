<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Plans\UpdatePlanAction;
use App\Actions\Tenants\GetTenantsIndexDataAction;
use App\Actions\Tenants\OverrideTenantFeatureAction;
use App\Actions\Tenants\ToggleTenantStatusAction;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Exceptions\TenantLifecycleException;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Support\Tenancy\TenantSuspensionReason;
use Database\Seeders\Catalog\PlanCatalog;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Http\Request;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * Super-admin actions called directly (no HTTP). IDEN-1.8: the acting operator is an
 * App\Models\CentralUser (the old fixture was a tenant-style User with the `admin` role) and
 * every tenant here is a central row only (no database is created for it).
 */
class SuperAdminSolidTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    protected CentralUser $superAdmin;

    protected Plan $basicPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = $this->centralSuperAdmin([
            'name' => 'مدير المنصة المركزي',
            'email' => 'super@test.com',
        ]);

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

    /** @param  array<string, mixed>  $attributes */
    private function rowTenant(array $attributes): Tenant
    {
        return Tenant::create(array_merge([
            'plan_id' => $this->basicPlan->id,
            'status' => TenantStatus::Active->value,
            'tenancy_create_database' => false,
        ], $attributes));
    }

    public function test_tenants_index_action_with_pipeline_filters(): void
    {
        $this->rowTenant([
            'id' => 'cairo-market',
            'name' => 'سوبر ماركت القاهرة',
            'slug' => 'cairo-market',
            'email' => 'cairo@market.test',
            'phone' => '01011111111',
        ]);

        $this->rowTenant([
            'id' => 'alex-spices',
            'name' => 'عطارة الإسكندرية',
            'slug' => 'alex-spices',
            'email' => 'alex@spices.test',
            'phone' => '01022222222',
            'status' => TenantStatus::Suspended->value,
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
        $tenant = $this->rowTenant([
            'id' => 'test-store',
            'name' => 'متجر تجريبي',
            'slug' => 'test-store',
            'email' => 'store@test.com',
        ]);

        $returned = app(ToggleTenantStatusAction::class)->execute(
            (string) $tenant->id,
            TenantStatus::Suspended,
            $this->superAdmin,
            0,
            TenantSuspensionReason::CustomerRequest,
        );

        $this->assertSame(TenantStatus::Suspended->value, $returned->status);
        $tenant->refresh();
        $this->assertEquals('suspended', $tenant->status);

        // Through the state machine: one lifecycle event caused by the central operator.
        $event = TenantLifecycleEvent::query()->where('tenant_id', 'test-store')->sole();
        $this->assertSame(TenantLifecycleActor::SuperAdmin, $event->actor);
        $this->assertSame($this->superAdmin->id, $event->central_user_id);
    }

    public function test_toggle_tenant_status_action_never_activates_a_tenant(): void
    {
        $tenant = $this->rowTenant([
            'id' => 'blocked-store',
            'name' => 'متجر موقوف',
            'slug' => 'blocked-store',
            'email' => 'blocked@test.com',
            'status' => TenantStatus::Suspended->value,
        ]);

        try {
            app(ToggleTenantStatusAction::class)->execute((string) $tenant->id, TenantStatus::Active, $this->superAdmin);
            $this->fail('A super-admin must not activate a tenant: only a verified payment does.');
        } catch (TenantLifecycleException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame('subscription.invalid_transition', $e->errorCode());
        }

        $this->assertSame(TenantStatus::Suspended->value, $tenant->fresh()?->status);
        $this->assertSame(0, TenantLifecycleEvent::query()->where('tenant_id', 'blocked-store')->count());
    }

    public function test_override_tenant_feature_action(): void
    {
        $tenant = $this->rowTenant([
            'id' => 'test-store-2',
            'name' => 'متجر تجريبي 2',
            'slug' => 'test-store-2',
            'email' => 'store2@test.com',
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
            'price_monthly' => '599.000',
            'price_yearly' => '5990.000',
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
        $this->assertSame('599.000', $this->basicPlan->price_monthly);
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
