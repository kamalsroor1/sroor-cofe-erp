<?php

declare(strict_types=1);

namespace App\Support\Perf;

use App\DTOs\Perf\PerfSeedOptions;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * PERF-04: writes a deterministic, report-sized dataset into the CURRENT tenant with bulk
 * inserts. Used only through App\Actions\Perf\SeedPerfDatasetAction (which owns the transaction,
 * the guards and the balance recalculation); one instance per run.
 *
 * The rows are the ones the application itself writes for each flow, so the app's invariants
 * hold on the result:
 *  - stock: one opening movement per (store, item), then purchase_in / sales_out /
 *    sales_return_in movements in time order with item-level stock_before/stock_after; every
 *    store_stocks row equals its movements and SUM(store_stocks) == items.current_stock;
 *  - money: one PAY-INV voucher per paid invoice (amount = paid_amount), PAY-CUST collections
 *    that settle credit invoices, negative PAY-RET vouchers for cash refunds, PAY-PUR / PAY-SUPP
 *    supplier vouchers; party balances are recomputed by the app's balance services afterwards;
 *  - numbers: the app's formats (INV-{branch}-{Ymd}-0001, PAY-INV-{Ymd}-000001, RET-SALES-…,
 *    PUR-…, EXP-{ymd}-…), continuing after any number already issued, and the document_sequences
 *    counters are left at the last number so the next real document never collides.
 *
 * Determinism: no random source. Every choice is an integer hash of the document index
 * (mix()), so two machines produce the same rows for the same options (ids depend only on the
 * ids already present in the tenant, and the dates on the day the command runs).
 *
 * Simplifications (documented in docs/07-operations/perf-testing.md): no discounts, no
 * cancelled invoices, no shifts, purchases at the item's cost (the weighted average cost stays
 * constant), all money in cash or InstaPay.
 */
final class PerfDatasetBuilder
{
    private const ROWS_PER_STATEMENT = 400;

    private const FLUSH_EVERY = 5_000;

    /** Insert order (foreign keys). */
    private const TABLES = [
        'stores', 'items', 'customers', 'suppliers', 'store_stocks', 'purchases', 'purchase_items',
        'invoices', 'invoice_items', 'returns', 'return_items', 'payments', 'expenses', 'stock_movements',
    ];

    /** Document tables => number column, for the counters already in use. */
    private const NUMBERED = [
        'invoices' => 'invoice_number',
        'payments' => 'payment_number',
        'returns' => 'return_number',
        'purchases' => 'purchase_number',
        'expenses' => 'expense_number',
    ];

    /** @var array<string, list<array<string, mixed>>> */
    private array $buffers = [];

    private int $buffered = 0;

    /** @var array<string, int> next explicit id per table */
    private array $nextId = [];

    /** @var array<string, int> number prefix => last sequence issued */
    private array $counters = [];

    /** @var array<string, int> scopes this run allocated from */
    private array $touchedScopes = [];

    /** @var list<array{id: int, code: string}> */
    private array $stores = [];

    /** @var list<array{id: int, code: string, cost: string, price: string, weighted: bool}> */
    private array $items = [];

    /** @var list<int> */
    private array $customerIds = [];

    /** @var list<int> */
    private array $supplierIds = [];

    /** @var array<int, string> item index => running item-level stock */
    private array $itemStock = [];

    /** @var array<int, array<int, string>> store index => item index => running store stock */
    private array $storeStock = [];

    /** @var array<int, array<int, string>> store index => item index => final store stock */
    private array $finalStock = [];

    /** @var array<int, array<int, string>> store index => item index => opening store stock */
    private array $openingStock = [];

    /** @var array<string, int> */
    private array $counts = [
        'invoices' => 0, 'invoice_lines' => 0, 'payments' => 0, 'returns' => 0, 'purchases' => 0,
        'purchase_lines' => 0, 'expenses' => 0, 'stock_movements' => 0, 'items' => 0,
        'customers' => 0, 'suppliers' => 0, 'stores_created' => 0,
    ];

    private DateTimeZone $storageZone;

    private int $latestTimestamp;

    /** @var list<string> business dates, oldest first */
    private array $dates = [];

