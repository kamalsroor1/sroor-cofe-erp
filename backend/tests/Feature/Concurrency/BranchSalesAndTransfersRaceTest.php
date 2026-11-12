<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Actions\Perf\VerifyTenantConsistencyAction;
use App\Actions\Reports\GetStockDriftReportAction;
use App\Exceptions\Stock\InsufficientStockException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RunsConcurrencyWorkers;
use Tests\TenantTestCase;

/**
 * PERF-04, manual result (3) of 2026-11-12 automated: a three-branch tenant under 72 sales
 * and 18 inter-branch transfers in 6 parallel processes, while 6 more processes race to
 * transfer 300.000 each out of a branch holding 1,000.000 of one item.
 *
 *  - exactly 3 of the 6 over-subscribed transfers pass, the other 3 are refused by the stock
 *    check (InsufficientStockException), never by a lock error;
 *  - every branch balance is exact, no balance is negative, the stock-drift (inventory
 *    variance) report is empty, invoice / transfer numbers are unique;
 *  - tenant:perf-verify finds nothing.
 *
 * The branch-scoped user checks of the same manual run (403 on other branches for invoices /
 * POS / shift / balances / sale, 404 on another branch's invoice by id, header branch 2 with
 * body branch 1 refused) are already automated by Tests\Feature\Stores\StoreIsolationApiTest
 * and StoreIsolationReturnsPurchasesExpensesTest, so they are not repeated here.
 */
#[Group('mysql')]
#[Group('mysql-only')]
#[Group('concurrency')]
final class BranchSalesAndTransfersRaceTest extends TenantTestCase
{
    use RunsConcurrencyWorkers;

    private const SALES_PER_WORKER = 12;

    private const TRANSFERS_PER_WORKER = 3;

