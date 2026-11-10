<?php

declare(strict_types=1);

namespace Tests\Feature\Stock;

use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Tests\TenantTestCase;

final class StockDriftReportTest extends TenantTestCase
{
    private Tenant $tenant;

    /** @var array<string, string> */
    private array $adminHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);
    }

    private function makeStore(string $code, bool $isMain = false): Store
    {
        return Store::create([
            'name' => 'Branch '.$code,
            'code' => $code,
            'type' => 'retail_shop',
            'is_active' => true,
            'is_main' => $isMain,
        ]);
    }

    private function makeItem(string $code, string $currentStock): Item
    {
        return Item::create([
            'code' => $code,
            'name' => 'Item '.$code,
            'unit' => 'kg',
            'current_stock' => $currentStock,
            'cost_price' => '100.000',
            'weighted_avg_cost' => '100.000',
            'selling_price' => '150.000',
            'min_stock_level' => '5.000',
            'is_active' => true,
        ]);
    }

    public function test_matching_item_is_excluded_and_drifted_items_are_reported_with_exact_bcmath_values(): void
    {
        [$matchedId, $overId, $underId] = $this->inTenant($this->tenant, function (): array {
            $main = Store::getMainStore() ?? $this->makeStore('MAIN', true);
            $branch2 = $this->makeStore('BR-2');

            // 1. Matched: current_stock = 15.500, main = 10.250, branch2 = 5.250 -> sum = 15.500
            $matched = $this->makeItem('MATCH-01', '15.500');
            StoreStock::updateOrCreate(
                ['store_id' => $main->id, 'item_id' => $matched->id],
                ['quantity' => '10.250', 'min_stock' => '5.000']
            );
            StoreStock::updateOrCreate(
                ['store_id' => $branch2->id, 'item_id' => $matched->id],
                ['quantity' => '5.250', 'min_stock' => '5.000']
            );

            // 2. Over-allocated in branches: current_stock = 10.000, main = 8.000, branch2 = 4.500 -> sum = 12.500, drift = +2.500
            $over = $this->makeItem('OVER-01', '10.000');
            StoreStock::updateOrCreate(
                ['store_id' => $main->id, 'item_id' => $over->id],
                ['quantity' => '8.000', 'min_stock' => '5.000']
            );
            StoreStock::updateOrCreate(
                ['store_id' => $branch2->id, 'item_id' => $over->id],
                ['quantity' => '4.500', 'min_stock' => '5.000']
            );

            // 3. Under-allocated in branches: current_stock = 20.000, main = 14.250, branch2 = 2.000 -> sum = 16.250, drift = -3.750
            $under = $this->makeItem('UNDER-01', '20.000');
            StoreStock::updateOrCreate(
                ['store_id' => $main->id, 'item_id' => $under->id],
                ['quantity' => '14.250', 'min_stock' => '5.000']
            );
            StoreStock::updateOrCreate(
                ['store_id' => $branch2->id, 'item_id' => $under->id],
                ['quantity' => '2.000', 'min_stock' => '5.000']
            );

            return [$matched->id, $over->id, $under->id];
        });

        $response = $this->getJson('/api/v1/reports/stock-drift', $this->adminHeaders);
        $response->assertOk()->assertJsonPath('success', true);

        /** @var list<array<string, mixed>> $data */
        $data = $response->json('data');
        $ids = array_column($data, 'id');

        $this->assertNotContains($matchedId, $ids);
        $this->assertContains($overId, $ids);
        $this->assertContains($underId, $ids);

        $byId = array_column($data, null, 'id');
        $this->assertSame('10.000', $byId[$overId]['current_stock']);
        $this->assertSame('12.500', $byId[$overId]['stores_stock_sum']);
        $this->assertSame('2.500', $byId[$overId]['drift']);

        $this->assertSame('20.000', $byId[$underId]['current_stock']);
        $this->assertSame('16.250', $byId[$underId]['stores_stock_sum']);
        $this->assertSame('-3.750', $byId[$underId]['drift']);
    }

    public function test_soft_deleted_store_stocks_rows_are_ignored_in_drift_calculation(): void
    {
        $itemId = $this->inTenant($this->tenant, function (): int {
            $main = Store::getMainStore() ?? $this->makeStore('MAIN', true);
            $branch2 = $this->makeStore('BR-DEL');

            $item = $this->makeItem('SOFT-01', '10.000');
            StoreStock::updateOrCreate(
                ['store_id' => $main->id, 'item_id' => $item->id],
                ['quantity' => '10.000', 'min_stock' => '5.000']
            );
            $deletedStock = StoreStock::updateOrCreate(
                ['store_id' => $branch2->id, 'item_id' => $item->id],
                ['quantity' => '7.000', 'min_stock' => '5.000']
            );
            $deletedStock->delete();

            return $item->id;
        });

        $response = $this->getJson('/api/v1/reports/stock-drift', $this->adminHeaders);
        $response->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertNotContains($itemId, $ids);
    }

    public function test_endpoint_enforces_authentication_permissions_and_pagination(): void
    {
        // Unauthenticated -> 401
        $this->getJson('/api/v1/reports/stock-drift', $this->tenantGuestHeaders($this->tenant))
            ->assertStatus(401);

        // User without reports.view -> 403
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $cashierHeaders = $this->tenantHeaders($this->tenant, $cashier);
        $this->getJson('/api/v1/reports/stock-drift', $cashierHeaders)
            ->assertStatus(403);

        // User with reports.view -> 200
        $auditor = $this->createTenantUser($this->tenant, null, ['reports.view']);
        $auditorHeaders = $this->tenantHeaders($this->tenant, $auditor);

        $this->inTenant($this->tenant, function (): void {
            $main = Store::getMainStore() ?? $this->makeStore('MAIN', true);
            for ($i = 1; $i <= 3; $i++) {
                $item = $this->makeItem("DRIFT-P{$i}", '10.000');
                StoreStock::updateOrCreate(
                    ['store_id' => $main->id, 'item_id' => $item->id],
                    ['quantity' => '5.000', 'min_stock' => '1.000']
                );
            }
        });

        $page1 = $this->getJson('/api/v1/reports/stock-drift?per_page=2&page=1', $auditorHeaders);
        $page1->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_tenant_isolation_prevents_cross_tenant_leakage(): void
    {
        $tenantB = $this->createTenant();
        $this->tenantAdmin($tenantB);

        $this->inTenant($tenantB, function (): void {
            $main = Store::getMainStore() ?? $this->makeStore('MAIN-B', true);
            $item = $this->makeItem('TENANT-B-DRIFT', '50.000');
            StoreStock::updateOrCreate(
                ['store_id' => $main->id, 'item_id' => $item->id],
                ['quantity' => '10.000', 'min_stock' => '1.000']
            );
        });

        $responseA = $this->getJson('/api/v1/reports/stock-drift', $this->adminHeaders);
        $responseA->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_artisan_command_reports_drift_read_only_and_fails_on_unknown_tenant(): void
    {
        $itemId = $this->inTenant($this->tenant, function (): int {
            $main = Store::getMainStore() ?? $this->makeStore('MAIN', true);
            $item = $this->makeItem('CMD-DRIFT-01', '25.000');
            StoreStock::updateOrCreate(
                ['store_id' => $main->id, 'item_id' => $item->id],
                ['quantity' => '19.500', 'min_stock' => '1.000']
            );

            return $item->id;
        });

        $exitCode = Artisan::call('stock:report-drift', ['--tenant' => $this->tenant->id]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('CMD-DRIFT-01', $output);
        $this->assertStringContainsString('-5.500', $output);

        // Read-only: neither current_stock nor store_stocks.quantity was modified
        $this->inTenant($this->tenant, function () use ($itemId): void {
            $item = Item::findOrFail($itemId);
            $this->assertSame('25.000', (string) $item->current_stock);
            $this->assertSame('19.500', (string) StoreStock::where('item_id', $itemId)->value('quantity'));
        });

        $this->assertSame(1, Artisan::call('stock:report-drift', ['--tenant' => 'non-existent-tenant']));
    }
}