    /** @var list<int> unix timestamp of the start of each business date */
    private array $dayStarts = [];

    private string $stamp;

    public function __construct(
        private readonly PerfSeedOptions $options,
        private readonly TenantClock $clock,
        private readonly int $userId,
        private readonly int $walkInCustomerId,
    ) {
        $this->storageZone = new DateTimeZone($clock->storageTimezone());
        // Nothing is stamped in the future or in the last minute: a shift opened after the
        // seed must not see seeded vouchers inside its [opened_at, now] window.
        $this->latestTimestamp = time() - 60;
        $this->stamp = $this->format($this->latestTimestamp);

        $today = $clock->businessDate();
        for ($d = $options->days - 1; $d >= 0; $d--) {
            $date = (new DateTimeImmutable($today))->modify("-{$d} day")->format('Y-m-d');
            $this->dates[] = $date;
            $this->dayStarts[] = $clock->startOfDay($date)->getTimestamp();
        }
    }

    /**
     * @return array<string, int> counts of what was written
     */
    public function build(): array
    {
        foreach (self::TABLES as $table) {
            $this->nextId[$table] = (int) DB::table($table)->max('id') + 1;
        }
        $this->loadCounters();

        $this->prepareStores();
        $this->prepareItems();
        $this->prepareParties();
        $this->planStock();

        $this->openingMovements();

        $invoice = 0;
        $purchase = 0;
        $purchaseCount = $this->purchaseCount();
        for ($day = 0; $day < $this->options->days; $day++) {
            for ($first = $purchase; $purchase < $purchaseCount && $this->dayOf($purchase, $purchaseCount) === $day; $purchase++) {
                $this->purchase($purchase, $day, $purchase - $first);
                $this->flushIfFull();
            }
            $firstOfDay = $invoice;
            $perDay = $this->firstOfDay($day + 1, $this->options->invoices) - $firstOfDay;
            for (; $invoice < $this->options->invoices && $this->dayOf($invoice, $this->options->invoices) === $day; $invoice++) {
                $this->invoice($invoice, $day, $invoice - $firstOfDay, $perDay);
                $this->flushIfFull();
            }
            $this->expenses($day);
            $this->flushIfFull();
        }

        $this->flush();
        $this->assertStockMatchesThePlan();
        $this->saveCounters();

        return $this->counts;
    }

    /** @return list<int> ids of the customers whose balance the run changed (walk-in included) */
    public function customerIds(): array
    {
        return [...$this->customerIds, $this->walkInCustomerId];
    }

    /** @return list<int> */
    public function supplierIds(): array
    {
        return $this->supplierIds;
    }

    // ------------------------------------------------------------------
    // Master data
    // ------------------------------------------------------------------

    private function prepareStores(): void
    {
        $existing = DB::table('stores')->whereNull('deleted_at')->where('is_active', true)
            ->orderByDesc('is_main')->orderBy('id')->limit($this->options->stores)->get(['id', 'code']);

        foreach ($existing as $store) {
            $this->stores[] = ['id' => (int) $store->id, 'code' => $this->invoiceStoreCode((int) $store->id, (string) $store->code)];
        }

        for ($n = count($this->stores) + 1; count($this->stores) < $this->options->stores; $n++) {
            $code = 'PERF-S'.$n;
            if (DB::table('stores')->where('code', $code)->exists()) {
                continue;
            }
            $id = $this->id('stores');
            $this->add('stores', [
                'id' => $id, 'name' => 'فرع أداء '.$n, 'code' => $code, 'type' => 'retail_shop',
                'is_active' => true, 'is_main' => false, 'created_at' => $this->stamp, 'updated_at' => $this->stamp,
            ]);
            $this->stores[] = ['id' => $id, 'code' => $this->invoiceStoreCode($id, $code)];
            $this->counts['stores_created']++;
        }
    }

