<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Entitlements\TenantEntitlementService;
use Database\Seeders\Catalog\PlanCatalog;
use Illuminate\Database\Migrations\Migration;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * SEC-06 follow-up: tenants on Basic when the catalog tightened keep transfers.manage,
 * mixes.manage, purchases.reorder and audit.logs as super-admin overrides; nobody else does.
 */
#[Group('billing')]
final class GrandfatherBasicTenantsMigrationTest extends TenantTestCase
{
    private const GRANDFATHERED = ['audit.logs', 'mixes.manage', 'purchases.reorder', 'transfers.manage'];

    private function runUp(): void
    {
        $migration = require database_path('migrations/2026_11_13_100100_grandfather_basic_tenants_feature_overrides.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $migration->up();
    }

    private function plan(string $slug): Plan
    {
        return Plan::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'features' => PlanCatalog::features($slug),
            'max_users' => 3,
            'max_stores' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    /** @return list<string> */
    private function features(Tenant $tenant): array
    {
        return app(TenantEntitlementService::class)->resolve((string) $tenant->id)->features;
    }

    public function test_existing_basic_tenants_get_the_four_overrides_and_others_do_not(): void
    {
        $basic = $this->plan('basic');
        $pro = $this->plan('pro');

        $onBasic = $this->createTenant(['plan_id' => $basic->id]);
        $withOverride = $this->createTenant(['plan_id' => $basic->id, 'enabled_features' => ['api.access', 'blender.access']]);
        $onPro = $this->createTenant(['plan_id' => $pro->id, 'enabled_features' => ['api.access']]);

        foreach (self::GRANDFATHERED as $key) {
            $this->assertNotContains($key, $this->features($onBasic), 'Basic does not include it by plan.');
        }

        $this->runUp();

        $this->assertEqualsCanonicalizing(
            ['transfers.manage', 'mixes.manage', 'purchases.reorder', 'audit.logs'],
            Tenant::query()->findOrFail($onBasic->id)->enabled_features,
        );
        foreach (self::GRANDFATHERED as $key) {
            $this->assertContains($key, $this->features($onBasic), 'Entitlements cache must be fresh after the migration.');
        }

        $overrides = Tenant::query()->findOrFail($withOverride->id)->enabled_features;
        $this->assertSame(['api.access', 'blender.access', 'transfers.manage', 'purchases.reorder', 'audit.logs'], $overrides, 'Existing overrides are kept; the legacy alias of mixes.manage is not duplicated.');

        $this->assertSame(['api.access'], Tenant::query()->findOrFail($onPro->id)->enabled_features, 'Other plans are untouched.');
        $this->assertNotContains('mixes.manage', $this->features($onPro), 'Pro keeps its own catalog (no mixes.manage).');
    }

    public function test_it_is_idempotent_and_new_basic_subscribers_do_not_get_the_features(): void
    {
        $basic = $this->plan('basic');
        $existing = $this->createTenant(['plan_id' => $basic->id]);

        $this->runUp();
        $this->runUp();

        $this->assertCount(4, Tenant::query()->findOrFail($existing->id)->enabled_features);

        $newcomer = $this->createTenant(['plan_id' => $basic->id]);
        foreach (self::GRANDFATHERED as $key) {
            $this->assertNotContains($key, $this->features($newcomer));
        }
        $this->assertSame([], Tenant::query()->findOrFail($newcomer->id)->enabled_features ?? []);
    }

    public function test_without_a_basic_plan_it_does_nothing(): void
    {
        $tenant = $this->createTenant();

        $this->runUp();

        $this->assertSame([], Tenant::query()->findOrFail($tenant->id)->enabled_features ?? []);
    }
}
