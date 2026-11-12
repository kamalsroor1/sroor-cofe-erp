<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Actions\Items\AdjustItemStockAction;
use App\Actions\Returns\DeleteReturnAction;
use App\DTOs\Items\AdjustStockDTO;
use App\Exceptions\Stock\InsufficientStockException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Services\ReturnService;
use App\Services\ShiftService;
use App\Services\StockService;
use App\Services\StockTransferService;
use App\Services\TreasuryService;
use App\Support\Database\TransactionRetry;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RunsConcurrencyWorkers;
use Tests\TenantTestCase;

/**
 * BUG-04 / BUG-07 on a real InnoDB server, MySQL 8.4 AND MariaDB 11.8 (innodb_snapshot_isolation
 * ON by default since 11.6.2). CI runs this class on both engines (`mysql` and `mariadb` jobs).
 *
 *  - BUG-04: with no document_sequences row, the first invoice / payment / return / purchase /
 *    transfer of the day succeeds on the FIRST attempt (no rolled-back attempt at all), both
 *    with the shipped settings and with the retry and READ COMMITTED switched off (proves the
 *    cause, creating the row after the read view, is fixed, not just retried away).
 *  - BUG-07: an internal retry is idempotent (one document, one number, no gap, one stock
 *    movement, one payment); a domain failure after the number was taken leaves nothing; and
 *    opposing transfers racing with sales of the same items finish with zero failures and exact
 *    balances.
 */
#[Group('mysql')]
#[Group('mysql-only')]
#[Group('concurrency')]
final class MysqlLockConflictTest extends TenantTestCase
{
    use RunsConcurrencyWorkers;

    /** Parallel processes per round of the transfer/sale race (12 like the field measurement). */
    private const RACE_WORKERS = 12;