    private function prepareItems(): void
    {
        if (DB::table('items')->where('code', 'like', 'PERF-I%')->exists()) {
            throw new RuntimeException((string) __('console.perf_seed.items_exist'));
        }

        for ($k = 0; $k < $this->options->items; $k++) {
            $cost = Decimal::normalize((10 + $k % 200).'.250');
            $this->items[] = [
                'id' => $this->id('items'),
                'code' => sprintf('PERF-I%06d', $k),
                'cost' => $cost,
                'price' => Decimal::mul($cost, '1.300'),
                'weighted' => $k % 3 === 0,
            ];
            $this->itemStock[$k] = '0.000';
        }
        $this->counts['items'] = $this->options->items;
    }

    private function prepareParties(): void
    {
        for ($c = 0; $c < $this->options->customers; $c++) {
            $id = $this->id('customers');
            $this->customerIds[] = $id;
            $this->add('customers', [
                'id' => $id, 'name' => 'عميل أداء '.$c, 'phone' => null, 'price_tier' => 'retail',
                'current_balance' => '0.000', 'is_active' => true, 'created_at' => $this->stamp, 'updated_at' => $this->stamp,
            ]);
        }
        for ($s = 0; $s < $this->options->suppliers; $s++) {
            $id = $this->id('suppliers');
            $this->supplierIds[] = $id;
            $this->add('suppliers', [
                'id' => $id, 'name' => 'مورد أداء '.$s, 'phone' => null, 'current_balance' => '0.000',
                'is_active' => true, 'created_at' => $this->stamp, 'updated_at' => $this->stamp,
            ]);
        }
        $this->counts['customers'] = $this->options->customers;
        $this->counts['suppliers'] = $this->options->suppliers;
    }

    /**
     * Pre-pass: the final stock of every (store, item) and the opening balance that keeps every
     * running balance >= 0 whatever the order (opening = everything that will be sold + 50).
     * Items and store_stocks are inserted with these final figures; the movements written by
     * the main pass must land on them exactly (assertStockMatchesThePlan()).
     */
    private function planStock(): void
    {
        $sold = [];
        $added = [];
        foreach (array_keys($this->stores) as $s) {
            foreach (array_keys($this->items) as $k) {
                $sold[$s][$k] = '0.000';
                $added[$s][$k] = '0.000';
            }
        }

        for ($i = 0; $i < $this->options->invoices; $i++) {
            $plan = $this->invoicePlan($i);
            foreach ($plan['lines'] as [$k, $qty]) {
                $sold[$plan['store']][$k] = bcadd($sold[$plan['store']][$k], $qty, 3);
            }
            if ($plan['return'] !== null) {
                [$k, $qty] = $plan['return'];
                $added[$plan['store']][$k] = bcadd($added[$plan['store']][$k], $qty, 3);
            }
        }
        for ($p = 0, $n = $this->purchaseCount(); $p < $n; $p++) {
            $plan = $this->purchasePlan($p);
            foreach ($plan['lines'] as [$k, $qty]) {
                $added[$plan['store']][$k] = bcadd($added[$plan['store']][$k], $qty, 3);
            }
        }

        $itemTotals = [];
        foreach ($this->items as $k => $item) {
            $itemTotals[$k] = '0.000';
        }

        foreach ($this->stores as $s => $store) {
            foreach ($this->items as $k => $item) {
                $opening = bcadd($sold[$s][$k], '50.000', 3);
                $final = bcadd(bcsub($opening, $sold[$s][$k], 3), $added[$s][$k], 3);
                $this->storeStock[$s][$k] = '0.000';
                $this->finalStock[$s][$k] = $final;
                $this->openingStock[$s][$k] = $opening;
                $itemTotals[$k] = bcadd($itemTotals[$k], $final, 3);
            }
        }

        foreach ($this->items as $k => $item) {
            $this->add('items', [
                'id' => $item['id'], 'code' => $item['code'], 'name' => 'صنف أداء '.$k, 'category' => 'perf',
                'unit' => $item['weighted'] ? 'كجم' : 'قطعة', 'is_weighted' => $item['weighted'],
                'current_stock' => $itemTotals[$k], 'cost_price' => $item['cost'], 'weighted_avg_cost' => $item['cost'],
                'selling_price' => $item['price'], 'min_selling_price' => '0.000', 'min_stock_level' => '5.000',
                'is_active' => true, 'created_at' => $this->stamp, 'updated_at' => $this->stamp,
            ]);
        }
        foreach ($this->stores as $s => $store) {
            foreach ($this->items as $k => $item) {
                $this->add('store_stocks', [
                    'store_id' => $store['id'], 'item_id' => $item['id'], 'quantity' => $this->finalStock[$s][$k],
                    'min_stock' => '5.000', 'created_at' => $this->stamp, 'updated_at' => $this->stamp,
                ]);
            }
        }
    }

