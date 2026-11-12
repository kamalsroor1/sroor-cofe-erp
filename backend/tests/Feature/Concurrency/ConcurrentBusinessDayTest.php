<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Actions\Perf\VerifyTenantConsistencyAction;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\ShiftService;
use App\Services\TreasuryService;
use App\Support\TenantClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RunsConcurrencyWorkers;
use Tests\TenantTestCase;

/**
 * PERF-04, manual result (2) of 2026-11-12 automated: a full business day on THREE tenants at
 * the same time (per tenant: 40 cash sales, 10 credit sales, 6 expenses, 3 purchases, 5
 * collections, 2 supplier payments, 5 cash returns), 4 parallel processes per tenant released
 * together. Afterwards, per tenant and to the last piaster: the shift's expected cash, the
 * treasury cash balance, the stock of both items, the customer and the supplier balance; the
 * shift closes with a zero difference; a sale after closing is refused with 422 (the branch
 * requires a shift); and tenant:perf-verify finds nothing.
 *
 * MySQL / MariaDB only (real row locks); `mysql-only` + `concurrency` like the other races.
 */
#[Group('mysql')]
#[Group('mysql-only')]
#[Group('concurrency')]
final class ConcurrentBusinessDayTest extends TenantTestCase
{
    use RunsConcurrencyWorkers;

    private const TENANTS = 3;