    private const RACE_ROUNDS = 2;

    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection($this->centralConnectionName())->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->fail("MySQL-only test run on `{$driver}`. Use: php artisan test -c phpunit.mysql.xml");
        }
    }

    /** @return array<string, array{bool}> */
    public static function settings(): array
    {
        return [
            'shipped settings (READ COMMITTED + 3 attempts)' => [false],
            'cause only (server isolation, 1 attempt)' => [true],
        ];
    }

    #[DataProvider('settings')]
    public function test_the_first_document_of_each_type_succeeds_on_the_first_attempt(bool $withoutSafetyNets): void
    {
        if ($withoutSafetyNets) {
            // Server default isolation (REPEATABLE READ, snapshot check ON on MariaDB) and no retry.
            config(['database.transaction_retry.isolation' => '', 'database.transaction_retry.attempts' => 1]);
        }

        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->getKey();

        $result = $this->inTenant($tenant, function () use ($storeId): array {
            $secondStoreId = (int) Store::query()->create(['name' => 'فرع تاني', 'code' => 'BR2', 'type' => 'retail_shop', 'is_active' => true, 'is_main' => false])->getKey();
            $item = $this->makeItem('QA-FIRST-DOC', [$storeId => '50.000', $secondStoreId => '0.000']);
            $customer = Customer::query()->create(['name' => 'عميل أول يوم', 'current_balance' => '0.000', 'is_active' => true]);
            $supplier = Supplier::query()->create(['name' => 'مورد أول يوم', 'current_balance' => '0.000', 'is_active' => true]);

            $this->assertSame(0, DB::table('document_sequences')->count(), 'No counter row may exist yet: every document below is the first of its type today.');

            $rollbacks = 0;
            Event::listen(TransactionRolledBack::class, function () use (&$rollbacks): void {
                $rollbacks++;
            });

            $invoice = app(InvoiceService::class)->confirmInvoice($this->checkoutPayload((int) $customer->id, (int) $item->id, $storeId, '2.000'));
            $collection = app(PaymentService::class)->recordCustomerPayment(['customer_id' => $customer->id, 'amount' => '10.000', 'payment_method' => 'cash', 'store_id' => $storeId]);
            $return = app(ReturnService::class)->createReturn([
                'return_type' => 'sales_return',
                'customer_id' => $customer->id,
                'store_id' => $storeId,
                'refund_method' => 'cash',
                'items' => [['item_id' => $item->id, 'quantity' => '0.500', 'unit_price' => '150.000']],
            ]);
            $purchase = app(PurchaseService::class)->createPurchase([
                'supplier_id' => $supplier->id,
                'store_id' => $storeId,
                'paid_amount' => '250.000',
                'payment_method' => 'cash',
                'items' => [['item_id' => $item->id, 'quantity' => '2.500', 'cost_price' => '100.000']],
            ]);
            $supplierPayment = app(PaymentService::class)->recordSupplierPayment(['supplier_id' => $supplier->id, 'amount' => '5.000', 'payment_method' => 'cash', 'store_id' => $storeId]);
            $transfer = app(StockTransferService::class)->createTransfer([
                'from_store_id' => $storeId,
                'to_store_id' => $secondStoreId,
                'items' => [['item_id' => $item->id, 'quantity' => '1.250']],
            ]);

            return [
                'rollbacks' => $rollbacks,
                'numbers' => [
                    $invoice->invoice_number,
                    (string) Payment::query()->where('invoice_id', $invoice->id)->value('payment_number'),
                    $collection->payment_number,
                    $return->return_number,
                    (string) Payment::query()->where('return_id', $return->id)->value('payment_number'),
                    $purchase->purchase_number,
                    (string) Payment::query()->where('purchase_id', $purchase->id)->value('payment_number'),
                    $supplierPayment->payment_number,
                    $transfer->transfer_number,
                ],
                'stock' => [
                    'item' => (string) Item::query()->whereKey($item->id)->value('current_stock'),
                    'store_1' => $this->storeQuantity($storeId, (int) $item->id),
                    'store_2' => $this->storeQuantity($secondStoreId, (int) $item->id),
                ],
            ];
        });

        $this->assertSame(0, $result['rollbacks'], 'A first-of-day document needed a second attempt (BUG-04 1020 still happens): '.json_encode($result, JSON_UNESCAPED_UNICODE));

        $suffixes = array_map(fn (string $n): string => substr($n, (int) strrpos($n, '-') + 1), $result['numbers']);
        $this->assertSame(['0001', '000001', '000001', '0001', '000001', '0001', '000001', '000001', '0001'], $suffixes, json_encode($result['numbers'], JSON_UNESCAPED_UNICODE));

        // 50 - 2 sold + 0.5 returned + 2.5 bought = 51 in total; 1.25 of it moved to branch 2.
        $this->assertSame(['item' => '51.000', 'store_1' => '49.750', 'store_2' => '1.250'], $result['stock']);
    }

    public function test_an_internal_retry_does_not_duplicate_the_document_number_stock_or_payment(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->getKey();

        $state = $this->inTenant($tenant, function () use ($storeId): array {
            $item = $this->makeItem('QA-RETRY-01', [$storeId => '10.000']);
            $customer = Customer::query()->create(['name' => 'عميل إعادة المحاولة', 'current_balance' => '0.000', 'is_active' => true]);

            // First attempt dies with a deadlock AFTER the invoice number, the stock deduction and
            // the payment number were taken; the helper must restart the whole unit of work.
            $injected = 0;
            Payment::created(function () use (&$injected): void {
                if ($injected++ === 0) {
                    $pdo = new PDOException('SQLSTATE[40001]: Deadlock found when trying to get lock');
                    $pdo->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction'];

                    throw new QueryException('tenant', 'insert into `payments`', [], $pdo);
                }
            });
            $rollbacks = 0;
            Event::listen(TransactionRolledBack::class, function () use (&$rollbacks): void {
                $rollbacks++;
            });

            $invoice = app(InvoiceService::class)->confirmInvoice($this->checkoutPayload((int) $customer->id, (int) $item->id, $storeId, '1.500'));

            return [
                'rollbacks' => $rollbacks,
                'invoice_number_suffix' => substr($invoice->invoice_number, -4),
                'invoices' => Invoice::query()->count(),
                'payments' => Payment::query()->pluck('payment_number')->map(fn ($n): string => substr((string) $n, -6))->all(),
                'payment_total' => (string) Payment::query()->sum('amount'),
                'movements' => StockMovement::query()->where('item_id', $item->id)->count(),
                'item_stock' => (string) Item::query()->whereKey($item->id)->value('current_stock'),
                'store_stock' => $this->storeQuantity($storeId, (int) $item->id),
                'counters' => DB::table('document_sequences')->orderBy('scope')->pluck('last_value')->map(fn ($v): int => (int) $v)->all(),
                'customer_balance' => (string) Customer::query()->whereKey($customer->id)->value('current_balance'),
            ];
        });

        $this->assertSame([
            'rollbacks' => 1,
            'invoice_number_suffix' => '0001',
            'invoices' => 1,
            'payments' => ['000001'],
            'payment_total' => '225.000',
            'movements' => 1,
            'item_stock' => '8.500',
            'store_stock' => '8.500',
            // INV-… and PAY-INV-… each advanced exactly once: the failed attempt's numbers rolled back.
            'counters' => [1, 1],
            'customer_balance' => '0.000',
        ], $state);
    }

    public function test_a_failure_after_the_number_is_reserved_leaves_no_stock_payment_or_gap(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->getKey();

        $state = $this->inTenant($tenant, function () use ($storeId): array {
            $plenty = $this->makeItem('QA-ROLLBACK-A', [$storeId => '10.000']);
            $scarce = $this->makeItem('QA-ROLLBACK-B', [$storeId => '0.250']);
            $customer = Customer::query()->create(['name' => 'عميل تراجع', 'current_balance' => '0.000', 'is_active' => true]);

            $payload = $this->checkoutPayload((int) $customer->id, (int) $plenty->id, $storeId, '1.000');
            // Line 2 fails the stock check after the number, line 1's deduction and the invoice row exist.
            $payload['items'][] = ['item_id' => $scarce->id, 'quantity' => '0.500', 'unit_price' => '150.000', 'discount_amount' => '0.000'];

            $attempts = 0;
            Event::listen(TransactionRolledBack::class, function () use (&$attempts): void {
                $attempts++;
            });

            try {
                app(InvoiceService::class)->confirmInvoice($payload);
                $this->fail('InsufficientStockException expected.');
            } catch (InsufficientStockException) {
                // expected: a domain error, never retried
            }

            $after = [
                'rolled_back_attempts' => $attempts,
                'invoices' => Invoice::withTrashed()->count(),
                'payments' => Payment::withTrashed()->count(),
                'movements' => StockMovement::query()->count(),
                'stock' => [(string) Item::query()->whereKey($plenty->id)->value('current_stock'), $this->storeQuantity($storeId, (int) $plenty->id), $this->storeQuantity($storeId, (int) $scarce->id)],
                'counters' => DB::table('document_sequences')->pluck('last_value')->map(fn ($v): int => (int) $v)->all(),
            ];

            // The next sale gets the number the failed one had reserved: no gap.
            $next = app(InvoiceService::class)->confirmInvoice($this->checkoutPayload((int) $customer->id, (int) $plenty->id, $storeId, '1.000'));
            $after['next_number_suffix'] = substr($next->invoice_number, -4);

            return $after;
        });

        $this->assertSame([
            'rolled_back_attempts' => 1,
            'invoices' => 0,
            'payments' => 0,
            'movements' => 0,
            'stock' => ['10.000', '10.000', '0.250'],
            // Prepared before the transaction (BUG-04), never advanced by the failed sale.
            'counters' => [0, 0],
            'next_number_suffix' => '0001',
        ], $state);
    }

    public function test_opposing_transfers_and_sales_of_the_same_items_never_fail_and_balance_exactly(): void
    {
        $tenant = $this->createTenant();
        $storeA = (int) $this->tenantStore($tenant)->getKey();

        [$storeB, $itemIds, $customerIds] = $this->inTenant($tenant, function () use ($storeA): array {
            $storeB = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'BRB', 'type' => 'retail_shop', 'is_active' => true, 'is_main' => false])->getKey();
            $itemIds = [
                (int) $this->makeItem('QA-RACE-X', [$storeA => '100.000', $storeB => '100.000'])->getKey(),
                (int) $this->makeItem('QA-RACE-Y', [$storeA => '100.000', $storeB => '100.000'])->getKey(),
            ];
            $customerIds = [];
            for ($i = 1; $i <= self::RACE_WORKERS; $i++) {
                $customerIds[] = (int) Customer::query()->create(['name' => 'عميل سباق '.$i, 'current_balance' => '0.000', 'is_active' => true])->getKey();
            }

            return [$storeB, $itemIds, $customerIds];
        });

        [$x, $y] = $itemIds;
        $results = [];

        for ($round = 1; $round <= self::RACE_ROUNDS; $round++) {
            $payloads = [];
            for ($i = 0; $i < self::RACE_WORKERS; $i++) {
                $payloads[] = match ($i % 3) {
                    // A -> B with lines [X, Y]
                    0 => ['__scenario' => 'transfer', 'from_store_id' => $storeA, 'to_store_id' => $storeB, 'items' => [['item_id' => $x, 'quantity' => '1.250'], ['item_id' => $y, 'quantity' => '0.500']]],
                    // B -> A with the lines in the OPPOSITE order [Y, X]
                    1 => ['__scenario' => 'transfer', 'from_store_id' => $storeB, 'to_store_id' => $storeA, 'items' => [['item_id' => $y, 'quantity' => '0.500'], ['item_id' => $x, 'quantity' => '1.250']]],
                    // a sale in branch A of [Y, X]
                    default => [
                        '__scenario' => 'checkout',
                        'client_uuid' => (string) Str::uuid(),
                        'customer_id' => $customerIds[$i],
                        'store_id' => $storeA,
                        'payment_type' => 'cash',
                        'discount_type' => 'fixed',
                        'discount_value' => '0.000',
                        'items' => [
                            ['item_id' => $y, 'quantity' => '0.250', 'unit_price' => '150.000', 'discount_amount' => '0.000'],
                            ['item_id' => $x, 'quantity' => '1.000', 'unit_price' => '150.000', 'discount_amount' => '0.000'],
                        ],
                    ],
                };
            }

            array_push($results, ...$this->runWorkers('checkout', $tenant, $payloads));
        }

        $failures = array_values(array_filter($results, fn (array $r): bool => $r['ok'] !== true));
        $absorbed = array_sum(array_map(fn (array $r): int => (int) ($r['rollbacks'] ?? 0), $results));
        $codes = array_count_values(array_map(fn (array $r): string => (string) ($r['driver_code'] ?? $r['error_class']), $failures));
        // Measurement line (BUG-07 report): failures by driver error code and retries absorbed.
        fwrite(STDERR, sprintf("\n[BUG-07 race] engine=%s operations=%d failures=%d by_code=%s absorbed_rollbacks=%d\n", $this->serverVersion(), count($results), count($failures), json_encode($codes), $absorbed));

        $this->assertSame([], $failures, 'Every operation must succeed (retried internally if needed): '.json_encode($failures, JSON_UNESCAPED_UNICODE));

        $sales = self::RACE_ROUNDS * intdiv(self::RACE_WORKERS, 3);
        $state = $this->inTenant($tenant, fn (): array => [
            'x' => [$this->storeQuantity($storeA, $x), $this->storeQuantity($storeB, $x), (string) Item::query()->whereKey($x)->value('current_stock'), (string) StoreStock::withTrashed()->where('item_id', $x)->sum('quantity')],
            'y' => [$this->storeQuantity($storeA, $y), $this->storeQuantity($storeB, $y), (string) Item::query()->whereKey($y)->value('current_stock'), (string) StoreStock::withTrashed()->where('item_id', $y)->sum('quantity')],
            'negative_rows' => StoreStock::withTrashed()->where('quantity', '<', 0)->count(),
            'invoice_numbers' => Invoice::query()->pluck('invoice_number')->all(),
            'transfer_numbers' => StockTransfer::query()->pluck('transfer_number')->all(),
            'payment_numbers' => Payment::query()->pluck('payment_number')->all(),
            'transfer_movements' => StockMovement::query()->whereIn('movement_type', ['transfer_out', 'transfer_in'])->count(),
        ]);

        // Transfers cancel out (same count each way); each sale takes 1.000 X and 0.250 Y from branch A.
        $soldX = bcmul((string) $sales, '1.000', 3);
        $soldY = bcmul((string) $sales, '0.250', 3);
        $this->assertSame([bcsub('100.000', $soldX, 3), '100.000', bcsub('200.000', $soldX, 3), bcsub('200.000', $soldX, 3)], $state['x']);
        $this->assertSame([bcsub('100.000', $soldY, 3), '100.000', bcsub('200.000', $soldY, 3), bcsub('200.000', $soldY, 3)], $state['y']);
        $this->assertSame(0, $state['negative_rows']);

        $this->assertCount($sales, $state['invoice_numbers']);
        $this->assertCount($sales, array_unique($state['invoice_numbers']), 'Duplicate invoice number.');
        $transfers = self::RACE_ROUNDS * self::RACE_WORKERS - $sales;
        $this->assertCount($transfers, $state['transfer_numbers']);
        $this->assertCount($transfers, array_unique($state['transfer_numbers']), 'Duplicate transfer number.');
        $this->assertCount($sales, array_unique($state['payment_numbers']), 'Duplicate or missing payment number.');
        $this->assertSame($transfers * 2 * 2, $state['transfer_movements'], 'Exactly one out + one in movement per transfer line.');

        // Gapless: the retried attempts' numbers rolled back with them.
        $this->assertSame(range(1, $sales), $this->sortedSuffixes($state['invoice_numbers']));
        $this->assertSame(range(1, $transfers), $this->sortedSuffixes($state['transfer_numbers']));
    }

    public function test_run_applies_its_isolation_level_and_does_not_leak_it_to_the_next_transaction(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn (): bool => DB::table('document_sequences')->insert(['scope' => 'QA-ISO', 'last_value' => 1, 'created_at' => now(), 'updated_at' => now()]));
        $side = $this->tenantSideConnection($tenant, 'qa_iso_side');

        try {
            $observed = $this->inTenant($tenant, function () use ($side): array {
                $read = fn (): int => (int) DB::table('document_sequences')->where('scope', 'QA-ISO')->value('last_value');
                $sessionLevel = fn (): string => (string) DB::selectOne('select @@session.transaction_isolation as level')->level;

                // Inside run(): READ COMMITTED, so a plain read sees a row committed meanwhile.
                $inRun = TransactionRetry::run(function () use ($read, $side): array {
                    $first = $read();
                    $side->table('document_sequences')->where('scope', 'QA-ISO')->update(['last_value' => 2]);

                    return [$first, $read()];
                });

                // The very next plain transaction on the same connection: back to the server's
                // REPEATABLE READ, so the second read still sees the first snapshot.
                $plain = DB::transaction(function () use ($read, $side): array {
                    $first = $read();
                    $side->table('document_sequences')->where('scope', 'QA-ISO')->update(['last_value' => 3]);

                    return [$first, $read()];
                });

                return ['run' => $inRun, 'plain' => $plain, 'session_level_after' => $sessionLevel()];
            });
        } finally {
            DB::purge('qa_iso_side');
        }

        $this->assertSame([
            'run' => [1, 2],
            'plain' => [2, 2],
            'session_level_after' => 'REPEATABLE-READ',
        ], $observed, 'Engine '.$this->serverVersion());
    }

    #[DataProvider('settings')]
    public function test_the_first_document_of_each_follow_up_flow_succeeds_on_the_first_attempt(bool $withoutSafetyNets): void
    {
        if ($withoutSafetyNets) {
            config(['database.transaction_retry.isolation' => '', 'database.transaction_retry.attempts' => 1]);
        }

        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->getKey();
        $adminId = (int) $this->tenantAdmin($tenant)->getKey();

        $result = $this->inTenant($tenant, function () use ($storeId, $adminId): array {
            $secondStoreId = (int) Store::query()->create(['name' => 'فرع تاني', 'code' => 'BR2', 'type' => 'retail_shop', 'is_active' => true, 'is_main' => false])->getKey();
            $item = $this->makeItem('QA-FOLLOW-01', [$storeId => '50.000', $secondStoreId => '10.000']);
            $customer = Customer::query()->create(['name' => 'عميل متابعة', 'current_balance' => '0.000', 'is_active' => true]);
            $supplier = Supplier::query()->create(['name' => 'مورد متابعة', 'current_balance' => '0.000', 'is_active' => true]);

            // Documents of an earlier day: their numbers exist, then every counter row is wiped so
            // each flow below is the first of its type "today".
            $invoice = app(InvoiceService::class)->confirmInvoice($this->checkoutPayload((int) $customer->id, (int) $item->id, $storeId, '2.000'));
            app(PaymentService::class)->recordCustomerPayment(['customer_id' => $customer->id, 'amount' => '500.000', 'payment_method' => 'cash', 'store_id' => $storeId]);
            $purchase = app(PurchaseService::class)->createPurchase([
                'supplier_id' => $supplier->id, 'store_id' => $storeId, 'paid_amount' => '200.000', 'payment_method' => 'cash',
                'items' => [['item_id' => $item->id, 'quantity' => '1.000', 'cost_price' => '200.000']],
            ]);
            $transfer = app(StockTransferService::class)->createTransfer(['from_store_id' => $storeId, 'to_store_id' => $secondStoreId, 'items' => [['item_id' => $item->id, 'quantity' => '1.000']]]);
            $return = app(ReturnService::class)->createReturn([
                'return_type' => 'sales_return', 'customer_id' => $customer->id, 'store_id' => $storeId, 'refund_method' => 'balance',
                'items' => [['item_id' => $item->id, 'quantity' => '0.500', 'unit_price' => '150.000']],
            ]);
            // So that restorePurchase() must issue a brand-new PAY-PUR number.
            Payment::withTrashed()->where('purchase_id', $purchase->id)->forceDelete();
            DB::table('document_sequences')->delete();

            $rollbacks = 0;
            Event::listen(TransactionRolledBack::class, function () use (&$rollbacks): void {
                $rollbacks++;
            });

            app(InvoiceService::class)->cancelInvoice($invoice, 'إلغاء أول اليوم', $adminId);
            $adjusted = app(AdjustItemStockAction::class)->execute(new AdjustStockDTO((int) $item->id, $storeId, '1.000', 'stock_adjustment_in'), $adminId);
            $counted = app(StockService::class)->adjustStock(Item::query()->findOrFail($item->id), '10.500', 'جرد أول اليوم', $secondStoreId);
            $treasury = app(TreasuryService::class)->transfer(['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '50.000', 'store_id' => $storeId]);
            $shift = app(ShiftService::class)->openShift('0.000', null, $storeId);
            $expected = app(ShiftService::class)->calculateShiftTotals($shift)['expected_cash_balance'];
            $closed = app(ShiftService::class)->closeShift($shift, $expected);
            app(StockTransferService::class)->cancelTransfer($transfer, 'إلغاء التحويل');
            app(DeleteReturnAction::class)->execute((int) $return->id);
            app(PurchaseService::class)->cancelPurchase($purchase, 'إلغاء');
            app(PurchaseService::class)->restorePurchase(Purchase::query()->findOrFail($purchase->id));
            app(PurchaseService::class)->deletePurchase(Purchase::query()->findOrFail($purchase->id));
            app(InvoiceService::class)->deleteInvoice(Invoice::query()->findOrFail($invoice->id));

            return [
                'rollbacks' => $rollbacks,
                'numbers' => [
                    'refund' => (string) Payment::withTrashed()->where('invoice_id', $invoice->id)->where('amount', '<', 0)->value('payment_number'),
                    'adjust_action' => (string) $adjusted->document_number,
                    'adjust_count' => (string) $counted->document_number,
                    'treasury' => (string) $treasury->transfer_number,
                    'shift' => (string) $closed->shift_number,
                    'restored_payment' => (string) Payment::withTrashed()->where('purchase_id', $purchase->id)->value('payment_number'),
                ],
                'shift_status' => (string) $closed->status,
                'purchase_deleted' => Purchase::query()->whereKey($purchase->id)->doesntExist(),
            ];
        });

        $this->assertSame(0, $result['rollbacks'], 'A first-of-day follow-up document needed a second attempt (BUG-04): '.json_encode($result, JSON_UNESCAPED_UNICODE));
        $this->assertSame(
            ['refund' => '000001', 'adjust_action' => '0001', 'adjust_count' => '0001', 'treasury' => '0001', 'shift' => '001', 'restored_payment' => '000001'],
            array_map(fn (string $n): string => substr($n, (int) strrpos($n, '-') + 1), $result['numbers']),
            json_encode($result['numbers'], JSON_UNESCAPED_UNICODE),
        );
        $this->assertSame('closed', $result['shift_status']);
        $this->assertTrue($result['purchase_deleted']);
    }

    public function test_parallel_sales_and_payments_of_one_customer_keep_its_balance_exact(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->getKey();

        [$itemId, $customerId] = $this->inTenant($tenant, fn (): array => [
            (int) $this->makeItem('QA-BAL-01', [$storeId => '100.000'])->getKey(),
            (int) Customer::query()->create(['name' => 'عميل رصيد متزامن', 'current_balance' => '0.000', 'is_active' => true])->getKey(),
        ]);

        $payloads = [];
        for ($i = 0; $i < 6; $i++) {
            // A credit sale of 1.250 x 150 = 187.500 and a 50.250 receipt, all for the same customer.
            $payloads[] = ['__scenario' => 'checkout'] + array_replace($this->checkoutPayload($customerId, $itemId, $storeId, '1.250'), ['payment_type' => 'credit']);
            $payloads[] = ['__scenario' => 'payment', 'customer_id' => $customerId, 'amount' => '50.250', 'payment_method' => 'cash', 'store_id' => $storeId];
        }

        $results = $this->runWorkers('checkout', $tenant, $payloads);

        foreach ($results as $result) {
            $this->assertTrue($result['ok'], 'Sale/receipt for one customer failed: '.json_encode($result, JSON_UNESCAPED_UNICODE));
        }

        $state = $this->inTenant($tenant, fn (): array => [
            'balance' => (string) Customer::query()->whereKey($customerId)->value('current_balance'),
            'invoices' => Invoice::query()->where('customer_id', $customerId)->count(),
            'payments' => Payment::query()->where('customer_id', $customerId)->count(),
            'stock' => (string) Item::query()->whereKey($itemId)->value('current_stock'),
        ]);

        // 6 x 187.500 - 6 x 50.250 = 1125.000 - 301.500
        $this->assertSame(['balance' => '823.500', 'invoices' => 6, 'payments' => 6, 'stock' => '92.500'], $state);
    }

    public function test_parallel_returns_of_one_invoice_line_never_over_return(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->getKey();

        [$itemId, $customerId, $invoiceId, $lineId] = $this->inTenant($tenant, function () use ($storeId): array {
            $item = $this->makeItem('QA-RET-01', [$storeId => '10.000']);
            $customer = Customer::query()->create(['name' => 'عميل مرتجع متزامن', 'current_balance' => '0.000', 'is_active' => true]);
            $invoice = app(InvoiceService::class)->confirmInvoice($this->checkoutPayload((int) $customer->id, (int) $item->id, $storeId, '2.000'));

            return [(int) $item->id, (int) $customer->id, (int) $invoice->id, (int) $invoice->items()->value('id')];
        });

        $payload = [
            '__scenario' => 'return',
            'return_type' => 'sales_return',
            'invoice_id' => $invoiceId,
            'customer_id' => $customerId,
            'store_id' => $storeId,
            'refund_method' => 'balance',
            'items' => [['invoice_item_id' => $lineId, 'item_id' => $itemId, 'quantity' => '1.000']],
        ];

        // Four returns of 1.000 race on a line sold as 2.000.
        $results = $this->runWorkers('return', $tenant, array_fill(0, 4, $payload));

        $succeeded = array_values(array_filter($results, fn (array $r): bool => $r['ok'] === true));
        $failed = array_values(array_filter($results, fn (array $r): bool => $r['ok'] !== true));

        $this->assertCount(2, $succeeded, json_encode($results, JSON_UNESCAPED_UNICODE));
        foreach ($failed as $failure) {
            // Refused by the returnable-quantity rule, not by a lock error.
            $this->assertSame(ValidationException::class, $failure['error_class'], (string) json_encode($failure, JSON_UNESCAPED_UNICODE));
        }

        $state = $this->inTenant($tenant, fn (): array => [
            'returned' => (string) DB::table('return_items')->where('invoice_item_id', $lineId)->whereNull('deleted_at')->sum('quantity'),
            'returns' => ReturnDocument::query()->where('invoice_id', $invoiceId)->count(),
            'stock' => (string) Item::query()->whereKey($itemId)->value('current_stock'),
            'store_stock' => $this->storeQuantity($storeId, $itemId),
        ]);

        $this->assertSame(['returned' => '2.000', 'returns' => 2, 'stock' => '10.000', 'store_stock' => '10.000'], $state);
    }

    public function test_real_lock_conflicts_are_retried_and_the_work_is_applied_once(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn (): bool => DB::table('document_sequences')->insert([
            ['scope' => 'QA-DL-A', 'last_value' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['scope' => 'QA-DL-B', 'last_value' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['scope' => 'QA-SNAP', 'last_value' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]));

        // 1. A real deadlock: A then B against B then A, both holding the first row while asking
        //    for the second. InnoDB kills one (1213); the helper must restart it.
        $deadlock = $this->runWorkers('lockorder', $tenant, [
            ['first' => 'QA-DL-A', 'second' => 'QA-DL-B', 'pause_ms' => 400],
            ['first' => 'QA-DL-B', 'second' => 'QA-DL-A', 'pause_ms' => 400],
        ]);

        // 2. MariaDB's snapshot check: at the server's REPEATABLE READ a plain read opens a read
        //    view, another worker commits the row, then the locking read fails with 1020.
        $snapshot = $this->runWorkers('snapshot', $tenant, [
            ['first' => 'QA-SNAP', 'pause_ms' => 600, 'isolation' => ''],
            ['first' => 'QA-SNAP', 'pause_ms' => 0, 'isolation' => ''],
        ]);

        $absorbed = fn (array $results): int => array_sum(array_map(fn (array $r): int => (int) ($r['rollbacks'] ?? 0), $results));
        $isMaria = str_contains(strtolower($this->serverVersion()), 'mariadb');
        fwrite(STDERR, sprintf("\n[BUG-07 real retry] engine=%s deadlock_rollbacks=%d snapshot_rollbacks=%d\n", $this->serverVersion(), $absorbed($deadlock), $absorbed($snapshot)));

        foreach ([...$deadlock, ...$snapshot] as $result) {
            $this->assertTrue($result['ok'], 'The helper must absorb the conflict: '.json_encode($result, JSON_UNESCAPED_UNICODE));
        }
        $this->assertGreaterThanOrEqual(1, $absorbed($deadlock), 'The deadlock must have happened and been retried.');
        if ($isMaria) {
            $this->assertGreaterThanOrEqual(1, $absorbed($snapshot), 'MariaDB must have raised 1020 and the helper retried it.');
        }

        // Every unit of work applied exactly once, retried or not.
        $this->assertSame(
            ['QA-DL-A' => 2, 'QA-DL-B' => 2, 'QA-SNAP' => 2],
            $this->inTenant($tenant, fn (): array => DB::table('document_sequences')->whereIn('scope', ['QA-DL-A', 'QA-DL-B', 'QA-SNAP'])->orderBy('scope')->pluck('last_value', 'scope')->map(fn ($v): int => (int) $v)->all()),
        );
    }

    /**
     * @param  list<string>  $numbers
     * @return list<int>
     */
    private function sortedSuffixes(array $numbers): array
    {
        $suffixes = array_map(fn (string $n): int => (int) substr($n, (int) strrpos($n, '-') + 1), $numbers);
        sort($suffixes);

        return $suffixes;
    }

    /** @return array<string, mixed> */
    private function checkoutPayload(int $customerId, int $itemId, int $storeId, string $quantity): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'customer_id' => $customerId,
            'store_id' => $storeId,
            'payment_type' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '0.000',
            'items' => [['item_id' => $itemId, 'quantity' => $quantity, 'unit_price' => '150.000', 'discount_amount' => '0.000']],
        ];
    }

    /**
     * An item whose total stock equals the sum of its store rows (unlike stockInAllStores()).
     *
     * @param  array<int, string>  $perStore  store id => quantity
     */
    private function makeItem(string $code, array $perStore): Item
    {
        $total = '0.000';
        foreach ($perStore as $quantity) {
            $total = bcadd($total, $quantity, 3);
        }

        $item = Item::query()->create([
            'code' => $code,
            'name' => 'صنف '.$code,
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '100.000',
            'selling_price' => '150.000',
            'current_stock' => $total,
            'min_stock_level' => '0.000',
            'is_active' => true,
        ]);

        foreach ($perStore as $storeId => $quantity) {
            StoreStock::query()->updateOrCreate(['store_id' => $storeId, 'item_id' => $item->id], ['quantity' => $quantity]);
        }

        return $item;
    }

    private function storeQuantity(int $storeId, int $itemId): string
    {
        return (string) StoreStock::withTrashed()->where('store_id', $storeId)->where('item_id', $itemId)->value('quantity');
    }

    private function serverVersion(): string
    {
        return (string) DB::connection($this->centralConnectionName())->selectOne('select version() as v')->v;
    }
}