    private function openingMovements(): void
    {
        $at = $this->format(min($this->dayStarts[0] + 6 * 3600, $this->latestTimestamp));
        foreach ($this->stores as $s => $store) {
            foreach ($this->items as $k => $item) {
                $this->movement($s, $k, 'opening_balance_in', $this->openingStock[$s][$k], $item['cost'], Item::class, $item['id'], 'OPEN-'.$item['code'], $at);
                $this->flushIfFull();
            }
        }
    }

    // ------------------------------------------------------------------
    // Documents
    // ------------------------------------------------------------------

    private function invoice(int $i, int $day, int $rank, int $perDay): void
    {
        $plan = $this->invoicePlan($i);
        $store = $this->stores[$plan['store']];
        $date = $this->dates[$day];
        $ymd = str_replace('-', '', $date);
        $ts = $this->clamp($this->dayStarts[$day] + 8 * 3600 + intdiv($rank * 12 * 3600, max(1, $perDay)), $day);
        $at = $this->format($ts);

        $invoiceId = $this->id('invoices');
        $number = $this->nextNumber("INV-{$store['code']}-{$ymd}", 4);
        $net = '0.000';
        $cost = '0.000';
        $firstLine = null;

        foreach ($plan['lines'] as $j => [$k, $qty]) {
            $item = $this->items[$k];
            $total = Decimal::mul($qty, $item['price']);
            $net = bcadd($net, $total, 3);
            $cost = bcadd($cost, Decimal::mul($qty, $item['cost']), 3);
            $lineId = $this->id('invoice_items');
            $firstLine ??= $lineId;
            $this->add('invoice_items', [
                'id' => $lineId, 'invoice_id' => $invoiceId, 'item_id' => $item['id'], 'entry_sequence' => $j + 1,
                'quantity' => $qty, 'cost_price' => $item['cost'], 'unit_price' => $item['price'], 'list_unit_price' => $item['price'],
                'discount_amount' => '0.000', 'total_price' => $total, 'created_at' => $at, 'updated_at' => $at,
            ]);
            $this->movement($plan['store'], $k, 'sales_out', $qty, $item['cost'], Invoice::class, $invoiceId, $number, $at);
            $this->counts['invoice_lines']++;
        }

        $credit = $plan['kind'] === 'credit';
        $collected = $credit && $i % 3 === 0;
        $customerId = $plan['walk_in'] ? $this->walkInCustomerId : $this->customerIds[$plan['customer']];
        $paid = $credit && ! $collected ? '0.000' : $net;

        $this->add('invoices', [
            'id' => $invoiceId, 'invoice_number' => $number, 'customer_id' => $customerId, 'user_id' => $this->userId,
            'store_id' => $store['id'], 'invoice_date' => $date, 'payment_type' => $credit ? 'credit' : 'cash',
            'payment_method' => $plan['kind'] === 'instapay' ? 'instapay' : 'cash', 'status' => 'confirmed',
            'payment_status' => bccomp($paid, $net, 3) === 0 ? 'paid' : 'unpaid', 'subtotal' => $net, 'discount_type' => 'fixed',
            'discount_value' => '0.000', 'discount_amount' => '0.000', 'net_total' => $net, 'paid_amount' => $paid,
            'remaining_amount' => bcsub($net, $paid, 3), 'total_cost' => $cost, 'shipping_cost' => '0.000', 'change_amount' => '0.000',
            'created_at' => $at, 'updated_at' => $at,
        ]);
        $this->counts['invoices']++;

        if (! $credit) {
            $this->payment("PAY-INV-{$ymd}", ['customer_id' => $customerId, 'invoice_id' => $invoiceId], $store['id'], $net, $date, $plan['kind'] === 'instapay' ? 'instapay' : 'cash', $at);
        } elseif ($collected) {
            $this->payment("PAY-CUST-{$ymd}", ['customer_id' => $customerId, 'invoice_id' => $invoiceId], $store['id'], $net, $date, 'cash', $this->format($this->clamp($ts + 2 * 3600, $day, $ts)));
        }

        if ($plan['return'] !== null && $firstLine !== null) {
            $this->salesReturn($plan, $invoiceId, $firstLine, $customerId, $credit, $day, $ts);
        }
    }

