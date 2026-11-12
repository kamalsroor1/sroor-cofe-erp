<?php

declare(strict_types=1);

namespace Tests\Feature\Entitlements;

use App\Enums\Billing\SubscriptionAddonStatus;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use Database\Seeders\Catalog\FeatureCatalog;
use Database\Seeders\Catalog\PlanCatalog;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * BILL-03 / PLAN-01: profit figures need `reports.advanced`; the "control" bundle add-on turns on
 * reports.advanced + audit.logs + purchases.reorder; the Starter plan has transfers and no limits
 * on items / monthly invoices.
 */
#[Group('entitlements')]
final class ProfitReportsGatingApiTest extends TenantTestCase
{
    private const PROFIT_KEYS = ['net_profit', 'gross_profit', 'total_cogs'];

    public function test_a_shop_without_reports_advanced_gets_no_profit_fields(): void
    {
        $tenant = $this->tenantOn($this->plan(['reports.advanced' => false]));

        $summary = $this->getJson('/api/v1/reports/summary', $this->tenantHeaders($tenant))->assertOk()->json('summary');
        foreach (self::PROFIT_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $summary, $key);
        }
        $this->assertArrayHasKey('total_sales', $summary);

        $bundle = $this->getJson('/api/v1/reports/comprehensive', $this->tenantHeaders($tenant))->assertOk()->json();
        foreach (self::PROFIT_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $bundle['summary'], $key);
        }
        $this->assertArrayNotHasKey('profit', $bundle['item_profits_totals']);
        $this->assertArrayHasKey('total_revenue', $bundle['item_profits_totals']);

        $this->getJson('/api/v1/reports/top-items', $this->tenantHeaders($tenant))->assertOk();
    }

    public function test_a_shop_with_reports_advanced_keeps_the_profit_fields(): void
    {
        $tenant = $this->tenantOn($this->plan(['reports.advanced' => true]));

        $summary = $this->getJson('/api/v1/reports/summary', $this->tenantHeaders($tenant))->assertOk()->json('summary');
        foreach (self::PROFIT_KEYS as $key) {
            $this->assertArrayHasKey($key, $summary, $key);
        }
    }

    public function test_a_super_admin_override_restores_the_profit_fields(): void
    {
        $tenant = $this->tenantOn($this->plan(['reports.advanced' => false]), ['enabled_features' => ['reports.advanced']]);

        $this->getJson('/api/v1/reports/summary', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonStructure(['summary' => ['net_profit']]);
    }

    public function test_the_control_bundle_switches_on_all_three_features(): void
    {
        $plan = $this->plan(['reports.advanced' => false, 'audit.logs' => false, 'purchases.reorder' => false]);
        $tenant = $this->tenantOn($plan);

        $this->getJson('/api/v1/purchases/smart-reorder', $this->tenantHeaders($tenant))->assertStatus(403);
        $this->getJson('/api/v1/activity-logs', $this->tenantHeaders($tenant))->assertStatus(403);

        $bundle = Addon::query()->create([
            'key' => 'bundle.control.test', 'name_key' => 'plans.addons.control_bundle.name', 'type' => 'recurring',
            'feature_keys' => ['reports.advanced', 'audit.logs', 'purchases.reorder'],
            'unit_price' => '199.000', 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);
        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->getKey(), 'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'status' => 'active',
            'amount' => '449.000', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
        ]);
        $line = SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->getKey(), 'addon_id' => $bundle->getKey(), 'quantity' => 1, 'unit_price' => '199.000',
            'billing_cycle' => 'monthly', 'status' => SubscriptionAddonStatus::Active, 'starts_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/purchases/smart-reorder', $this->tenantHeaders($tenant))->assertOk();
        $this->getJson('/api/v1/reports/summary', $this->tenantHeaders($tenant))->assertJsonStructure(['summary' => ['net_profit']]);

        // Cancelling the bundle locks all three again.
        $line->update(['status' => SubscriptionAddonStatus::Cancelled, 'ends_at' => now()->subMinute()]);
        app(\App\Services\Entitlements\TenantEntitlementService::class)->forget((string) $tenant->getKey());

        $this->getJson('/api/v1/purchases/smart-reorder', $this->tenantHeaders($tenant))->assertStatus(403);
    }

    public function test_the_catalog_matches_the_new_structure(): void
    {
        foreach (['basic', 'pro', 'enterprise'] as $slug) {
            $this->assertNull(PlanCatalogProbe::value($slug, 'max_items'), "{$slug} has no item limit");
            $this->assertNull(PlanCatalogProbe::value($slug, 'max_invoices_per_month'), "{$slug} has no monthly invoice limit");
        }

        $this->assertTrue(PlanCatalog::features('basic')['transfers.manage']);
        $this->assertFalse(PlanCatalog::features('basic')['reports.advanced']);
        $this->assertTrue(PlanCatalog::features('pro')['mixes.manage']);
        $this->assertContains('reports.advanced', array_diff(FeatureCatalog::keys(), FeatureCatalog::hiddenKeys()));
    }

    public function test_the_unauthenticated_caller_is_401(): void
    {
        $tenant = $this->tenantOn($this->plan(['reports.advanced' => false]));

        $this->getJson('/api/v1/reports/summary', $this->tenantGuestHeaders($tenant))->assertStatus(401);
    }

    private function tenantOn(Plan $plan, array $attributes = []): Tenant
    {
        return $this->createTenant(array_merge(['plan_id' => $plan->id], $attributes));
    }

    /**
     * @param  array<string, bool>  $features  overrides of the "everything on" feature map
     */
    private function plan(array $features = []): Plan
    {
        $slug = 'pg-'.Str::lower(Str::random(10));

        return Plan::query()->create([
            'name' => $slug, 'slug' => $slug, 'price_monthly' => '449.000', 'price_yearly' => '4490.000',
            'features' => array_merge(array_fill_keys(FeatureCatalog::keys(), true), $features),
            'max_users' => null, 'max_stores' => null, 'max_warehouses' => null, 'max_vans' => null,
            'max_items' => null, 'is_active' => true, 'sort_order' => 1,
        ]);
    }
}

/** Reads a plan attribute straight from the approved catalog. */
final class PlanCatalogProbe
{
    public static function value(string $slug, string $column): mixed
    {
        foreach (PlanCatalog::all() as $plan) {
            if ($plan['slug'] === $slug) {
                return $plan[$column];
            }
        }

        return 'missing';
    }
}
