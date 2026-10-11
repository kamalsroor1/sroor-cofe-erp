<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * SEC-1 (W2 batch 6):
 *  - M3: GET /items/{id} and /items/{id}/movements run under store.active; a cashier of branch A
 *    never receives branch B's stock rows or ledger (403 on B, rows filtered).
 *  - M6: cost_price and cost-derived figures only with items.view_cost (admin, accountant);
 *    cashiers keep the selling price (POS works). GET /stores/stocks needs items.view. The
 *    dashboard's profit/margin/debts need reports.view. The item card returns ItemResource.
 */
final class ItemCostAndStoreScopeApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeAId;

    private int $storeBId;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeAId = (int) $this->tenantStore($this->tenant)->id;

        [$this->storeBId, $this->itemId] = $this->inTenant($this->tenant, function (): array {
            $storeB = Store::create(['name' => 'فرع التجمع', 'code' => 'BR-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);

            $item = Item::create([
                'name' => 'بن برازيلي',
                'code' => 'SEC1-ITEM',
                'category' => 'coffee_beans',
                'unit' => 'كجم',
                'cost_price' => '300.000',
                'selling_price' => '500.000',
                'min_selling_price' => '450.000',
                'current_stock' => '30.250',
                'min_stock_level' => '1.000',
                'is_active' => true,
            ]);

            StoreStock::create(['store_id' => $this->storeAId, 'item_id' => $item->id, 'quantity' => '10.250']);
            StoreStock::create(['store_id' => $storeB->id, 'item_id' => $item->id, 'quantity' => '20.000']);

            foreach ([[$this->storeAId, 'SEC1-MOV-A'], [(int) $storeB->id, 'SEC1-MOV-B']] as [$storeId, $doc]) {
                StockMovement::create([
                    'item_id' => $item->id,
                    'movement_type' => 'purchase_in',
                    'quantity' => '5.000',
                    'stock_before' => '0.000',
                    'stock_after' => '5.000',
                    'unit_cost' => '300.000',
                    'source_type' => 'adjustment',
                    'source_id' => 0,
                    'document_number' => $doc,
                    'store_id' => $storeId,
                ]);
            }

            return [(int) $storeB->id, (int) $item->id];
        });
    }

    // ------------------------------------------------------------------ M3

    public function test_cashier_sees_only_their_branch_stock_rows_and_no_cost(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $data = $this->getJson("/api/v1/items/{$this->itemId}", $this->tenantHeaders($this->tenant, $cashier))
            ->assertOk()
            ->json('data');

        $this->assertSame([$this->storeAId], array_column($data['store_stocks'], 'store_id'));
        $this->assertNull($data['cost_price']);
        $this->assertSame(500.0, (float) $data['selling_price']);
        $this->assertSame(450.0, (float) $data['min_selling_price']);
    }

    public function test_cashier_cannot_open_an_item_on_another_branch(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $this->getJson("/api/v1/items/{$this->itemId}", $this->tenantHeaders($this->tenant, $cashier, $this->storeBId))
            ->assertStatus(403);
        $this->getJson("/api/v1/items/{$this->itemId}/movements", $this->tenantHeaders($this->tenant, $cashier, $this->storeBId))
            ->assertStatus(403);
    }

    public function test_cashier_movements_are_limited_to_their_branch_and_hide_costs(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $headers = $this->tenantHeaders($this->tenant, $cashier);

        $response = $this->getJson("/api/v1/items/{$this->itemId}/movements", $headers)->assertOk();

        $this->assertSame(['SEC1-MOV-A'], array_column($response->json('data.data'), 'document_number'));
        $this->assertSame($this->storeAId, $response->json('data.filters.store_id'));
        $this->assertNull($response->json('data.item.cost_price'));
        $this->assertArrayNotHasKey('unit_cost', $response->json('data.data.0'));

        // An explicit filter on another branch is refused, never widened.
        $this->getJson("/api/v1/items/{$this->itemId}/movements?store_id={$this->storeBId}", $headers)->assertStatus(403);
        $this->getJson("/api/v1/items/{$this->itemId}/movements?store_id=all", $headers)->assertStatus(403);
    }

    public function test_admin_sees_every_branch_and_the_cost(): void
    {
        $headers = $this->tenantHeaders($this->tenant, null, $this->storeAId);

        $data = $this->getJson("/api/v1/items/{$this->itemId}", $headers)->assertOk()->json('data');
        $stores = array_column($data['store_stocks'], 'store_id');
        sort($stores);
        $this->assertSame([$this->storeAId, $this->storeBId], $stores);
        $this->assertSame(300.0, (float) $data['cost_price']);

        $all = $this->getJson("/api/v1/items/{$this->itemId}/movements?store_id=all", $headers)->assertOk();
        $this->assertCount(2, $all->json('data.data'));
        $this->assertSame(300.0, (float) $all->json('data.item.cost_price'));
    }

    public function test_items_list_hides_other_branches_rows_and_the_stock_valuation_from_cashiers(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $response = $this->getJson('/api/v1/items', $this->tenantHeaders($this->tenant, $cashier))->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $this->itemId);
        $this->assertSame([$this->storeAId], array_column($row['store_stocks'], 'store_id'));
        $this->assertNull($row['cost_price']);
        $this->assertNull($response->json('summary.total_stock_value'));
    }

    // ------------------------------------------------------------------ M6

    public function test_accountant_with_view_cost_sees_the_cost(): void
    {
        $accountant = $this->createTenantUser($this->tenant, 'accountant');

        $data = $this->getJson("/api/v1/items/{$this->itemId}", $this->tenantHeaders($this->tenant, $accountant))
            ->assertOk()
            ->json('data');

        $this->assertSame(300.0, (float) $data['cost_price']);
    }

    public function test_store_stocks_requires_items_view_and_hides_cost_without_view_cost(): void
    {
        $nobody = $this->createTenantUser($this->tenant);
        $this->getJson('/api/v1/stores/stocks', $this->tenantHeaders($this->tenant, $nobody))->assertStatus(403);

        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $row = $this->getJson('/api/v1/stores/stocks', $this->tenantHeaders($this->tenant, $cashier))
            ->assertOk()
            ->json('data.0');
        $this->assertSame(10.25, (float) $row['quantity']);
        $this->assertNull($row['cost_price']);
        $this->assertNull($row['total_valuation']);

        $adminRow = $this->getJson('/api/v1/stores/stocks', $this->tenantHeaders($this->tenant))->assertOk()->json('data.0');
        $this->assertSame(300.0, (float) $adminRow['cost_price']);
        $this->assertSame(3075.0, (float) $adminRow['total_valuation']);
    }

    public function test_pos_bootstrap_gives_cashiers_selling_prices_but_not_the_cost(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $items = $this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($this->tenant, $cashier))
            ->assertOk()
            ->json('data.items');
        $row = collect($items)->firstWhere('id', $this->itemId);

        $this->assertSame(500.0, (float) $row['price_retail']);
        $this->assertSame(450.0, (float) $row['price_wholesale']);
        $this->assertSame(450.0, (float) $row['min_selling_price']);
        $this->assertNull($row['cost_price']);
        $this->assertSame(10.25, (float) $row['current_stock']);

        $adminRow = collect($this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($this->tenant))->assertOk()->json('data.items'))
            ->firstWhere('id', $this->itemId);
        $this->assertSame(300.0, (float) $adminRow['cost_price']);
    }

    public function test_dashboard_financial_blocks_need_reports_view(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $cashierData = $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $cashier))->assertOk()->json('data');
        $this->assertFalse($cashierData['can_view_financials']);
        foreach (['monthly_gross_profit', 'monthly_margin', 'customers_debt', 'suppliers_debt'] as $key) {
            $this->assertNull($cashierData['metrics'][$key], $key);
        }
        $this->assertArrayHasKey('today_sales', $cashierData['metrics']);

        $adminData = $this->getJson('/api/v1/dashboard/summary', $this->tenantHeaders($this->tenant))->assertOk()->json('data');
        $this->assertTrue($adminData['can_view_financials']);
        $this->assertIsNumeric($adminData['metrics']['monthly_gross_profit']);
        $this->assertIsNumeric($adminData['metrics']['customers_debt']);
    }

    public function test_item_card_returns_the_resource_without_cost_for_reports_only_users(): void
    {
        $reporter = $this->createTenantUser($this->tenant, permissions: ['reports.view']);

        $response = $this->getJson("/api/v1/reports/items/{$this->itemId}/card", $this->tenantHeaders($this->tenant, $reporter))
            ->assertOk();

        $this->assertNull($response->json('item.cost_price'));
        $this->assertArrayNotHasKey('weighted_avg_cost', $response->json('item'));
        $this->assertArrayHasKey('store_stocks', $response->json('item'));
        $this->assertArrayNotHasKey('unit_cost', $response->json('movements.0'));
    }

    public function test_items_of_another_tenant_are_not_found(): void
    {
        $other = $this->createTenant();
        $foreignId = $this->inTenant($other, fn (): int => (int) Item::create([
            'name' => 'صنف مستأجر آخر', 'code' => 'OTHER-1', 'cost_price' => '1.000', 'selling_price' => '2.000',
            'current_stock' => '0.000', 'is_active' => true,
        ])->id);

        $this->getJson("/api/v1/items/{$foreignId}", $this->tenantHeaders($this->tenant))->assertStatus(404);
        $this->getJson("/api/v1/items/{$foreignId}/movements", $this->tenantHeaders($this->tenant))->assertStatus(404);
    }

    public function test_guest_is_401(): void
    {
        $this->getJson("/api/v1/items/{$this->itemId}", $this->tenantGuestHeaders($this->tenant))->assertStatus(401);
        $this->getJson('/api/v1/stores/stocks', $this->tenantGuestHeaders($this->tenant))->assertStatus(401);
    }
}