    /**
     * @param  array{store: int, kind: string, walk_in: bool, customer: int, lines: list<array{0: int, 1: string}>, return: array{0: int, 1: string}|null}  $plan
     */
    private function salesReturn(array $plan, int $invoiceId, int $invoiceLineId, int $customerId, bool $credit, int $day, int $invoiceTs): void
    {
        [$k, $qty] = $plan['return'] ?? throw new RuntimeException('No return planned.');
        $item = $this->items[$k];
        $store = $this->stores[$plan['store']];
        $date = $this->dates[$day];
        $ymd = str_replace('-', '', $date);
        $at = $this->format($this->clamp($invoiceTs + 1800, $day, $invoiceTs));
        $amount = Decimal::mul($qty, $item['price']);
        // The app refunds the anonymous walk-in customer in cash; account customers of a credit
        // sale get a balance credit, the others cash.
        $refund = $credit ? ReturnDocument::REFUND_BALANCE : ReturnDocument::REFUND_CASH;

        $returnId = $this->id('returns');
        $number = $this->nextNumber("RET-SALES-{$ymd}", 4);
        $this->add('returns', [
            'id' => $returnId, 'return_number' => $number, 'return_type' => 'sales_return', 'invoice_id' => $invoiceId,
            'customer_id' => $customerId, 'user_id' => $this->userId, 'store_id' => $store['id'], 'total_amount' => $amount,
            'return_date' => $date, 'reason' => 'مرتجع أداء', 'refund_method' => $refund, 'created_at' => $at, 'updated_at' => $at,
        ]);
        $this->add('return_items', [
            'id' => $this->id('return_items'), 'return_id' => $returnId, 'item_id' => $item['id'], 'invoice_item_id' => $invoiceLineId,
            'quantity' => $qty, 'unit_price' => $item['price'], 'total_price' => $amount, 'created_at' => $at, 'updated_at' => $at,
        ]);
        $this->movement($plan['store'], $k, 'sales_return_in', $qty, $item['cost'], ReturnDocument::class, $returnId, $number, $at);
        $this->counts['returns']++;

        if ($refund === ReturnDocument::REFUND_CASH) {
            $this->payment("PAY-RET-{$ymd}", ['customer_id' => $customerId, 'invoice_id' => $invoiceId, 'return_id' => $returnId], $store['id'], bcsub('0.000', $amount, 3), $date, 'cash', $at);
        }
    }

