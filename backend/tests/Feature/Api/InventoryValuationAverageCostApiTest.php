<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Actions\Dashboard\GetTenantDashboardAnalyticsAction;
use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PurchaseService;
use App\Services\StockTransferService;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * BUG-02: stock is valued at ONE weighted average cost per item (CarryingCost), the cost the sales
 * are costed at, never at the last purchase price (items.cost_price).
 *
 * Card scenario: 78 on hand at 180, then 5 bought at 200. The average is
 * (78 x 180 + 5 x 200) / 83 = 15040 / 83 = 181.2048... -> 181.205 (half-up, scale 3, as
 * PurchaseService stores it), so the item is worth 83 x 181.205 = 15040.015, not 83 x 200 = 16600.
 * The 0.015 is the rounding of the stored average (at most qty x 0.0005).
 */
#[Group('mysql')]
#[Group('mariadb-reports')]
final class InventoryValuationAverageCostApiTest extends TenantTestCase
{
    private Tenant $tenant;

    /** @var array<string, string> */
    private array $headers;

    private int $storeId;

    private int $itemId;

    private int $supplierId;

    private int $customerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->headers = $this->tenantHeaders($this->tenant);

        $this->inTenant($this->tenant, function (): void {
            $this->itemId = $this->makeItem('BUG02-CUP', '78.000', '180.000', '180.000', $this->storeId);
            $this->supplierId = (int) Supplier::create(['name' => 'مورد التقييم', 'phone' => '01000009101', 'current_balance' => '0.000', 'is_active' => true])->id;
            $this->customerId = (int) Customer::create(['name' => 'عميل التقييم', 'phone' => '01000009102', 'price_tier' => 'retail', 'is_active' => true])->id;
        });
    }

    public function test_a_purchase_at_a_new_price_values_the_stock_at_the_weighted_average_not_the_last_price(): void
    {
        $this->assertValuation('14040.000');

        $this->purchase('5.000', '200.000');

        $this->inTenant($this->tenant, function (): void {
            $item = Item::query()->findOrFail($this->itemId);
            $this->assertSame('83.000', (string) $item->current_stock);
            $this->assertSame('181.205', (string) $item->weighted_avg_cost);
            $this->assertSame('200.000', (string) $item->cost_price);
        });

        // 83 x 181.205 = 15040.015 (83 x 200 would be 16600.000); selling 83 x 260 = 21580.
        $inventory = $this->getJson('/api/v1/reports/inventory?period=today', $this->headers)->assertOk()
            ->assertJsonPath('data.stock_cost_valuation', '15040.015')
            ->assertJsonPath('data.stock_selling_valuation', '21580.000')
            ->assertJsonPath('data.expected_stock_profit', '6539.985');
        $row = $this->rowFor($inventory->json('data.items'), 'id', $this->itemId);
        $this->assertSame('83.000', $row['current_stock']);
        $this->assertSame('181.205', $row['average_cost']);
        $this->assertSame('200.000', $row['last_purchase_price']);
        $this->assertSame('200.000', $row['cost_price']);
        $this->assertSame('15040.015', $row['cost_val']);
        $this->assertSame('6539.985', $row['profit']);

        $this->getJson('/api/v1/reports/summary?period=today', $this->headers)->assertOk()
            ->assertJsonPath('summary.stock_cost_valuation', '15040.015')
            ->assertJsonPath('summary.stock_selling_valuation', '21580.000')
            ->assertJsonPath('summary.expected_stock_profit', '6539.985');

        // The reports dashboard bundle (summary + inventory tab) and the items screen KPI.
        $this->getJson('/api/v1/reports/comprehensive?period=today', $this->headers)->assertOk()
            ->assertJsonPath('summary.stock_cost_valuation', '15040.015')
            ->assertJsonPath('summary.expected_stock_profit', '6539.985')
            ->assertJsonPath('inventory_data.stock_cost_valuation', '15040.015');
        $this->getJson('/api/v1/items', $this->headers)->assertOk()
            ->assertJsonPath('summary.total_stock_value', '15040.015');

        // Branch balances.
        $stock = $this->rowFor($this->getJson('/api/v1/stores/stocks?store_id='.$this->storeId, $this->headers)->assertOk()->json('data'), 'item_id', $this->itemId);
        $this->assertSame('15040.015', $stock['total_valuation']);
        $this->assertSame('181.205', $stock['average_cost']);
        $this->assertSame('200.000', $stock['last_purchase_price']);
    }

    public function test_the_item_api_exposes_the_average_cost_and_the_last_purchase_price_separately(): void
    {
        $this->purchase('5.000', '200.000');

        $this->getJson('/api/v1/items/'.$this->itemId, $this->headers)->assertOk()
            ->assertJsonPath('data.average_cost', '181.205')
            ->assertJsonPath('data.last_purchase_price', '200.000')
            ->assertJsonPath('data.cost_price', '200.000');

        $row = $this->rowFor($this->getJson('/api/v1/items', $this->headers)->assertOk()->json('data'), 'id', $this->itemId);
        $this->assertSame('181.205', $row['average_cost']);
        $this->assertSame('200.000', $row['last_purchase_price']);

        $posRow = $this->rowFor($this->getJson('/api/v1/pos/bootstrap', $this->headers)->assertOk()->json('data.items'), 'id', $this->itemId);
        $this->assertSame('181.205', $posRow['average_cost']);
        $this->assertSame('200.000', $posRow['last_purchase_price']);

        $gridRow = $this->rowFor($this->getJson('/api/v1/pos/items', $this->headers)->assertOk()->json('data'), 'id', $this->itemId);
        $this->assertSame('181.205', $gridRow['average_cost']);
        $this->assertSame('200.000', $gridRow['last_purchase_price']);

        $this->getJson('/api/v1/pos/lookup?code=BUG02-CUP', $this->headers)->assertOk()
            ->assertJsonPath('data.item.average_cost', '181.205')
            ->assertJsonPath('data.item.last_purchase_price', '200.000');

        $this->getJson('/api/v1/items/'.$this->itemId.'/movements', $this->headers)->assertOk()
            ->assertJsonPath('data.item.average_cost', '181.205')
            ->assertJsonPath('data.item.last_purchase_price', '200.000');
    }

    public function test_a_user_without_items_view_cost_sees_neither_cost_figure(): void
    {
        $this->purchase('5.000', '200.000');

        $viewer = $this->createTenantUser($this->tenant, null, ['items.view', 'pos.access']);
        $headers = $this->tenantHeaders($this->tenant, $viewer);

        $this->getJson('/api/v1/items/'.$this->itemId, $headers)->assertOk()
            ->assertJsonPath('data.average_cost', null)
            ->assertJsonPath('data.last_purchase_price', null)
            ->assertJsonPath('data.cost_price', null);

        $index = $this->getJson('/api/v1/items', $headers)->assertOk()->assertJsonPath('summary.total_stock_value', null);
        $row = $this->rowFor($index->json('data'), 'id', $this->itemId);
        $this->assertNull($row['average_cost']);
        $this->assertNull($row['last_purchase_price']);
        $this->assertNull($row['cost_price']);

        foreach ([
            $this->rowFor($this->getJson('/api/v1/pos/bootstrap', $headers)->assertOk()->json('data.items'), 'id', $this->itemId),
            $this->rowFor($this->getJson('/api/v1/pos/items', $headers)->assertOk()->json('data'), 'id', $this->itemId),
            $this->getJson('/api/v1/pos/lookup?code=BUG02-CUP', $headers)->assertOk()->json('data.item'),
            $this->rowFor($this->getJson('/api/v1/stores/stocks?store_id='.$this->storeId, $headers)->assertOk()->json('data'), 'item_id', $this->itemId),
        ] as $payload) {
            $this->assertArrayHasKey('average_cost', $payload);
            $this->assertNull($payload['average_cost']);
            $this->assertNull($payload['last_purchase_price']);
            $this->assertNull($payload['cost_price']);
        }

        $stock = $this->rowFor($this->getJson('/api/v1/stores/stocks?store_id='.$this->storeId, $headers)->json('data'), 'item_id', $this->itemId);
        $this->assertNull($stock['total_valuation']);

        // Low stock radar, the item ledger header and both dashboards.
        $lowId = $this->inTenant($this->tenant, fn (): int => $this->makeItem('BUG02-LOW', '0.500', '50.000', '40.000', $this->storeId, minStock: '5.000'));
        $low = $this->rowFor($this->getJson('/api/v1/items/low-stock', $headers)->assertOk()->json('low_items'), 'id', $lowId);
        $this->assertArrayHasKey('average_cost', $low);
        $this->assertNull($low['average_cost']);
        $this->assertNull($low['last_purchase_price']);
        $this->assertNull($low['cost_price']);

        $this->getJson('/api/v1/items/'.$this->itemId.'/movements', $headers)->assertOk()
            ->assertJsonPath('data.item.average_cost', null)
            ->assertJsonPath('data.item.last_purchase_price', null)
            ->assertJsonPath('data.item.cost_price', null);

        // GET /dashboard low_stock_items never carries a cost key, for anyone.
        $dashLow = $this->rowFor($this->getJson('/api/v1/dashboard', $headers)->assertOk()->json('data.low_stock_items'), 'id', $lowId);
        $this->assertArrayNotHasKey('average_cost', $dashLow);
        $this->assertArrayNotHasKey('last_purchase_price', $dashLow);
        $this->assertArrayNotHasKey('cost_price', $dashLow);

        $analyticsLow = $this->dashboardAnalyticsLowStockRow($viewer, $lowId);
        $this->assertNull($analyticsLow['average_cost']);
        $this->assertNull($analyticsLow['last_purchase_price']);
        $this->assertNull($analyticsLow['cost_price']);
    }

    public function test_low_stock_payloads_carry_the_real_cost_figures_for_a_user_with_items_view_cost(): void
    {
        // Average 40 (zero-free), last purchase 50: the two names must not be confused.
        $lowId = $this->inTenant($this->tenant, fn (): int => $this->makeItem('BUG02-LOW', '0.500', '50.000', '40.000', $this->storeId, minStock: '5.000'));

        $low = $this->rowFor($this->getJson('/api/v1/items/low-stock', $this->headers)->assertOk()->json('low_items'), 'id', $lowId);
        $this->assertSame('40.000', $low['average_cost']);
        $this->assertSame('50.000', $low['last_purchase_price']);
        $this->assertSame('50.000', $low['cost_price']);

        $admin = $this->inTenant($this->tenant, fn (): User => User::query()->orderBy('id')->firstOrFail());
        $analyticsLow = $this->dashboardAnalyticsLowStockRow($admin, $lowId);
        $this->assertSame('40.000', $analyticsLow['average_cost']);
        $this->assertSame('50.000', $analyticsLow['last_purchase_price']);
        $this->assertSame('50.000', $analyticsLow['cost_price']);
    }

    /**
     * Review fix: /reports/inventory and /reports/comprehensive are gated by reports.view only.
     * Without items.view_cost the PER-UNIT costs are null (rows and ABC); the totals and the
     * per-row values stay (owner decision recorded by the coordinator).
     */
    public function test_a_reports_role_without_items_view_cost_gets_no_per_unit_cost(): void
    {
        $this->purchase('5.000', '200.000');

        $reporter = $this->inTenant($this->tenant, function (): User {
            $role = Role::findOrCreate('مراجع تقارير BUG02', 'web');
            $role->syncPermissions(['reports.view']);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return User::query()->findOrFail($this->createTenantUser($this->tenant, 'مراجع تقارير BUG02')->id);
        });
        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => $reporter->can('items.view_cost')));
        $headers = $this->tenantHeaders($this->tenant, $reporter);

        // The admin reads first, so the ABC cache is warm with the unmasked rows.
        $adminInventory = $this->getJson('/api/v1/reports/inventory?period=today', $this->headers)->assertOk();
        $this->assertSame('181.205', $this->rowFor($adminInventory->json('data.abc_data.items'), 'id', $this->itemId)['unit_cost']);

        $inventory = $this->getJson('/api/v1/reports/inventory?period=today', $headers)->assertOk()
            ->assertJsonPath('data.stock_cost_valuation', '15040.015')
            ->assertJsonPath('data.expected_stock_profit', '6539.985');
        $this->assertNoUnitCost($inventory->json('data'));

        $comprehensive = $this->getJson('/api/v1/reports/comprehensive?period=today', $headers)->assertOk()
            ->assertJsonPath('summary.stock_cost_valuation', '15040.015')
            ->assertJsonPath('inventory_data.stock_cost_valuation', '15040.015');
        $this->assertNoUnitCost($comprehensive->json('inventory_data'));

        // The masked read did not poison the shared cache.
        $again = $this->getJson('/api/v1/reports/inventory?period=today', $this->headers)->assertOk();
        $row = $this->rowFor($again->json('data.items'), 'id', $this->itemId);
        $this->assertSame('181.205', $row['average_cost']);
        $this->assertSame('200.000', $row['last_purchase_price']);
        $this->assertSame('181.205', $this->rowFor($again->json('data.abc_data.items'), 'id', $this->itemId)['unit_cost']);
        $this->assertSame('181.205', $this->rowFor($again->json('data.abc_data.dead_stock'), 'id', $this->itemId)['unit_cost']);
    }

    public function test_abc_analysis_falls_back_to_the_last_purchase_cost_when_the_average_is_zero(): void
    {
        $zeroId = $this->inTenant($this->tenant, fn (): int => $this->makeItem('BUG02-ABC0', '10.000', '50.000', '0.000', $this->storeId));

        $abc = $this->getJson('/api/v1/reports/inventory?period=today', $this->headers)->assertOk()->json('data.abc_data');
        $row = $this->rowFor($abc['items'], 'id', $zeroId);
        $this->assertSame('50.000', $row['unit_cost']);
        $this->assertSame('500.000', $row['stock_value']);
        $this->assertSame('50.000', $this->rowFor($abc['dead_stock'], 'id', $zeroId)['unit_cost']);
    }

    /**
     * @param  array<string, mixed>  $inventory
     */
    private function assertNoUnitCost(array $inventory): void
    {
        $row = $this->rowFor($inventory['items'], 'id', $this->itemId);
        $this->assertSame('15040.015', $row['cost_val']);
        foreach (['average_cost', 'last_purchase_price', 'cost_price'] as $key) {
            $this->assertArrayHasKey($key, $row);
            $this->assertNull($row[$key], $key);
        }
        foreach (['items', 'dead_stock'] as $list) {
            $this->assertNotEmpty($inventory['abc_data'][$list]);
            foreach ($inventory['abc_data'][$list] as $abcRow) {
                $this->assertArrayHasKey('unit_cost', $abcRow);
                $this->assertNull($abcRow['unit_cost'], "abc_data.{$list}.unit_cost");
            }
        }
    }

    /**
     * GetTenantDashboardAnalyticsAction's low_stock_items row (POSItemResource) for $user.
     *
     * @return array<string, mixed>
     */
    private function dashboardAnalyticsLowStockRow(User $user, int $itemId): array
    {
        return $this->inTenant($this->tenant, function () use ($user, $itemId): array {
            $this->actingAs(User::query()->findOrFail($user->id));

            return $this->rowFor(app(GetTenantDashboardAnalyticsAction::class)->execute(User::query()->findOrFail($user->id))['low_stock_items'], 'id', $itemId);
        });
    }

    public function test_cancelling_the_purchase_restores_the_valuation(): void
    {
        $purchaseId = $this->purchase('5.000', '200.000');
        $this->assertValuation('15040.015');

        $this->inTenant($this->tenant, function () use ($purchaseId): void {
            $this->actAsAdmin();
            app(PurchaseService::class)->cancelPurchase(Purchase::query()->findOrFail($purchaseId), 'BUG-02');

            $item = Item::query()->findOrFail($this->itemId);
            $this->assertSame('78.000', (string) $item->current_stock);
            $this->assertSame('180.000', (string) $item->weighted_avg_cost);
            $this->assertSame('180.000', (string) $item->cost_price);
        });

        $this->assertValuation('14040.000');
        $stock = $this->rowFor($this->getJson('/api/v1/stores/stocks?store_id='.$this->storeId, $this->headers)->json('data'), 'item_id', $this->itemId);
        $this->assertSame('14040.000', $stock['total_valuation']);
    }

    /**
     * value before + purchases - cost of goods sold + cost of returned goods = value after, at every
     * step and over the whole run. A transfer only moves value between branches.
     *
     * Tolerance: weighted_avg_cost is stored at scale 3 (half-up), so each step that re-computes
     * the average (a purchase, a purchase cancel taking the formula path) may move the value by at
     * most quantity-after x 0.0005. Every other step must be exact. Here: purchase +0.035 (81 x
     * 181.235 vs 14680), cancel -0.007 (74 x 179.967 vs 13317.565).
     */
    public function test_the_valuation_invariant_holds_through_sale_return_transfer_purchase_and_cancel(): void
    {
        $storeB = $this->inTenant($this->tenant, fn (): int => (int) Store::query()->create(['name' => 'فرع التقييم ب', 'code' => 'BUG02-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id);
        $headersB = $this->tenantHeaders($this->tenant, null, $storeB);

        $value = $this->totalValuation();
        $this->assertSame('14040.000', $value);
        $start = $value;

        // 1. Sale of 3 at 260: COGS 3 x 180 = 540 (exact).
        $sale = $this->sell('3.000');
        $value = $this->assertStep($value, '13500.000', cogs: '540.000', bound: '0');

        // 2. Sales return of 1 of that line at its cost 180 (exact).
        $this->salesReturn($sale['invoice_id'], $sale['line_id'], '1.000')->assertCreated();
        $value = $this->assertStep($value, '13680.000', returned: '180.000', bound: '0');

        // 3. Transfer 10 to branch B: the total does not move; A 66 x 180, B 10 x 180.
        $this->transfer($storeB, '10.000');
        $value = $this->assertStep($value, '13680.000', bound: '0');
        $this->assertBranch($this->headers, '11880.000');
        $this->assertBranch($headersB, '1800.000');

        // 4. Purchase 5 at 200: average 14680 / 81 = 181.2345... -> 181.235; 81 x 181.235.
        $purchaseId = $this->purchase('5.000', '200.000');
        $value = $this->assertStep($value, '14680.035', purchased: '1000.000', bound: Decimal::mul('81', '0.0005'));
        $this->assertBranch($this->headers, '12867.685'); // 71 x 181.235
        $this->assertBranch($headersB, '1812.350');       // 10 x 181.235

        // 5. Sale of 2 after the purchase: COGS 2 x 181.235 = 362.470 (exact on the stored average).
        $this->sell('2.000');
        $value = $this->assertStep($value, '14317.565', cogs: '362.470', bound: '0');

        // 6. Cancel the purchase (a sale came after it, so the moving-average formula):
        //    (79 x 181.235 - 5 x 200) / 74 = 179.96709... -> 179.967; 74 x 179.967.
        $this->inTenant($this->tenant, function () use ($purchaseId): void {
            $this->actAsAdmin();
            app(PurchaseService::class)->cancelPurchase(Purchase::query()->findOrFail($purchaseId), 'BUG-02 invariant');
        });
        $value = $this->assertStep($value, '13317.558', purchased: '-1000.000', bound: Decimal::mul('74', '0.0005'));
        $this->assertBranch($this->headers, '11517.888'); // 64 x 179.967
        $this->assertBranch($headersB, '1799.670');       // 10 x 179.967

        // Whole run, against the COGS the reports book (net of the return): 540 + 362.47 - 180.
        $this->getJson('/api/v1/reports/summary?period=today&store_id=all', $this->headers)->assertOk()
            ->assertJsonPath('summary.total_cogs', '722.470');
        $expected = bcsub($start, '722.470', 3); // purchases net to 0 (bought and cancelled)
        $this->assertSame('13317.530', $expected);
        $this->assertWithin($expected, $value, bcadd(Decimal::mul('81', '0.0005'), Decimal::mul('74', '0.0005'), 4));
    }

    public function test_a_zero_average_falls_back_to_the_last_purchase_cost(): void
    {
        $zeroId = $this->inTenant($this->tenant, fn (): int => $this->makeItem('BUG02-ZERO', '10.000', '50.000', '0.000', $this->storeId));

        $inventory = $this->getJson('/api/v1/reports/inventory?period=today', $this->headers)->assertOk()
            ->assertJsonPath('data.stock_cost_valuation', '14540.000'); // 14040 + 10 x 50
        $row = $this->rowFor($inventory->json('data.items'), 'id', $zeroId);
        $this->assertSame('50.000', $row['average_cost']);
        $this->assertSame('500.000', $row['cost_val']);

        $this->getJson('/api/v1/items/'.$zeroId, $this->headers)->assertOk()
            ->assertJsonPath('data.average_cost', '50.000')
            ->assertJsonPath('data.last_purchase_price', '50.000');
        $stock = $this->rowFor($this->getJson('/api/v1/stores/stocks?store_id='.$this->storeId, $this->headers)->json('data'), 'item_id', $zeroId);
        $this->assertSame('500.000', $stock['total_valuation']);
        $this->getJson('/api/v1/items', $this->headers)->assertJsonPath('summary.total_stock_value', '14540.000');
    }

    public function test_fractional_quantities_are_valued_half_up_at_the_average(): void
    {
        // 0.250 x 181.205 = 45.30125 -> 45.301
        $id = $this->inTenant($this->tenant, fn (): int => $this->makeItem('BUG02-FRAC', '0.250', '200.000', '181.205', $this->storeId));

        $row = $this->rowFor($this->getJson('/api/v1/reports/inventory?period=today', $this->headers)->assertOk()->json('data.items'), 'id', $id);
        $this->assertSame('45.301', $row['cost_val']);
        $stock = $this->rowFor($this->getJson('/api/v1/stores/stocks?store_id='.$this->storeId, $this->headers)->json('data'), 'item_id', $id);
        $this->assertSame('45.301', $stock['total_valuation']);
    }

    public function test_another_tenants_stock_and_costs_never_reach_this_tenants_valuation(): void
    {
        $this->purchase('5.000', '200.000');

        $other = $this->createTenant();
        $otherStore = (int) $this->tenantStore($other)->id;
        $this->inTenant($other, fn (): int => $this->makeItem('BUG02-OTHER', '1000.000', '999.000', '999.000', $otherStore));

        $this->getJson('/api/v1/reports/inventory?period=today', $this->tenantHeaders($other))->assertOk()
            ->assertJsonPath('data.stock_cost_valuation', '999000.000');

        $this->assertValuation('15040.015');
        $this->getJson('/api/v1/items', $this->headers)->assertJsonPath('summary.total_stock_value', '15040.015');
    }

    public function test_a_branch_is_valued_on_its_own_quantity_and_other_branches_are_refused(): void
    {
        $storeB = $this->inTenant($this->tenant, function (): int {
            $id = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'BUG02-SB', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            $this->transferBetween($this->storeId, $id, '8.000');

            return $id;
        });
        $this->purchase('5.000', '200.000');

        // A: 75 x 181.205 = 13590.375; B: 8 x 181.205 = 1449.640; together 15040.015.
        $this->assertBranch($this->headers, '13590.375');
        $this->assertBranch($this->tenantHeaders($this->tenant, null, $storeB), '1449.640');
        $this->assertSame('15040.015', $this->totalValuation());

        // A reports user of branch A only cannot read branch B's valuation or stock.
        $reporter = $this->createTenantUser($this->tenant, null, ['reports.view', 'items.view', 'items.view_cost']);
        $this->getJson('/api/v1/reports/inventory?period=today', $this->tenantHeaders($this->tenant, $reporter, $storeB))->assertForbidden();
        $this->getJson('/api/v1/stores/stocks?store_id='.$storeB, $this->tenantHeaders($this->tenant, $reporter))->assertForbidden();
        $this->getJson('/api/v1/reports/inventory?period=today', $this->tenantHeaders($this->tenant, $reporter))->assertOk()
            ->assertJsonPath('data.stock_cost_valuation', '13590.375');
    }

    public function test_the_touched_endpoints_still_require_authentication_and_permission(): void
    {
        $guest = $this->tenantGuestHeaders($this->tenant);
        $this->getJson('/api/v1/reports/inventory', $guest)->assertUnauthorized();
        $this->getJson('/api/v1/items/'.$this->itemId, $guest)->assertUnauthorized();
        $this->getJson('/api/v1/stores/stocks', $guest)->assertUnauthorized();

        $cashier = $this->createTenantUser($this->tenant, null, ['pos.access']);
        $headers = $this->tenantHeaders($this->tenant, $cashier);
        $this->getJson('/api/v1/reports/inventory', $headers)->assertForbidden();
        $this->getJson('/api/v1/stores/stocks', $headers)->assertForbidden();
    }

    private function assertValuation(string $expected): void
    {
        $this->getJson('/api/v1/reports/inventory?period=today', $this->headers)->assertOk()
            ->assertJsonPath('data.stock_cost_valuation', $expected);
        $this->getJson('/api/v1/reports/summary?period=today', $this->headers)->assertOk()
            ->assertJsonPath('summary.stock_cost_valuation', $expected);
    }

    /** Every branch: the tenant-wide valuation (items.current_stock x average). */
    private function totalValuation(): string
    {
        return (string) $this->getJson('/api/v1/reports/inventory?period=today&store_id=all', $this->headers)->assertOk()->json('data.stock_cost_valuation');
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function assertBranch(array $headers, string $expected): void
    {
        $this->getJson('/api/v1/reports/inventory?period=today', $headers)->assertOk()
            ->assertJsonPath('data.stock_cost_valuation', $expected);
    }

    /**
     * Asserts the exact new value, and that it is within $bound of before + purchased - cogs + returned.
     */
    private function assertStep(string $before, string $expected, string $purchased = '0.000', string $cogs = '0.000', string $returned = '0.000', string $bound = '0'): string
    {
        $after = $this->totalValuation();
        $this->assertSame($expected, $after);

        $invariant = bcadd(bcsub(bcadd($before, $purchased, 3), $cogs, 3), $returned, 3);
        $this->assertWithin($invariant, $after, $bound);

        return $after;
    }

    private function assertWithin(string $expected, string $actual, string $bound): void
    {
        $diff = bcsub($actual, $expected, 4);
        $abs = str_starts_with($diff, '-') ? substr($diff, 1) : $diff;
        $this->assertTrue(bccomp($abs, $bound, 4) <= 0, "Valuation {$actual} is {$diff} away from {$expected} (allowed {$bound}).");
    }

    private function purchase(string $qty, string $cost): int
    {
        return $this->inTenant($this->tenant, function () use ($qty, $cost): int {
            $this->actAsAdmin();

            return (int) app(PurchaseService::class)->createPurchase([
                'supplier_id' => $this->supplierId, 'store_id' => $this->storeId, 'paid_amount' => '0.000',
                'items' => [['item_id' => $this->itemId, 'quantity' => $qty, 'cost_price' => $cost]],
            ])->id;
        });
    }

    /**
     * @return array{invoice_id: int, line_id: int}
     */
    private function sell(string $qty): array
    {
        return $this->inTenant($this->tenant, function () use ($qty): array {
            $this->actAsAdmin();
            $invoice = app(InvoiceService::class)->confirmInvoice([
                'customer_id' => $this->customerId, 'store_id' => $this->storeId,
                'payment_type' => 'credit', 'payment_method' => 'cash', 'paid_amount' => '0.000',
                'discount_type' => 'fixed', 'discount_value' => '0.000',
                'items' => [['item_id' => $this->itemId, 'quantity' => $qty, 'unit_price' => '260.000']],
            ]);

            return [
                'invoice_id' => (int) $invoice->id,
                'line_id' => (int) InvoiceItem::query()->where('invoice_id', $invoice->id)->orderBy('id')->value('id'),
            ];
        });
    }

    private function salesReturn(int $invoiceId, int $lineId, string $qty): TestResponse
    {
        $today = $this->inTenant($this->tenant, fn (): string => app(TenantClock::class)->businessDate());

        return $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => $today, 'items' => [['invoice_item_id' => $lineId, 'quantity' => $qty]],
        ], $this->headers);
    }

    private function transfer(int $toStoreId, string $qty): void
    {
        $this->inTenant($this->tenant, fn () => $this->transferBetween($this->storeId, $toStoreId, $qty));
    }

    /** Runs inside the tenant. */
    private function transferBetween(int $fromStoreId, int $toStoreId, string $qty): void
    {
        $this->actAsAdmin();
        app(StockTransferService::class)->createTransfer([
            'from_store_id' => $fromStoreId, 'to_store_id' => $toStoreId,
            'items' => [['item_id' => $this->itemId, 'quantity' => $qty]],
        ]);
    }

    /** Runs inside the tenant. */
    private function actAsAdmin(): void
    {
        $this->actingAs(User::query()->orderBy('id')->firstOrFail());
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $rows
     * @return array<string, mixed>
     */
    private function rowFor(?array $rows, string $key, int|string $value): array
    {
        foreach ($rows ?? [] as $row) {
            if ($row[$key] == $value) {
                return $row;
            }
        }

        $this->fail("No row with {$key} = {$value}.");
    }

    /** Runs inside the tenant. */
    private function makeItem(string $code, string $qty, string $cost, string $wac, int $storeId, string $minStock = '1.000'): int
    {
        $item = Item::create([
            'name' => 'صنف '.$code, 'code' => $code, 'category' => 'coffee_beans', 'unit' => 'قطعة',
            'cost_price' => $cost, 'weighted_avg_cost' => $wac, 'selling_price' => '260.000',
            'current_stock' => $qty, 'min_stock_level' => $minStock, 'is_active' => true,
        ]);
        StoreStock::create(['store_id' => $storeId, 'item_id' => $item->id, 'quantity' => $qty]);

        return (int) $item->id;
    }
}