    private const RACERS = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection($this->centralConnectionName())->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->fail("MySQL-only test run on `{$driver}`. Use: php artisan test -c phpunit.mysql.xml");
        }
    }

    public function test_concurrent_sales_and_transfers_keep_every_branch_exact(): void
    {
        $tenant = $this->createTenant();
        $main = (int) $this->tenantStore($tenant)->getKey();
        $userId = (int) $this->tenantAdmin($tenant)->getKey();

        $f = $this->inTenant($tenant, function () use ($main): array {
            $stores = [$main];
            foreach (['BR2', 'BR3'] as $code) {
                $stores[] = (int) Store::query()->create(['name' => 'فرع '.$code, 'code' => $code, 'type' => 'retail_shop', 'is_active' => true, 'is_main' => false])->getKey();
            }

            return [
                'stores' => $stores,
                'a' => $this->makeItem('QA-BR-A', 'كجم', array_fill_keys($stores, '200.000')),
                'b' => $this->makeItem('QA-BR-B', 'قطعة', array_fill_keys($stores, '200.000')),
                'z' => $this->makeItem('QA-BR-Z', 'كجم', [$stores[0] => '1000.000', $stores[1] => '0.000', $stores[2] => '0.000']),
                'customer' => (int) Customer::query()->create(['name' => 'عميل فروع', 'current_balance' => '0.000', 'is_active' => true])->getKey(),
            ];
        });
        [$s1, $s2, $s3] = $f['stores'];

        $jobs = [];
        $expected = ['a' => array_fill_keys($f['stores'], '200.000'), 'b' => array_fill_keys($f['stores'], '200.000')];
        for ($w = 0; $w < 6; $w++) {
            $store = $f['stores'][$w % 3];
            $next = $f['stores'][($w + 1) % 3];
            $ops = [];
            for ($n = 0; $n < self::SALES_PER_WORKER; $n++) {
                $ops[] = ['op' => 'checkout', 'data' => [
                    'client_uuid' => (string) Str::uuid(), 'customer_id' => $f['customer'], 'store_id' => $store, 'user_id' => $userId,
                    'payment_type' => 'cash', 'discount_type' => 'fixed', 'discount_value' => '0.000',
                    'items' => [
                        ['item_id' => $f['b'], 'quantity' => '1.000', 'unit_price' => '40.000', 'discount_amount' => '0.000'],
                        ['item_id' => $f['a'], 'quantity' => '0.250', 'unit_price' => '150.000', 'discount_amount' => '0.000'],
                    ],
                ]];
                $expected['a'][$store] = bcsub($expected['a'][$store], '0.250', 3);
                $expected['b'][$store] = bcsub($expected['b'][$store], '1.000', 3);
                if ($n % 4 === 3) {
                    $ops[] = ['op' => 'transfer', 'data' => [
                        'from_store_id' => $store, 'to_store_id' => $next, 'user_id' => $userId,
                        'items' => [['item_id' => $f['a'], 'quantity' => '1.000'], ['item_id' => $f['b'], 'quantity' => '2.000']],
                    ]];
                    foreach (['a' => '1.000', 'b' => '2.000'] as $key => $quantity) {
                        $expected[$key][$store] = bcsub($expected[$key][$store], $quantity, 3);
                        $expected[$key][$next] = bcadd($expected[$key][$next], $quantity, 3);
                    }
                }
            }
            $jobs[] = ['tenant' => $tenant, 'scenario' => 'batch', 'payload' => ['ops' => $ops]];
        }
        for ($r = 0; $r < self::RACERS; $r++) {
            $jobs[] = ['tenant' => $tenant, 'scenario' => 'batch', 'payload' => ['ops' => [['op' => 'transfer', 'tag' => 'race', 'data' => [
                'from_store_id' => $s1, 'to_store_id' => $r % 2 === 0 ? $s2 : $s3, 'user_id' => $userId,
                'items' => [['item_id' => $f['z'], 'quantity' => '300.000']],
            ]]]]];
        }

        $workers = $this->runWorkerJobs(base_path('tests/Support/batch-worker.php'), $jobs);

        $results = [];
        foreach ($workers as $worker) {
            $this->assertTrue($worker['ok'], (string) json_encode($worker, JSON_UNESCAPED_UNICODE));
            array_push($results, ...(array) $worker['results']);
        }
        $race = array_values(array_filter($results, fn (array $r): bool => $r['tag'] === 'race'));
        $rest = array_values(array_filter($results, fn (array $r): bool => $r['tag'] !== 'race'));

        $this->assertSame([], array_values(array_filter($rest, fn (array $r): bool => $r['ok'] !== true)), 'Every sale and in-stock transfer must succeed.');
        $this->assertCount(6 * (self::SALES_PER_WORKER + self::TRANSFERS_PER_WORKER), $rest);

        $won = array_values(array_filter($race, fn (array $r): bool => $r['ok'] === true));
        $lost = array_values(array_filter($race, fn (array $r): bool => $r['ok'] !== true));
        $this->assertCount(3, $won, '1000.000 fits three transfers of 300.000: '.json_encode($race, JSON_UNESCAPED_UNICODE));
        $this->assertCount(3, $lost);
        foreach ($lost as $failure) {
            $this->assertSame(InsufficientStockException::class, $failure['error_class'], (string) json_encode($failure, JSON_UNESCAPED_UNICODE));
        }

        $state = $this->inTenant($tenant, function () use ($f): array {
            $quantities = fn (int $item): array => StoreStock::query()->where('item_id', $item)->orderBy('store_id')
                ->pluck('quantity', 'store_id')->map(fn ($q): string => (string) $q)->all();

            return [
                'a' => $quantities($f['a']),
                'b' => $quantities($f['b']),
                'z' => $quantities($f['z']),
                'z_total' => (string) Item::query()->whereKey($f['z'])->value('current_stock'),
                'negative' => StoreStock::query()->where('quantity', '<', 0)->count(),
                'drift' => app(GetStockDriftReportAction::class)->execute(1, 0)['items'],
                'invoices' => [Invoice::query()->count(), Invoice::query()->distinct()->count('invoice_number')],
                'transfers' => [StockTransfer::query()->count(), StockTransfer::query()->distinct()->count('transfer_number')],
                'problems' => app(VerifyTenantConsistencyAction::class)->execute()['problems'],
            ];
        });

        ksort($expected['a']);
        ksort($expected['b']);
        $this->assertSame($expected['a'], $state['a']);
        $this->assertSame($expected['b'], $state['b']);
        $this->assertSame('100.000', $state['z'][$s1]);
        $this->assertSame('900.000', bcadd($state['z'][$s2], $state['z'][$s3], 3));
        $this->assertSame('1000.000', $state['z_total']);
        $this->assertSame(0, $state['negative']);
        $this->assertSame([], $state['drift'], 'The inventory variance (stock drift) report must be empty.');
        $this->assertSame([72, 72], $state['invoices']);
        $this->assertSame([18 + 3, 18 + 3], $state['transfers']);
        $this->assertSame([], $state['problems']);
    }

    /**
     * @param  array<int, string>  $perStore
     */
    private function makeItem(string $code, string $unit, array $perStore): int
    {
        $total = '0.000';
        foreach ($perStore as $quantity) {
            $total = bcadd($total, $quantity, 3);
        }

        $item = Item::query()->create([
            'code' => $code, 'name' => 'صنف '.$code, 'category' => 'coffee_beans', 'unit' => $unit, 'is_weighted' => $unit === 'كجم',
            'cost_price' => '100.000', 'weighted_avg_cost' => '100.000', 'selling_price' => '150.000', 'current_stock' => $total,
            'min_stock_level' => '0.000', 'is_active' => true,
        ]);
        foreach ($perStore as $storeId => $quantity) {
            StoreStock::query()->updateOrCreate(['store_id' => $storeId, 'item_id' => $item->id], ['quantity' => $quantity]);
        }

        return (int) $item->id;
    }
}
