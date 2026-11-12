<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * IMP-02: committing a big items file is batched (chunked inserts in ONE transaction), so the
 * query count does not grow with the row count and 5,000 items commit well inside a proxy timeout.
 * In the `mysql` group: the timing bound must hold on a real InnoDB server too.
 */
#[Group('mysql')]
class ItemsImportCommitPerformanceTest extends TenantTestCase
{
    /** Target is 10 s (measured ~3 s); the bound is looser so a shared CI runner cannot flake it. */
    private const MAX_SECONDS = 20.0;

    private Tenant $tenant;

    /** @var list<int> */
    private array $storeIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $mainStoreId = (int) $this->tenantStore($this->tenant)->id;

        // A 3-store tenant: the import's active store plus two more branches.
        $extra = $this->inTenant($this->tenant, fn (): array => [
            (int) Store::query()->create(['name' => 'فرع 2', 'code' => 'BR-2', 'type' => 'retail_shop', 'is_active' => true])->id,
            (int) Store::query()->create(['name' => 'فرع 3', 'code' => 'BR-3', 'type' => 'retail_shop', 'is_active' => true])->id,
        ]);
        $this->storeIds = [$mainStoreId, ...$extra];
    }

    private function itemsCsv(int $count, int $categories = 25): UploadedFile
    {
        $columns = ['code', 'name', 'category', 'unit', 'cost_price', 'selling_price', 'min_selling_price', 'min_stock_level', 'is_weighted', 'opening_quantity', 'opening_cost'];
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_map(static fn (string $c): string => (string) __("import.columns.items.{$c}", [], 'ar'), $columns), ',', '"', '');
        for ($i = 1; $i <= $count; $i++) {
            // Every 3rd row has no code (auto ITM-xxxx), every 2nd row carries opening stock.
            fputcsv($handle, [
                $i % 3 === 0 ? '' : 'P-'.$i,
                'صنف رقم '.$i,
                'تصنيف '.($i % $categories),
                'كجم',
                '10.250',
                '15.500',
                '',
                '2',
                $i % 5 === 0 ? 'نعم' : '',
                $i % 2 === 0 ? '3.250' : '',
                $i % 4 === 0 ? '9.750' : '',
            ], ',', '"', '');
        }
        fclose($handle);

        return new UploadedFile($path, 'items.csv', null, null, true);
    }

    private function previewToken(UploadedFile $file): string
    {
        return (string) $this->post('/api/v1/imports/items/preview', ['file' => $file], $this->tenantHeaders($this->tenant))
            ->assertOk()->json('data.token');
    }

    /**
     * @return array{0: float, 1: int} seconds, queries
     */
    private function timedCommit(string $token, int $expectedCreated): array
    {
        $queries = 0;
        Event::listen(QueryExecuted::class, function () use (&$queries): void {
            $queries++;
        });

        $started = microtime(true);
        $this->postJson('/api/v1/imports/items/commit', ['token' => $token], $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('data.created', $expectedCreated);
        $seconds = microtime(true) - $started;

        fwrite(STDERR, sprintf("\n[IMP-02] commit of %d items: %.2f s, %d queries\n", $expectedCreated, $seconds, $queries));

        return [$seconds, $queries];
    }

    public function test_committing_5000_items_on_a_3_store_tenant_is_fast_and_bounded_in_queries(): void
    {
        [$seconds, $queries] = $this->timedCommit($this->previewToken($this->itemsCsv(5000)), 5000);

        $this->assertLessThanOrEqual(self::MAX_SECONDS, $seconds, 'Committing 5,000 items must stay well inside a proxy timeout.');
        // Per-row writes would be >= 5,000 queries; batches of a few hundred rows stay in the low hundreds.
        $this->assertLessThan(250, $queries, "Commit ran {$queries} queries for 5,000 rows: the write is not batched.");

        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(5000, Item::query()->count());
            $this->assertSame(25, Category::query()->count());
            // Every item has a row in every store (BE-04 grid), opening stock only in the active store.
            $this->assertSame(15000, StoreStock::query()->count());
            $this->assertSame(2500, StockMovement::query()->where('movement_type', 'opening_balance_in')->count());
            // 2,500 rows x 3.250 = 8125 in the active store (the driver returns the SUM as int/float/string).
            $this->assertSame(0, bccomp((string) StoreStock::query()->where('store_id', $this->storeIds[0])->sum('quantity'), '8125', 3));
            $this->assertSame(0, bccomp((string) Item::query()->sum('current_stock'), '8125', 3));
            $this->assertSame(0, StoreStock::query()->whereIn('store_id', [$this->storeIds[1], $this->storeIds[2]])->where('quantity', '!=', 0)->count());
        });
    }

    public function test_query_count_does_not_grow_linearly_with_rows(): void
    {
        [, $small] = $this->timedCommit($this->previewToken($this->itemsCsv(300)), 300);

        $this->inTenant($this->tenant, function (): void {
            StockMovement::query()->delete();
            StoreStock::query()->forceDelete();
            Item::query()->forceDelete();
            Category::query()->forceDelete();
        });
        Event::forget(QueryExecuted::class);

        [, $large] = $this->timedCommit($this->previewToken($this->itemsCsv(3000)), 3000);

        // 10x the rows may add a few chunks, never 10x the queries.
        $this->assertLessThan($small * 3, $large, "300 rows: {$small} queries, 3,000 rows: {$large} queries.");
    }

    public function test_a_failure_in_the_middle_of_a_big_commit_rolls_everything_back(): void
    {
        // An item that existed before the import, with stock and a movement: must stay untouched.
        $before = $this->inTenant($this->tenant, function (): array {
            $item = Item::query()->create([
                'code' => 'OLD-1', 'name' => 'قديم', 'unit' => 'كجم', 'cost_price' => '5.000', 'selling_price' => '7.000',
                'weighted_avg_cost' => '5.000', 'min_selling_price' => '0.000', 'min_stock_level' => '0.000', 'current_stock' => '4.500', 'is_active' => true,
            ]);
            StoreStock::query()->create(['store_id' => $this->storeIds[0], 'item_id' => $item->id, 'quantity' => '4.500', 'min_stock' => '0.000']);

            return ['items' => Item::query()->count(), 'stocks' => StoreStock::query()->count(), 'movements' => StockMovement::query()->count(), 'categories' => Category::query()->count()];
        });

        $token = $this->previewToken($this->itemsCsv(1200));

        // Make the write blow up half-way: the 2nd batch of opening-stock movements fails.
        $movementInserts = 0;
        Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$movementInserts): void {
            if (preg_match('/^insert into [`"]stock_movements[`"]/i', $query->sql) === 1 && ++$movementInserts === 2) {
                throw new RuntimeException('forced failure mid-commit');
            }
        });

        $this->postJson('/api/v1/imports/items/commit', ['token' => $token], $this->tenantHeaders($this->tenant))->assertStatus(500);
        Event::forget(QueryExecuted::class);

        $this->inTenant($this->tenant, function () use ($before): void {
            $this->assertSame($before, ['items' => Item::query()->count(), 'stocks' => StoreStock::query()->count(), 'movements' => StockMovement::query()->count(), 'categories' => Category::query()->count()]);
            $this->assertSame('4.500', (string) Item::query()->where('code', 'OLD-1')->value('current_stock'));
            $this->assertSame('4.500', (string) StoreStock::query()->where('store_id', $this->storeIds[0])->value('quantity'));
        });
    }
}