    private function purchase(int $p, int $day, int $rank): void
    {
        $plan = $this->purchasePlan($p);
        $store = $this->stores[$plan['store']];
        $date = $this->dates[$day];
        $ymd = str_replace('-', '', $date);
        $ts = $this->clamp($this->dayStarts[$day] + 7 * 3600 + $rank * 60, $day);
        $at = $this->format($ts);
        $supplierId = $this->supplierIds[$p % count($this->supplierIds)];

        $purchaseId = $this->id('purchases');
        $number = $this->nextNumber("PUR-{$ymd}", 4);
        $net = '0.000';
        foreach ($plan['lines'] as [$k, $qty]) {
            $item = $this->items[$k];
            $total = Decimal::mul($qty, $item['cost']);
            $net = bcadd($net, $total, 3);
            $this->add('purchase_items', [
                'id' => $this->id('purchase_items'), 'purchase_id' => $purchaseId, 'item_id' => $item['id'], 'quantity' => $qty,
                'cost_price' => $item['cost'], 'total_price' => $total, 'base_cost_price' => $item['cost'], 'allocated_expense' => '0.000',
                'wac_before' => $item['cost'], 'cost_price_before' => $item['cost'], 'created_at' => $at, 'updated_at' => $at,
            ]);
            $this->movement($plan['store'], $k, 'purchase_in', $qty, $item['cost'], Purchase::class, $purchaseId, $number, $at);
            $this->counts['purchase_lines']++;
        }

        // Even: paid at issue (PAY-PUR); 1 mod 4: settled later the same day (PAY-SUPP); 3 mod 4: on credit.
        $paid = $p % 4 === 3 ? '0.000' : $net;
        $this->add('purchases', [
            'id' => $purchaseId, 'purchase_number' => $number, 'supplier_id' => $supplierId, 'user_id' => $this->userId,
            'store_id' => $store['id'], 'purchase_date' => $date, 'status' => 'confirmed',
            'payment_status' => bccomp($paid, '0.000', 3) === 0 ? 'unpaid' : 'paid', 'subtotal' => $net, 'discount_amount' => '0.000',
            'net_total' => $net, 'paid_amount' => $paid, 'remaining_amount' => bcsub($net, $paid, 3), 'additional_expenses_total' => '0.000',
            'created_at' => $at, 'updated_at' => $at,
        ]);
        $this->counts['purchases']++;

        if ($p % 2 === 0) {
            $this->payment("PAY-PUR-{$ymd}", ['supplier_id' => $supplierId, 'purchase_id' => $purchaseId], $store['id'], $net, $date, 'cash', $at);
        } elseif ($p % 4 === 1) {
            $this->payment("PAY-SUPP-{$ymd}", ['supplier_id' => $supplierId, 'purchase_id' => $purchaseId], $store['id'], $net, $date, 'cash', $this->format($this->clamp($ts + 3600, $day, $ts)));
        }
    }

    private function expenses(int $day): void
    {
        $date = $this->dates[$day];
        $prefix = 'EXP-'.substr(str_replace('-', '', $date), 2);
        $categories = ['rent', 'utilities', 'supplies', 'salaries'];

        for ($e = 0; $e < 2; $e++) {
            $at = $this->format($this->clamp($this->dayStarts[$day] + 15 * 3600 + $e * 1800, $day));
            $this->add('expenses', [
                'id' => $this->id('expenses'), 'expense_number' => $this->nextNumber($prefix, 4), 'category' => $categories[($day + $e) % 4],
                'title' => 'مصروف أداء', 'amount' => Decimal::normalize((50 + (($day * 7 + $e * 3) % 20) * 5).'.500'),
                'expense_date' => $date, 'payment_method' => 'cash', 'user_id' => $this->userId,
                'store_id' => $this->stores[($day * 2 + $e) % count($this->stores)]['id'], 'cost_center' => 'operational',
                'created_at' => $at, 'updated_at' => $at,
            ]);
            $this->counts['expenses']++;
        }
    }

    /**
     * @param  array<string, int>  $links  party / document columns
     */
    private function payment(string $prefix, array $links, int $storeId, string $amount, string $date, string $method, string $at): void
    {
        $this->add('payments', array_merge([
            'id' => $this->id('payments'), 'payment_number' => $this->nextNumber($prefix, 6), 'customer_id' => null,
            'supplier_id' => null, 'invoice_id' => null, 'purchase_id' => null, 'return_id' => null,
        ], $links, [
            'user_id' => $this->userId, 'store_id' => $storeId, 'amount' => $amount, 'payment_date' => $date,
            'payment_method' => $method, 'created_at' => $at, 'updated_at' => $at,
        ]));
        $this->counts['payments']++;
    }

    private function movement(int $s, int $k, string $type, string $qty, string $unitCost, string $sourceType, int $sourceId, string $document, string $at): void
    {
        $inbound = $type !== 'sales_out';
        $before = $this->itemStock[$k];
        $after = $inbound ? bcadd($before, $qty, 3) : bcsub($before, $qty, 3);
        $this->itemStock[$k] = $after;
        $this->storeStock[$s][$k] = $inbound ? bcadd($this->storeStock[$s][$k], $qty, 3) : bcsub($this->storeStock[$s][$k], $qty, 3);

        $this->add('stock_movements', [
            'id' => $this->id('stock_movements'), 'item_id' => $this->items[$k]['id'], 'store_id' => $this->stores[$s]['id'],
            'movement_type' => $type, 'quantity' => $qty, 'stock_before' => $before, 'stock_after' => $after, 'unit_cost' => $unitCost,
            'source_type' => $sourceType, 'source_id' => $sourceId, 'document_number' => $document, 'user_id' => $this->userId,
            'created_at' => $at, 'updated_at' => $at,
        ]);
        $this->counts['stock_movements']++;
    }

