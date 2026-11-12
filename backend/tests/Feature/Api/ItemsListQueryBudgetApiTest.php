<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TenantTestCase;

/**
 * PERF-02: GET /api/v1/items answers with a FIXED number of SQL statements whatever the page
 * size (it used to run ~12 per row: getDeletionBlockers() twice per item), and the JSON of
 * every row is unchanged - including can_be_deleted / deletion_blockers of each blocker kind,
 * soft-deleted history rows (never counted) and the cost visibility rules.
 */
final class ItemsListQueryBudgetApiTest extends TenantTestCase
{
    /** Upper bound of statements per items page (auth, tenancy, permissions, page, counts, summary). */
    private const QUERY_BUDGET = 30;

    private Tenant $tenant;

    private int $mainStore;

    private int $storeB;

    private int $userId;

    private string $stamp = '2026-11-01 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);
        $this->mainStore = (int) $this->tenantStore($this->tenant)->id;
        $this->storeB = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'ITM-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
        $this->userId = (int) User::query()->orderBy('id')->value('id');
    }

    public function test_every_row_keeps_its_exact_contract_and_deletion_blockers(): void
    {
        $clean = $this->item('ITM-CLEAN', '0.000');
        $this->storeStock($clean, $this->mainStore, '0.000');
        $this->storeStock($clean, $this->storeB, '9.000', deleted: true); // soft-deleted stock never blocks

        $stocked = $this->item('ITM-STOCK', '5.250', ['min_stock_level' => '2.000', 'cost_price' => '8.125', 'min_selling_price' => '9.000', 'selling_price' => '11.000']);
        $this->storeStock($stocked, $this->mainStore, '5.250', '12.500');
        $this->storeStock($stocked, $this->storeB, '0.000');

        $elsewhere = $this->item('ITM-ELSE', '0.000');
        $this->storeStock($elsewhere, $this->storeB, '1.000');

        $history = $this->item('ITM-HIST', '0.000');
        $this->history($history);

        $response = $this->listItems(50)->assertOk();
        $rows = collect($response->json('data'))->keyBy('code');

        $this->assertSame([
            'id' => $stocked,
            'code' => 'ITM-STOCK',
            'name' => 'صنف ITM-STOCK',
            'category' => 'coffee_beans',
            'category_id' => null,
            'pos_sales_count' => 0,
            'unit' => 'كجم',
            'cost_price' => '8.125',
            'min_selling_price' => '9.000',
            'has_min_price' => true,
            'wholesale_price' => null,
            'selling_price' => '12.500',
            'current_stock' => '5.250',
            'total_stock' => '5.250',
            'min_stock_level' => '2.000',
            'is_active' => true,
            'is_weighted' => true,
            'is_low_stock' => false,
            'notes' => null,
            'store_stocks' => [
                ['store_id' => $this->mainStore, 'store_name' => Store::query()->whereKey($this->mainStore)->value('name'), 'quantity' => '5.250', 'custom_selling_price' => '12.500'],
                ['store_id' => $this->storeB, 'store_name' => 'فرع ب', 'quantity' => '0.000', 'custom_selling_price' => null],
            ],
            'can_be_deleted' => false,
            'deletion_blockers' => [
                'يوجد رصيد بضاعة متبقي بالمخزن (5.250 كجم)',
                'يوجد رصيد متوفر في أحد الفروع أو عربات التوزيع',
            ],
            'created_at' => $this->stamp,
        ], $rows['ITM-STOCK']);

        $this->assertTrue($rows['ITM-CLEAN']['can_be_deleted']);
        $this->assertSame([], $rows['ITM-CLEAN']['deletion_blockers']);
        $this->assertTrue($rows['ITM-CLEAN']['is_low_stock']);

        $this->assertFalse($rows['ITM-ELSE']['can_be_deleted']);
        $this->assertSame(['يوجد رصيد متوفر في أحد الفروع أو عربات التوزيع'], $rows['ITM-ELSE']['deletion_blockers']);

        $this->assertFalse($rows['ITM-HIST']['can_be_deleted']);
        $this->assertSame([
            'مسجل به 2 فاتورة مبيعات معتمدة',
            'مسجل به 1 فاتورة مشتريات وتوريد',
            'يوجد له 3 حركة مخزنية مسجلة',
            'مرتبط بـ 1 حركة مرتجعات',
            'مرتبط بـ 1 إذن تحويل بين الفروع',
        ], $rows['ITM-HIST']['deletion_blockers']);

        $response->assertJsonPath('summary.total_items', 4)
            ->assertJsonPath('summary.total_stock_value', '42.656')
            ->assertJsonPath('meta.total', 4);
    }

    public function test_a_cashier_still_never_receives_cost_or_the_minimum_price(): void
    {
        $this->item('ITM-COST', '3.000', ['cost_price' => '8.125', 'min_selling_price' => '9.000']);
        $cashier = $this->createTenantUser($this->tenant, null, ['pos.access']);

        $row = $this->getJson('/api/v1/items?per_page=50', $this->tenantHeaders($this->tenant, $cashier, $this->mainStore))
            ->assertOk()
            ->assertJsonPath('summary.total_stock_value', null)
            ->json('data.0');

        $this->assertNull($row['cost_price']);
        $this->assertNull($row['min_selling_price']);
        $this->assertTrue($row['has_min_price']);
    }

    public function test_the_query_count_is_the_same_for_50_and_200_rows(): void
    {
        $this->bulkItems(50);
        $this->listItems(1)->assertOk(); // warm-up: token, settings and permission caches
        $fifty = $this->countQueries(fn () => $this->listItems(50)->assertOk()->assertJsonCount(50, 'data'));

        $this->bulkItems(150);
        $this->listItems(1)->assertOk();
        $twoHundred = $this->countQueries(fn () => $this->listItems(200)->assertOk()->assertJsonCount(200, 'data'));

        $this->assertSame($fifty, $twoHundred, 'the items page must not run a query per row');
        $this->assertLessThanOrEqual(self::QUERY_BUDGET, $twoHundred);
    }

    public function test_delete_still_refuses_an_item_with_blockers_without_preloaded_counts(): void
    {
        // DELETE loads the item with findOrFail(): no withDeletionBlockers() counts, so
        // getDeletionBlockers() takes its per-query path (including the stockTransferItems relation).
        $transferOnly = $this->item('DEL-TRF', '0.000');
        $userId = $this->userId;
        $transferId = (int) DB::table('stock_transfers')->insertGetId(['transfer_number' => 'DEL-TRF-1', 'from_store_id' => $this->mainStore, 'to_store_id' => $this->storeB, 'user_id' => $userId, 'transfer_date' => '2026-11-01', 'created_at' => $this->stamp, 'updated_at' => $this->stamp]);
        DB::table('stock_transfer_items')->insert(['stock_transfer_id' => $transferId, 'item_id' => $transferOnly, 'quantity' => '1.000', 'created_at' => $this->stamp, 'updated_at' => $this->stamp]);

        $stockElsewhere = $this->item('DEL-ELSE', '0.000');
        $this->storeStock($stockElsewhere, $this->storeB, '0.250');

        $history = $this->item('DEL-HIST', '0.000');
        $this->history($history);

        $clean = $this->item('DEL-CLEAN', '0.000');
        $this->storeStock($clean, $this->storeB, '4.000', deleted: true); // soft-deleted stock never blocks

        foreach ([$transferOnly, $stockElsewhere, $history] as $blocked) {
            $this->deleteJson('/api/v1/items/'.$blocked, [], $this->tenantHeaders($this->tenant, null, $this->mainStore))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['item' => __('inventory.cannot_delete_has_movements')]);
            $this->assertNull(DB::table('items')->where('id', $blocked)->value('deleted_at'), "item {$blocked} kept");
        }

        $this->deleteJson('/api/v1/items/'.$clean, [], $this->tenantHeaders($this->tenant, null, $this->mainStore))->assertOk();
        $this->assertNotNull(DB::table('items')->where('id', $clean)->value('deleted_at'), 'a clean item is (soft) deleted');
    }

    private function listItems(int $perPage): TestResponse
    {
        return $this->getJson('/api/v1/items?per_page='.$perPage, $this->tenantHeaders($this->tenant, null, $this->mainStore));
    }

    private function countQueries(callable $request): int
    {
        $count = 0;
        DB::listen(function (QueryExecuted $query) use (&$count): void {
            $count++;
        });
        $request();
        $counted = $count;
        $count = PHP_INT_MIN; // later listeners of this test no longer matter

        return $counted;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function item(string $code, string $stock, array $overrides = []): int
    {
        return (int) DB::table('items')->insertGetId(array_merge([
            'code' => $code, 'name' => 'صنف '.$code, 'category' => 'coffee_beans', 'unit' => 'كجم',
            'current_stock' => $stock, 'cost_price' => '4.000', 'weighted_avg_cost' => '4.000', 'selling_price' => '6.000',
            'min_selling_price' => '0.000', 'min_stock_level' => '0.000', 'is_active' => true,
            'created_at' => $this->stamp, 'updated_at' => $this->stamp,
        ], $overrides));
    }

    private function storeStock(int $itemId, int $storeId, string $quantity, ?string $customPrice = null, bool $deleted = false): void
    {
        DB::table('store_stocks')->insert([
            'item_id' => $itemId, 'store_id' => $storeId, 'quantity' => $quantity, 'custom_selling_price' => $customPrice,
            'created_at' => $this->stamp, 'updated_at' => $this->stamp, 'deleted_at' => $deleted ? $this->stamp : null,
        ]);
    }

    /**
     * Two live invoice lines + one soft-deleted, one purchase line, three stock movements, one
     * return line, one live transfer line + one soft-deleted.
     */
    private function history(int $itemId): void
    {
        $customerId = (int) DB::table('customers')->insertGetId(['name' => 'عميل', 'phone' => '01000008401', 'price_tier' => 'retail', 'is_active' => true, 'created_at' => $this->stamp, 'updated_at' => $this->stamp]);
        $supplierId = (int) DB::table('suppliers')->insertGetId(['name' => 'مورد', 'phone' => '01000008402', 'is_active' => true, 'created_at' => $this->stamp, 'updated_at' => $this->stamp]);
        $invoiceId = (int) DB::table('invoices')->insertGetId([
            'invoice_number' => 'ITM-INV-1', 'customer_id' => $customerId, 'user_id' => $this->userId, 'store_id' => $this->mainStore,
            'invoice_date' => '2026-11-01', 'payment_type' => 'cash', 'status' => 'confirmed', 'subtotal' => '18.000', 'net_total' => '18.000',
            'paid_amount' => '18.000', 'remaining_amount' => '0.000', 'created_at' => $this->stamp, 'updated_at' => $this->stamp,
        ]);
        foreach ([null, null, $this->stamp] as $deletedAt) {
            DB::table('invoice_items')->insert([
                'invoice_id' => $invoiceId, 'item_id' => $itemId, 'quantity' => '1.000', 'cost_price' => '4.000', 'unit_price' => '6.000',
                'discount_amount' => '0.000', 'total_price' => '6.000', 'created_at' => $this->stamp, 'updated_at' => $this->stamp, 'deleted_at' => $deletedAt,
            ]);
        }
        $purchaseId = (int) DB::table('purchases')->insertGetId(['purchase_number' => 'ITM-PUR-1', 'supplier_id' => $supplierId, 'user_id' => $this->userId, 'store_id' => $this->mainStore, 'purchase_date' => '2026-11-01', 'created_at' => $this->stamp, 'updated_at' => $this->stamp]);
        DB::table('purchase_items')->insert(['purchase_id' => $purchaseId, 'item_id' => $itemId, 'quantity' => '3.000', 'cost_price' => '4.000', 'total_price' => '12.000', 'created_at' => $this->stamp, 'updated_at' => $this->stamp]);
        for ($m = 0; $m < 3; $m++) {
            DB::table('stock_movements')->insert([
                'item_id' => $itemId, 'store_id' => $this->mainStore, 'movement_type' => 'purchase_in', 'quantity' => '1.000', 'stock_before' => '0.000',
                'stock_after' => '1.000', 'unit_cost' => '4.000', 'source_type' => 'purchase', 'source_id' => $purchaseId, 'user_id' => $this->userId,
                'created_at' => $this->stamp, 'updated_at' => $this->stamp,
            ]);
        }
        $returnId = (int) DB::table('returns')->insertGetId(['return_number' => 'ITM-RET-1', 'return_type' => 'sales_return', 'invoice_id' => $invoiceId, 'customer_id' => $customerId, 'user_id' => $this->userId, 'store_id' => $this->mainStore, 'total_amount' => '6.000', 'return_date' => '2026-11-01', 'created_at' => $this->stamp, 'updated_at' => $this->stamp]);
        DB::table('return_items')->insert(['return_id' => $returnId, 'item_id' => $itemId, 'quantity' => '1.000', 'unit_price' => '6.000', 'total_price' => '6.000', 'created_at' => $this->stamp, 'updated_at' => $this->stamp]);
        $transferId = (int) DB::table('stock_transfers')->insertGetId(['transfer_number' => 'ITM-TRF-1', 'from_store_id' => $this->mainStore, 'to_store_id' => $this->storeB, 'user_id' => $this->userId, 'transfer_date' => '2026-11-01', 'created_at' => $this->stamp, 'updated_at' => $this->stamp]);
        foreach ([null, $this->stamp] as $deletedAt) {
            DB::table('stock_transfer_items')->insert(['stock_transfer_id' => $transferId, 'item_id' => $itemId, 'quantity' => '1.000', 'created_at' => $this->stamp, 'updated_at' => $this->stamp, 'deleted_at' => $deletedAt]);
        }
    }

    /**
     * Items with a stock row in both stores and a stock movement each (every blocker query has rows).
     */
    private function bulkItems(int $count): void
    {
        $offset = (int) DB::table('items')->count();
        for ($i = 0; $i < $count; $i++) {
            $id = $this->item(sprintf('BULK-%04d', $offset + $i), '1.000');
            $this->storeStock($id, $this->mainStore, '1.000');
            $this->storeStock($id, $this->storeB, '0.000');
            DB::table('stock_movements')->insert([
                'item_id' => $id, 'store_id' => $this->mainStore, 'movement_type' => 'opening', 'quantity' => '1.000', 'stock_before' => '0.000',
                'stock_after' => '1.000', 'unit_cost' => '4.000', 'source_type' => 'opening', 'source_id' => $id, 'user_id' => $this->userId,
                'created_at' => $this->stamp, 'updated_at' => $this->stamp,
            ]);
        }
    }
}