    private const WORKERS_PER_TENANT = 4;

    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection($this->centralConnectionName())->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->fail("MySQL-only test run on `{$driver}`. Use: php artisan test -c phpunit.mysql.xml");
        }
    }

    public function test_a_concurrent_business_day_on_three_tenants_reconciles_exactly(): void
    {
        $tenants = [];
        $fixtures = [];
        for ($t = 0; $t < self::TENANTS; $t++) {
            $tenants[$t] = $this->createTenant();
            $fixtures[$t] = $this->prepareDay($tenants[$t]);
        }

        $jobs = [];
        foreach ($tenants as $t => $tenant) {
            foreach ($this->splitIntoWorkers($this->dayOperations($fixtures[$t])) as $ops) {
                $jobs[] = ['tenant' => $tenant, 'scenario' => 'batch', 'payload' => ['ops' => $ops]];
            }
        }

        $started = microtime(true);
        $workers = $this->runWorkerJobs(base_path('tests/Support/batch-worker.php'), $jobs);
        fwrite(STDERR, sprintf("\n[PERF-04 business day] engine=%s processes=%d operations=%d seconds=%.1f\n", $this->serverVersion(), count($jobs), 71 * self::TENANTS, microtime(true) - $started));

        $failures = [];
        foreach ($workers as $worker) {
            $this->assertTrue($worker['ok'], (string) json_encode($worker, JSON_UNESCAPED_UNICODE));
            foreach ((array) $worker['results'] as $result) {
                if ($result['ok'] !== true) {
                    $failures[] = $result;
                }
            }
        }
        $this->assertSame([], $failures, 'Every operation of the day must succeed: '.json_encode($failures, JSON_UNESCAPED_UNICODE));

        foreach ($tenants as $t => $tenant) {
            $this->assertDayReconciles($tenant, $fixtures[$t]);
        }
    }

    /**
     * Per tenant: two items with 500 in the main branch, a cash and a credit customer, a
     * supplier, five cash sales made BEFORE the shift (the day's returns refer to them) and
     * an open shift with 1,000.000 opening cash.
     *
     * @return array{store: int, user: int, x: int, y: int, cash_customer: int, credit_customer: int, supplier: int, return_sources: list<array{invoice: int, line: int}>, shift: int}
     */
    private function prepareDay(Tenant $tenant): array
    {
        $storeId = (int) $this->tenantStore($tenant)->getKey();
        $userId = (int) $this->tenantAdmin($tenant)->getKey();

        return $this->inTenant($tenant, function () use ($storeId, $userId): array {
            $x = $this->makeItem('QA-DAY-X', 'كجم', '100.000', '150.000', $storeId);
            $y = $this->makeItem('QA-DAY-Y', 'قطعة', '25.000', '40.000', $storeId);
            $cash = (int) Customer::query()->create(['name' => 'عميل نقدي يومي', 'current_balance' => '0.000', 'is_active' => true])->getKey();
            $credit = (int) Customer::query()->create(['name' => 'عميل آجل يومي', 'current_balance' => '0.000', 'is_active' => true])->getKey();
            $supplier = (int) Supplier::query()->create(['name' => 'مورد يومي', 'current_balance' => '0.000', 'is_active' => true])->getKey();

            $sources = [];
            for ($r = 0; $r < 5; $r++) {
                $invoice = app(InvoiceService::class)->confirmInvoice([
                    'client_uuid' => (string) Str::uuid(), 'customer_id' => $cash, 'store_id' => $storeId, 'user_id' => $userId,
                    'payment_type' => 'cash', 'discount_type' => 'fixed', 'discount_value' => '0.000',
                    'items' => [
                        ['item_id' => $x, 'quantity' => '1.000', 'unit_price' => '150.000', 'discount_amount' => '0.000'],
                        ['item_id' => $y, 'quantity' => '2.000', 'unit_price' => '40.000', 'discount_amount' => '0.000'],
                    ],
                ]);
                $sources[] = ['invoice' => (int) $invoice->id, 'line' => (int) $invoice->items()->where('item_id', $y)->value('id')];
            }
            // Yesterday's sales, so they sit before the shift window on any clock.
            $earlier = now()->subHour();
            DB::table('invoices')->update(['created_at' => $earlier]);
            DB::table('payments')->update(['created_at' => $earlier]);

            $shift = app(ShiftService::class)->openShift('1000.000', null, $storeId, $userId);

            return [
                'store' => $storeId, 'user' => $userId, 'x' => $x, 'y' => $y, 'cash_customer' => $cash,
                'credit_customer' => $credit, 'supplier' => $supplier, 'return_sources' => $sources, 'shift' => (int) $shift->id,
            ];
        });
    }

    /**
     * The day's 71 operations, interleaved by type.
     *
     * @param  array{store: int, user: int, x: int, y: int, cash_customer: int, credit_customer: int, supplier: int, return_sources: list<array{invoice: int, line: int}>, shift: int}  $f
     * @return list<array{op: string, data: array<string, mixed>}>
     */
    private function dayOperations(array $f): array
    {
        $sale = fn (int $customer, string $type, array $items): array => ['op' => 'checkout', 'data' => [
            'client_uuid' => (string) Str::uuid(), 'customer_id' => $customer, 'store_id' => $f['store'], 'user_id' => $f['user'],
            'payment_type' => $type, 'discount_type' => 'fixed', 'discount_value' => '0.000', 'items' => $items,
        ]];
        $groups = [
            array_fill(0, 40, $sale($f['cash_customer'], 'cash', [
                ['item_id' => $f['x'], 'quantity' => '0.250', 'unit_price' => '150.000', 'discount_amount' => '0.000'],
                ['item_id' => $f['y'], 'quantity' => '1.000', 'unit_price' => '40.000', 'discount_amount' => '0.000'],
            ])),
            array_fill(0, 10, $sale($f['credit_customer'], 'credit', [
                ['item_id' => $f['x'], 'quantity' => '1.000', 'unit_price' => '150.000', 'discount_amount' => '0.000'],
            ])),
            array_fill(0, 6, ['op' => 'expense', 'data' => [
                'title' => 'مصروف يومي', 'category' => 'supplies', 'amount' => '25.500', 'payment_method' => 'cash',
                'expense_date' => app(TenantClock::class)->businessDate(), 'store_id' => $f['store'], 'user_id' => $f['user'],
            ]]),
            array_fill(0, 3, ['op' => 'purchase', 'data' => [
                'supplier_id' => $f['supplier'], 'store_id' => $f['store'], 'user_id' => $f['user'], 'paid_amount' => '100.000',
                'payment_method' => 'cash', 'items' => [['item_id' => $f['y'], 'quantity' => '10.000', 'cost_price' => '25.000']],
            ]]),
            array_fill(0, 5, ['op' => 'collection', 'data' => [
                'customer_id' => $f['credit_customer'], 'amount' => '60.000', 'payment_method' => 'cash', 'store_id' => $f['store'], 'user_id' => $f['user'],
            ]]),
            array_fill(0, 2, ['op' => 'supplier_payment', 'data' => [
                'supplier_id' => $f['supplier'], 'amount' => '50.000', 'payment_method' => 'cash', 'store_id' => $f['store'], 'user_id' => $f['user'],
            ]]),
            array_map(fn (array $source): array => ['op' => 'return', 'data' => [
                'return_type' => 'sales_return', 'invoice_id' => $source['invoice'], 'customer_id' => $f['cash_customer'],
                'store_id' => $f['store'], 'user_id' => $f['user'], 'refund_method' => 'cash',
                'items' => [['invoice_item_id' => $source['line'], 'item_id' => $f['y'], 'quantity' => '1.000']],
            ]], $f['return_sources']),
        ];

        // Each sale needs its own idempotency key.
        $groups[0] = array_map(fn (array $op): array => ['op' => 'checkout', 'data' => ['client_uuid' => (string) Str::uuid()] + $op['data']], $groups[0]);
        $groups[1] = array_map(fn (array $op): array => ['op' => 'checkout', 'data' => ['client_uuid' => (string) Str::uuid()] + $op['data']], $groups[1]);

        $ops = [];
        for ($round = 0; $round < 40; $round++) {
            foreach ($groups as $group) {
                if (isset($group[$round])) {
                    $ops[] = $group[$round];
                }
            }
        }

        return $ops;
    }

    /**
     * @param  list<array{op: string, data: array<string, mixed>}>  $ops
     * @return list<list<array{op: string, data: array<string, mixed>}>>
     */
    private function splitIntoWorkers(array $ops): array
    {
        $workers = array_fill(0, self::WORKERS_PER_TENANT, []);
        foreach ($ops as $n => $op) {
            $workers[$n % self::WORKERS_PER_TENANT][] = $op;
        }

        return $workers;
    }

    /**
     * @param  array{store: int, user: int, x: int, y: int, cash_customer: int, credit_customer: int, supplier: int, return_sources: list<array{invoice: int, line: int}>, shift: int}  $f
     */
    private function assertDayReconciles(Tenant $tenant, array $f): void
    {
        $state = $this->inTenant($tenant, function () use ($f): array {
            $shift = CashShift::query()->findOrFail($f['shift']);

            return [
                'shift' => app(ShiftService::class)->calculateShiftTotals($shift),
                'treasury_cash' => app(TreasuryService::class)->getBalances($f['store'])['cash']['balance'],
                'x' => [(string) Item::query()->whereKey($f['x'])->value('current_stock'), (string) StoreStock::query()->where('item_id', $f['x'])->value('quantity')],
                'y' => [(string) Item::query()->whereKey($f['y'])->value('current_stock'), (string) StoreStock::query()->where('item_id', $f['y'])->value('quantity')],
                'credit_customer' => (string) Customer::query()->whereKey($f['credit_customer'])->value('current_balance'),
                'cash_customer' => (string) Customer::query()->whereKey($f['cash_customer'])->value('current_balance'),
                'supplier' => (string) Supplier::query()->whereKey($f['supplier'])->value('current_balance'),
                'invoices' => Invoice::query()->count(),
                'invoice_numbers' => Invoice::query()->distinct()->count('invoice_number'),
                'payment_numbers' => [Payment::query()->count(), Payment::query()->distinct()->count('payment_number')],
                'problems' => app(VerifyTenantConsistencyAction::class)->execute()['problems'],
            ];
        });

        // Cash: 1000 opening + 40 x 77.500 sales + 5 x 60 collections - 6 x 25.500 expenses
        //       - (3 x 100 purchase payments + 2 x 50 supplier payments) - 5 x 40 cash refunds.
        $this->assertSame('3100.000', $state['shift']['total_cash_sales']);
        $this->assertSame('300.000', $state['shift']['total_customer_receipts']);
        $this->assertSame('1500.000', $state['shift']['total_credit_sales']);
        $this->assertSame('153.000', $state['shift']['total_expenses']);
        $this->assertSame('400.000', $state['shift']['total_supplier_paid']);
        $this->assertSame('200.000', $state['shift']['total_refunds']);
        $this->assertSame('3647.000', $state['shift']['expected_cash_balance']);
        // Treasury = the drawer + the five earlier cash sales (5 x 230.000).
        $this->assertSame('4797.000', $state['treasury_cash']);

        $this->assertSame(['475.000', '475.000'], $state['x'], '500 - 5 x 1 - 40 x 0.250 - 10 x 1');
        $this->assertSame(['485.000', '485.000'], $state['y'], '500 - 5 x 2 - 40 x 1 + 3 x 10 + 5 x 1');
        $this->assertSame('1200.000', $state['credit_customer'], '10 x 150 - 5 x 60');
        $this->assertSame('0.000', $state['cash_customer']);
        $this->assertSame('350.000', $state['supplier'], '3 x 250 - 3 x 100 - 2 x 50');
        $this->assertSame(55, $state['invoices']);
        $this->assertSame(55, $state['invoice_numbers']);
        $this->assertSame($state['payment_numbers'][0], $state['payment_numbers'][1], 'Duplicate payment number.');
        $this->assertSame([], $state['problems']);

        // Close with exactly the expected cash: zero difference.
        $closed = $this->inTenant($tenant, fn (): CashShift => app(ShiftService::class)->closeShift(CashShift::query()->findOrFail($f['shift']), '3647.000'));
        $this->assertSame('0.000', (string) $closed->cash_difference);
        $this->assertSame('closed', (string) $closed->status);

        // The branch requires a shift (shipped default): a sale after closing is refused.
        $admin = $this->inTenant($tenant, fn (): User => User::query()->findOrFail($f['user']));
        $this->postJson('/api/v1/pos/checkout', [
            'customer_id' => $f['cash_customer'], 'payment_type' => 'cash', 'payment_method' => 'cash',
            'items' => [['item_id' => $f['y'], 'quantity' => '1.000']],
        ], $this->tenantHeaders($tenant, $admin, $f['store']))->assertStatus(422)->assertJsonValidationErrors('shift');
        $this->assertSame(55, $this->inTenant($tenant, fn (): int => Invoice::query()->count()));
    }

    private function makeItem(string $code, string $unit, string $cost, string $price, int $storeId): int
    {
        $item = Item::query()->create([
            'code' => $code, 'name' => 'صنف '.$code, 'category' => 'coffee_beans', 'unit' => $unit, 'is_weighted' => $unit === 'كجم',
            'cost_price' => $cost, 'weighted_avg_cost' => $cost, 'selling_price' => $price, 'current_stock' => '500.000',
            'min_stock_level' => '0.000', 'is_active' => true,
        ]);
        StoreStock::query()->updateOrCreate(['store_id' => $storeId, 'item_id' => $item->id], ['quantity' => '500.000']);

        return (int) $item->id;
    }

    private function serverVersion(): string
    {
        return (string) DB::connection($this->centralConnectionName())->selectOne('select version() as v')->v;
    }
}