    // ------------------------------------------------------------------
    // Plans (pure functions of the index)
    // ------------------------------------------------------------------

    /**
     * @return array{store: int, kind: string, walk_in: bool, customer: int, lines: list<array{0: int, 1: string}>, return: array{0: int, 1: string}|null}
     */
    private function invoicePlan(int $i): array
    {
        $itemCount = count($this->items);
        $lineCount = 1 + self::mix($i, 2) % 4;
        $start = self::mix($i, 10) % $itemCount;
        $lines = [];
        $seen = [];
        for ($j = 0; $j < $lineCount; $j++) {
            $k = ($start + $j * 7919) % $itemCount;
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $pick = self::mix($i, 20 + $j);
            $qty = $this->items[$k]['weighted']
                ? ['0.250', '0.500', '1.000', '1.250'][$pick % 4]
                : (1 + $pick % 3).'.000';
            $lines[] = [$k, $qty];
        }

        $kindRoll = self::mix($i, 3) % 10;
        $kind = $kindRoll < 2 ? 'credit' : ($kindRoll === 2 ? 'instapay' : 'cash');
        $walkIn = $kind !== 'credit' && self::mix($i, 4) % 10 < 7;
        $return = null;
        if ($i % 97 === 50) {
            [$k] = $lines[0];
            $return = [$k, $this->items[$k]['weighted'] ? '0.250' : '1.000'];
        }

        return [
            'store' => self::mix($i, 1) % count($this->stores),
            'kind' => $kind,
            'walk_in' => $walkIn,
            'customer' => self::mix($i, 5) % count($this->customerIds),
            'lines' => $lines,
            'return' => $return,
        ];
    }

    /**
     * @return array{store: int, lines: list<array{0: int, 1: string}>}
     */
    private function purchasePlan(int $p): array
    {
        $itemCount = count($this->items);
        $lines = [];
        $seen = [];
        for ($j = 0; $j < 3; $j++) {
            $k = ($p * 13 + $j * 101) % $itemCount;
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $lines[] = [$k, $this->items[$k]['weighted'] ? '10.000' : '20.000'];
        }

        return ['store' => $p % count($this->stores), 'lines' => $lines];
    }

    private function purchaseCount(): int
    {
        return max($this->options->stores, intdiv($this->options->invoices, 50));
    }

    /** Day (0 = oldest) of document $i of $total, spread evenly over the period. */
    private function dayOf(int $i, int $total): int
    {
        return intdiv($i * $this->options->days, max(1, $total));
    }

    /** First document index whose day is >= $day. */
    private function firstOfDay(int $day, int $total): int
    {
        return min($total, intdiv($day * $total + $this->options->days - 1, $this->options->days));
    }

    /** 32-bit integer hash (deterministic on every 64-bit PHP). */
    private static function mix(int $a, int $b): int
    {
        $h = ($a * 0x9E3779B1 + $b * 0x85EBCA77 + 0x165667B1) & 0xFFFFFFFF;
        $h ^= $h >> 15;
        $h = ($h * 0x2C1B3C6D) & 0xFFFFFFFF;
        $h ^= $h >> 12;
        $h = ($h * 0x297A2D39) & 0xFFFFFFFF;
        $h ^= $h >> 15;

        return $h;
    }

    // ------------------------------------------------------------------
    // Numbers
    // ------------------------------------------------------------------

