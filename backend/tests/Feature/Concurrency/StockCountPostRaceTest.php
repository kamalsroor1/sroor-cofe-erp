<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Actions\StockCounts\SaveStockCountLinesAction;
use App\Actions\StockCounts\StartStockCountAction;
use App\DTOs\StockCounts\SaveStockCountLinesDTO;
use App\DTOs\StockCounts\StartStockCountDTO;
use App\Models\Item;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockMovement;
use App\Models\StoreStock;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RunsConcurrencyWorkers;
use Tests\TenantTestCase;

/**
 * SHOP-02: posting a stocktake while sales of the same item run in parallel processes.
 *
 * The posting and every sale lock the item row first (global lock order), so they serialise;
 * movement ids follow that order. Whatever the interleaving:
 *  - the balance the posting compared against = opening - sales committed before it;
 *  - the final balance = counted - sales committed after it (never the stale snapshot);
 *  - the master item stock equals the single store balance.
 */
#[Group('mysql')]
#[Group('mysql-only')]
#[Group('concurrency')]
final class StockCountPostRaceTest extends TenantTestCase
{
    use RunsConcurrencyWorkers;

    private const SALES = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection($this->centralConnectionName())->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->fail("MySQL-only test run on `{$driver}`. Use: php artisan test -c phpunit.mysql.xml");
        }
    }

    public function test_posting_a_count_while_selling_keeps_the_balance_exact(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->getKey();
        $userId = (int) $this->tenantAdmin($tenant)->getKey();

        [$itemId, $countId] = $this->inTenant($tenant, function () use ($storeId, $userId): array {
            $item = Item::query()->create([
                'code' => 'QA-CNT-RACE', 'name' => 'صنف جرد', 'unit' => 'قطعة', 'cost_price' => '10.000',
                'selling_price' => '15.000', 'current_stock' => '1000.000', 'min_stock_level' => '0.000', 'is_active' => true,
            ]);
            StoreStock::query()->updateOrCreate(['store_id' => $storeId, 'item_id' => $item->id], ['quantity' => '1000.000']);

            $count = app(StartStockCountAction::class)->execute(new StartStockCountDTO($storeId, null, false, null, $userId));
            app(SaveStockCountLinesAction::class)->execute(new SaveStockCountLinesDTO((int) $count->id, [['item_id' => (int) $item->id, 'counted_qty' => '900.000']], $userId));

            return [(int) $item->id, (int) $count->id];
        });

        $payloads = [['__scenario' => 'stock_count_post', 'stock_count_id' => $countId, 'user_id' => $userId]];
        for ($i = 1; $i <= self::SALES; $i++) {
            $payloads[] = ['__scenario' => 'deduct', 'item_id' => $itemId, 'quantity' => '5.000', 'store_id' => $storeId, 'document' => 'QA-CNT-SALE-'.$i];
        }

        $results = $this->runWorkers('deduct', $tenant, $payloads);
        foreach ($results as $result) {
            $this->assertTrue($result['ok'], (string) json_encode($result, JSON_UNESCAPED_UNICODE));
        }

        $this->inTenant($tenant, function () use ($itemId, $countId, $storeId): void {
            $countMovement = StockMovement::query()->where('source_type', StockCount::class)->where('source_id', $countId)->sole();

            $salesBefore = '0.000';
            $salesAfter = '0.000';
            foreach (StockMovement::query()->where('item_id', $itemId)->where('movement_type', 'sales_out')->get() as $sale) {
                if ($sale->id < $countMovement->id) {
                    $salesBefore = bcadd($salesBefore, (string) $sale->quantity, 3);
                } else {
                    $salesAfter = bcadd($salesAfter, (string) $sale->quantity, 3);
                }
            }

            $line = StockCountLine::query()->where('stock_count_id', $countId)->where('item_id', $itemId)->sole();
            $expectedAtPost = bcsub('1000.000', $salesBefore, 3);
            $this->assertSame($expectedAtPost, (string) $line->system_qty_at_post);
            $this->assertSame(bcsub('900.000', $expectedAtPost, 3), (string) $line->difference);

            $final = (string) StoreStock::query()->where('store_id', $storeId)->where('item_id', $itemId)->value('quantity');
            $this->assertSame(bcsub('900.000', $salesAfter, 3), $final);
            $this->assertSame($final, (string) Item::query()->whereKey($itemId)->value('current_stock'));
        });
    }
}
