<?php

declare(strict_types=1);

namespace Tests\Feature\Entitlements;

use App\Models\Item;
use App\Models\Plan;
use App\Models\StoreStock;
use App\Models\Tenant;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * BIL-034 (#307): verifies the 4 gated feature families across the seeded catalog plans:
 *  - mixes.manage (/api/v1/mixes/*, /api/v1/coffee-blender/*)
 *  - purchases.reorder (/api/v1/purchases/smart-reorder)
 *  - reports.advanced (/api/v1/reports/items, /api/v1/reports/stores, /api/v1/reports/items/export,
 *    plus profit stripping on /reports/summary, /reports/comprehensive, /reports/inventory)
 *  - audit.logs (/api/v1/activity-logs, /api/v1/activity-logs/export-csv)
 */
#[Group('entitlements')]
final class CatalogPlanFeaturesGatingTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlansAndFeaturesSeeder::class);
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function catalogPlans(): iterable
    {
        yield 'free' => ['free', false];
        yield 'basic (Starter)' => ['basic', false];
        yield 'pro (Growth)' => ['pro', true];
        yield 'enterprise (Business)' => ['enterprise', true];
    }

    #[DataProvider('catalogPlans')]
    public function test_mixes_smart_reorder_detailed_profit_reports_and_activity_logs_follow_catalog_plan(
        string $planSlug,
        bool $shouldBeUnlocked,
    ): void {
        $plan = Plan::query()->where('slug', $planSlug)->firstOrFail();
        $tenant = $this->createTenant(['plan_id' => $plan->id, 'status' => 'active']);
        $headers = $this->tenantHeaders($tenant);
        $itemId = $this->stockedItemId($tenant, (int) $headers['X-Store-Id']);

        $mixPayload = [
            'target_weight_grams' => 1000,
            'components' => [['item_id' => $itemId, 'percentage' => 100, 'unit_price' => '250.000']],
        ];

        if ($shouldBeUnlocked) {
            // 1. Mixes & legacy coffee-blender
            $this->postJson('/api/v1/mixes/calculate', $mixPayload, $headers)->assertOk();
            $this->postJson('/api/v1/coffee-blender/calculate', $mixPayload, $headers)->assertOk();

            // 2. Smart reorder
            $this->getJson('/api/v1/purchases/smart-reorder', $headers)->assertOk();

            // 3. Detailed profit reports (dedicated endpoints + export + profit keys in summary & comprehensive)
            $this->getJson('/api/v1/reports/items', $headers)->assertOk()
                ->assertJsonStructure(['data', 'totals' => ['profit']]);
            $this->getJson('/api/v1/reports/stores', $headers)->assertOk();
            $this->get('/api/v1/reports/items/export', $headers)->assertOk();

            $summary = $this->getJson('/api/v1/reports/summary', $headers)->assertOk()->json('summary');
            foreach (['net_profit', 'total_cogs', 'margin_percentage'] as $key) {
                $this->assertArrayHasKey($key, $summary, "{$planSlug}: {$key}");
            }

            $comprehensive = $this->getJson('/api/v1/reports/comprehensive', $headers)->assertOk()->json();
            $this->assertSame([], $comprehensive['meta']['locked_sections']);
            $this->assertArrayHasKey('profit', $comprehensive['item_profits_totals']);
            $this->assertArrayHasKey('stock_cost_valuation', $comprehensive['inventory_data']);
            $this->assertArrayHasKey('expected_stock_profit', $comprehensive['inventory_data']);

            // 4. Activity logs
            $this->getJson('/api/v1/activity-logs', $headers)->assertOk();
            $this->get('/api/v1/activity-logs/export-csv', $headers)->assertOk();

            return;
        }

        // Starter / Free: every one of the 4 gated feature paths answers 403 subscription.feature_unavailable.
        $gatedRequests = [
            ['POST', '/api/v1/mixes/calculate', $mixPayload, 'mixes.manage'],
            ['POST', '/api/v1/coffee-blender/calculate', $mixPayload, 'mixes.manage'],
            ['GET', '/api/v1/purchases/smart-reorder', [], 'purchases.reorder'],
            ['GET', '/api/v1/reports/items', [], 'reports.advanced'],
            ['GET', '/api/v1/reports/stores', [], 'reports.advanced'],
            ['GET', '/api/v1/reports/items/export', [], $planSlug === 'free' ? 'reports.export' : 'reports.advanced'],
            ['GET', '/api/v1/activity-logs', [], 'audit.logs'],
            ['GET', '/api/v1/activity-logs/export-csv', [], 'audit.logs'],
        ];

        foreach ($gatedRequests as [$method, $uri, $payload, $feature]) {
            $this->json($method, $uri, $payload, $headers)
                ->assertStatus(403)
                ->assertJsonPath('success', false)
                ->assertJsonPath('error_code', 'subscription.feature_unavailable')
                ->assertJsonPath('details.feature', $feature);
        }

        // Detailed profit breakdown fields are also stripped from summary & comprehensive on Free / Starter.
        $summary = $this->getJson('/api/v1/reports/summary', $headers)->assertOk()->json('summary');
        foreach (['net_profit', 'total_cogs', 'margin_percentage', 'gross_cogs', 'expected_stock_profit'] as $key) {
            $this->assertArrayNotHasKey($key, $summary, "{$planSlug}: {$key} must be stripped");
        }

        $comprehensive = $this->getJson('/api/v1/reports/comprehensive', $headers)->assertOk()->json();
        $this->assertSame([], $comprehensive['item_profits']);
        $this->assertSame([], $comprehensive['item_profits_totals']);
        $this->assertSame([], $comprehensive['store_breakdown']);
        $this->assertSame(['item_profits', 'store_breakdown'], $comprehensive['meta']['locked_sections']);
        $this->assertArrayNotHasKey('stock_cost_valuation', $comprehensive['inventory_data']);
        $this->assertArrayNotHasKey('expected_stock_profit', $comprehensive['inventory_data']);
    }

    private function stockedItemId(Tenant $tenant, int $storeId): int
    {
        return $this->inTenant($tenant, function () use ($storeId): int {
            $item = Item::query()->create([
                'name' => 'بن اختبار الباقات',
                'code' => 'CAT-'.Str::upper(Str::random(6)),
                'unit' => 'كجم',
                'is_weighted' => true,
                'cost_price' => '100.000',
                'selling_price' => '180.000',
                'current_stock' => '25.000',
                'is_active' => true,
            ]);
            StoreStock::query()->create([
                'store_id' => $storeId,
                'item_id' => $item->id,
                'quantity' => '25.000',
            ]);

            return (int) $item->id;
        });
    }
}
