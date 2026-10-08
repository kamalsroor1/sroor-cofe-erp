<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Services\StockService;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * QA-1: concurrency guarantees that sqlite cannot prove (phase-1-plan R7).
 *
 * sqlite serialises every writer on a file lock, so `lockForUpdate()` is a no-op
 * there and a missing lock never shows up as a failing test. These tests run only
 * on MySQL 8 (CI job `mysql`, `phpunit.mysql.xml`) against real tenant databases
 * created by the IDEN-4.8 harness:
 *
 *  - a deterministic probe that the stock engine really takes an InnoDB row lock;
 *  - real parallel PHP processes (tests/Support/concurrency-worker.php) racing on
 *    the same stock row and on the same POS idempotency key (`client_uuid`);
 *  - exact DECIMAL(12,3) round trips through MySQL.
 *
 * On sqlite every test is skipped with an explicit reason.
 */
#[Group('mysql')]
#[Group('concurrency')]
final class MysqlConcurrencyTest extends TenantTestCase
{
    /** MySQL error code for "Lock wait timeout exceeded". */
    private const LOCK_WAIT_TIMEOUT = 1205;

    /** Seconds a worker may take to boot Laravel and connect before the race starts. */
    private const WORKER_READY_TIMEOUT = 60;

    private const WORKERS = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection($this->centralConnectionName())->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('MySQL-only: sqlite has no row locks. Runs in the CI `mysql` job (phpunit.mysql.xml).');
        }
    }

    public function test_stock_deduction_blocks_on_the_item_row_lock(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->getKey();
        $itemId = $this->inTenant($tenant, fn (): int => (int) $this->makeItem('QA-LOCK-01', '5.000')->getKey());

        $holder = $this->tenantSideConnection($tenant, 'qa_lock_holder');
        $holder->beginTransaction();

        try {
            $holder->selectOne('select id from items where id = ? for update', [$itemId]);

            [$plainRead, $errorCode] = $this->inTenant($tenant, function () use ($itemId, $storeId): array {
                DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

                // A plain read is not blocked (MVCC), so only a locking read can make the
                // deduction wait: a timeout below proves deductStock() uses lockForUpdate().
                $plain = (string) Item::query()->whereKey($itemId)->value('current_stock');

                try {
                    $this->deduct($itemId, '1.000', $storeId, 'QA-LOCK-BLOCKED');

                    return [$plain, null];
                } catch (QueryException $e) {
                    return [$plain, (int) ($e->errorInfo[1] ?? 0)];
                }
            });
        } finally {
            $holder->rollBack();
            DB::purge('qa_lock_holder');
        }

        $this->assertSame('5.000', $plainRead);
        $this->assertSame(self::LOCK_WAIT_TIMEOUT, $errorCode, 'deductStock() did not wait for the row lock: lockForUpdate() is missing.');

        // Lock released: the same deduction now goes through, and DECIMAL(12,3) is exact.
        $after = $this->inTenant($tenant, function () use ($itemId, $storeId): array {
            $this->deduct($itemId, '1.250', $storeId, 'QA-LOCK-01');
            $this->deduct($itemId, '0.001', $storeId, 'QA-LOCK-02');

            return [
                'stock' => (string) Item::query()->whereKey($itemId)->value('current_stock'),
                'movements' => StockMovement::query()->where('item_id', $itemId)->orderBy('id')->pluck('stock_after')->map(fn ($v): string => (string) $v)->all(),
            ];
        });

        $this->assertSame(['stock' => '3.749', 'movements' => ['3.750', '3.749']], $after);
    }

    public function test_parallel_deductions_never_oversell_the_stock(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->getKey();
        $itemId = $this->inTenant($tenant, fn (): int => (int) $this->makeItem('QA-RACE-01', '4.000')->getKey());

        $payloads = [];
        for ($i = 1; $i <= self::WORKERS; $i++) {
            $payloads[] = ['item_id' => $itemId, 'quantity' => '1.000', 'store_id' => $storeId, 'document' => 'QA-RACE-'.$i];
        }

        $results = $this->runWorkers('deduct', $tenant, $payloads);

        $succeeded = array_values(array_filter($results, fn (array $r): bool => $r['ok'] === true));
        $failed = array_values(array_filter($results, fn (array $r): bool => $r['ok'] !== true));

        $this->assertCount(4, $succeeded, 'Exactly the available 4.000 must be sold: '.json_encode($results, JSON_UNESCAPED_UNICODE));
        $this->assertCount(2, $failed);
        foreach ($failed as $failure) {
            // Rejected by the stock check, not by a deadlock or a crash.
            $this->assertSame('Exception', $failure['error_class'], (string) json_encode($failure, JSON_UNESCAPED_UNICODE));
        }

        $state = $this->inTenant($tenant, fn (): array => [
            'stock' => (string) Item::query()->whereKey($itemId)->value('current_stock'),
            'movements' => StockMovement::query()->where('item_id', $itemId)->count(),
            'stock_after' => StockMovement::query()->where('item_id', $itemId)->orderBy('id')->pluck('stock_after')->map(fn ($v): string => (string) $v)->all(),
        ]);

        $this->assertSame([
            'stock' => '0.000',
            'movements' => 4,
            // Serialised by the row lock: every movement saw the previous one's result.
            'stock_after' => ['3.000', '2.000', '1.000', '0.000'],
        ], $state);
    }

    public function test_parallel_replays_of_one_client_uuid_create_a_single_invoice(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->getKey();

        [$itemId, $customerId] = $this->inTenant($tenant, function (): array {
            $item = $this->makeItem('QA-UUID-01', '10.000');
            $customer = Customer::query()->create(['name' => 'عميل اختبار التزامن', 'current_balance' => '0.000', 'is_active' => true]);

            return [(int) $item->getKey(), (int) $customer->getKey()];
        });

        $clientUuid = (string) Str::uuid();
        $checkout = [
            'client_uuid' => $clientUuid,
            'customer_id' => $customerId,
            'store_id' => $storeId,
            'payment_type' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '0.000',
            'items' => [
                ['item_id' => $itemId, 'quantity' => '1.500', 'unit_price' => '150.000', 'discount_amount' => '0.000'],
            ],
        ];

        $results = $this->runWorkers('checkout', $tenant, array_fill(0, self::WORKERS, $checkout));

        foreach ($results as $result) {
            $this->assertTrue($result['ok'], 'A replay of the same client_uuid must return the original invoice: '.json_encode($result, JSON_UNESCAPED_UNICODE));
        }

        $invoiceIds = array_unique(array_map(fn (array $r): int => (int) $r['invoice_id'], $results));
        $created = array_filter($results, fn (array $r): bool => $r['created'] === true);

        $this->assertCount(1, $invoiceIds, 'Parallel replays produced more than one invoice id.');
        $this->assertCount(1, $created, 'Exactly one worker may create the invoice; the others replay it.');

        $state = $this->inTenant($tenant, fn (): array => [
            'invoices' => Invoice::query()->where('client_uuid', $clientUuid)->count(),
            'stock' => (string) Item::query()->whereKey($itemId)->value('current_stock'),
            'movements' => StockMovement::query()->where('item_id', $itemId)->count(),
        ]);

        $this->assertSame(['invoices' => 1, 'stock' => '8.500', 'movements' => 1], $state);
    }

    private function makeItem(string $code, string $stock): Item
    {
        return Item::query()->create([
            'code' => $code,
            'name' => 'صنف تزامن '.$code,
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '100.000',
            'selling_price' => '150.000',
            'current_stock' => $stock,
            'min_stock_level' => '0.000',
            'is_active' => true,
        ]);
    }

    private function deduct(int $itemId, string $quantity, int $storeId, string $document): void
    {
        DB::transaction(function () use ($itemId, $quantity, $storeId, $document): void {
            $item = Item::query()->findOrFail($itemId);
            app(StockService::class)->deductStock($item, $quantity, $item, $document, 'sales_out', null, $storeId);
        });
    }

    /** A second MySQL session on the tenant's schema (a different transaction than the test's). */
    private function tenantSideConnection(Tenant $tenant, string $name): Connection
    {
        config([
            "database.connections.{$name}" => array_merge(
                (array) config('database.connections.'.$this->centralConnectionName()),
                ['database' => $this->tenantDatabaseName($tenant)],
            ),
        ]);

        return DB::connection($name);
    }

    /**
     * Start one PHP process per payload, wait until every process has booted and
     * connected, release them at the same moment and collect their JSON results.
     *
     * @param  list<array<string, mixed>>  $payloads
     * @return list<array<string, mixed>>
     */
    private function runWorkers(string $scenario, Tenant $tenant, array $payloads): array
    {
        $script = base_path('tests/Support/concurrency-worker.php');
        $database = $this->tenantDatabaseName($tenant);
        $workers = [];

        try {
            foreach ($payloads as $payload) {
                $stderr = (string) tempnam(sys_get_temp_dir(), 'qa-worker-');
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, $script, $scenario, $database, base64_encode((string) json_encode($payload))],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $stderr, 'w']],
                    $pipes,
                    base_path(),
                );

                if (! is_resource($process)) {
                    throw new RuntimeException('Could not start a concurrency worker.');
                }

                $workers[] = ['process' => $process, 'pipes' => $pipes, 'stderr' => $stderr];
            }

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipes'][1], self::WORKER_READY_TIMEOUT);
                $line = trim((string) fgets($worker['pipes'][1]));
                if ($line !== 'READY') {
                    throw new RuntimeException('Worker did not become ready: '.$line.' '.(string) file_get_contents($worker['stderr']));
                }
            }

            // Barrier released: all workers hit the database at (almost) the same moment.
            foreach ($workers as $worker) {
                fwrite($worker['pipes'][0], "GO\n");
                fflush($worker['pipes'][0]);
            }

            $results = [];
            foreach ($workers as $worker) {
                $output = trim((string) stream_get_contents($worker['pipes'][1]));
                // Not \R: without /u it also splits on byte 0x85, which occurs inside Arabic UTF-8.
                $lines = preg_split('/\r?\n/', $output) ?: [];
                $decoded = json_decode((string) end($lines), true);

                if (! is_array($decoded) || ! array_key_exists('ok', $decoded)) {
                    throw new RuntimeException('Worker returned no result: '.$output.' '.(string) file_get_contents($worker['stderr']));
                }

                $results[] = $decoded;
            }

            return $results;
        } finally {
            foreach ($workers as $worker) {
                foreach ($worker['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($worker['process']);
                @unlink($worker['stderr']);
            }
        }
    }
}