    /**
     * Highest sequence already issued per prefix: the counters table and the documents
     * themselves (a number typed by hand, or a tenant whose counters were never seeded).
     */
    private function loadCounters(): void
    {
        foreach (DB::table('document_sequences')->get(['scope', 'last_value']) as $row) {
            $this->counters[(string) $row->scope] = max($this->counters[(string) $row->scope] ?? 0, (int) $row->last_value);
        }

        foreach (self::NUMBERED as $table => $column) {
            foreach (DB::table($table)->select($column)->orderBy('id')->lazy(2_000) as $row) {
                $number = (string) $row->{$column};
                $dash = strrpos($number, '-');
                if ($dash === false) {
                    continue;
                }
                $suffix = substr($number, $dash + 1);
                if ($suffix !== '' && ctype_digit($suffix)) {
                    $prefix = substr($number, 0, $dash);
                    $this->counters[$prefix] = max($this->counters[$prefix] ?? 0, (int) $suffix);
                }
            }
        }
    }

    private function nextNumber(string $prefix, int $pad): string
    {
        $value = ($this->counters[$prefix] ?? 0) + 1;
        $this->counters[$prefix] = $value;
        $this->touchedScopes[$prefix] = $value;

        return $prefix.'-'.str_pad((string) $value, $pad, '0', STR_PAD_LEFT);
    }

    /**
     * Leave every counter this run used at its last number (DocumentNumberService scopes are
     * the visible prefixes), so the next real document continues after the seeded ones.
     */
    private function saveCounters(): void
    {
        $rows = [];
        foreach ($this->touchedScopes as $scope => $value) {
            $rows[] = ['scope' => $scope, 'last_value' => $value, 'created_at' => $this->stamp, 'updated_at' => $this->stamp];
        }

        foreach (array_chunk($rows, self::ROWS_PER_STATEMENT) as $chunk) {
            DB::table('document_sequences')->upsert($chunk, ['scope'], ['last_value', 'updated_at']);
        }
    }

    /**
     * The branch code in an invoice number, exactly as InvoiceService::invoiceNumberPrefix()
     * builds it (upper-case letters and digits of stores.code, else "B" + the zero-padded id).
     */
    private function invoiceStoreCode(int $id, string $code): string
    {
        $clean = (string) preg_replace('/[^A-Za-z0-9]/', '', strtoupper($code));

        return $code !== '' ? $clean : 'B'.str_pad((string) $id, 2, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------
    // Plumbing
    // ------------------------------------------------------------------

    private function assertStockMatchesThePlan(): void
    {
        foreach ($this->finalStock as $s => $items) {
            foreach ($items as $k => $final) {
                if (bccomp($this->storeStock[$s][$k], $final, 3) !== 0) {
                    throw new RuntimeException("Perf seed: movements of store #{$s} item #{$k} end at {$this->storeStock[$s][$k]}, planned {$final}.");
                }
            }
        }
    }

    private function id(string $table): int
    {
        return $this->nextId[$table]++;
    }

    /** @param array<string, mixed> $row */
    private function add(string $table, array $row): void
    {
        $this->buffers[$table][] = $row;
        $this->buffered++;
    }

    /**
     * Called only between documents: a document's lines are buffered before its header row,
     * so flushing in the middle of one would insert lines before their parent (foreign keys).
     */
    private function flushIfFull(): void
    {
        if ($this->buffered >= self::FLUSH_EVERY) {
            $this->flush();
        }
    }

    private function flush(): void
    {
        foreach (self::TABLES as $table) {
            foreach (array_chunk($this->buffers[$table] ?? [], self::ROWS_PER_STATEMENT) as $chunk) {
                DB::table($table)->insert($chunk);
            }
            $this->buffers[$table] = [];
        }
        $this->buffered = 0;
    }

    /**
     * Never after the run starts, never before $notBefore; a document of an earlier day
     * keeps its own time.
     */
    private function clamp(int $ts, int $day, int $notBefore = 0): int
    {
        $latest = $day === count($this->dates) - 1 ? $this->latestTimestamp : PHP_INT_MAX;

        return max(min($ts, $latest), min($notBefore, $latest), $this->dayStarts[$day]);
    }

    private function format(int $ts): string
    {
        return (new DateTimeImmutable('@'.$ts))->setTimezone($this->storageZone)->format('Y-m-d H:i:s');
    }
}
