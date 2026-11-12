<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * PERF-01: a deterministic, report-sized tenant dataset written with bulk inserts (no Eloquent
 * events, no services), so tens of thousands of documents load in seconds.
 *
 * Must run INSIDE the tenant (inTenant / useTenantForTest). Ids start 100,000 above the highest
 * existing user/store id (the harness offsets every tenant's ids), so they never collide with
 * the harness fixtures (main store, admin user).
 *
 * Shape for $invoices invoices over the 365 days ending on $today:
 *  - 3 stores (the harness main store + 2), $items items, a stock row per store and item;
 *  - 200 customers; 1-4 lines per invoice (~2.6 on average, every third line weighed: 0.250);
 *  - every 5th invoice on credit, the rest paid in cash or InstaPay with a sale voucher;
 *  - every 50th invoice cancelled; every 100th invoice partly returned (1 unit) on the next day;
 *  - 3 expenses a day spread over the stores.
 */
trait BuildsLargeReportDataset
{
    /**
     * @return array{base: int, stores: list<int>, invoices: int, lines: int, payments: int, returns: int, expenses: int, items: int}
     */
    protected function buildLargeReportDataset(int $mainStoreId, int $userId, string $today, int $invoices = 50_000, int $items = 5_000): array
    {
        $now = CarbonImmutable::parse($today)->setTime(12, 0);
        $base = max((int) DB::table('stores')->max('id'), (int) DB::table('users')->max('id')) + 100_000;
        $stamp = $now->format('Y-m-d H:i:s');

        $stores = [$mainStoreId, $base + 1, $base + 2];
        DB::table('stores')->insert([
            ['id' => $base + 1, 'name' => 'فرع أداء 1', 'code' => 'PERF-S1', 'type' => 'retail_shop', 'is_active' => true, 'is_main' => false, 'created_at' => $stamp, 'updated_at' => $stamp],
            ['id' => $base + 2, 'name' => 'فرع أداء 2', 'code' => 'PERF-S2', 'type' => 'retail_shop', 'is_active' => true, 'is_main' => false, 'created_at' => $stamp, 'updated_at' => $stamp],
        ]);

        // Items and their stock rows.
        $costs = [];
        $prices = [];
        $rows = [];
        $stockRows = [];
        for ($k = 0; $k < $items; $k++) {
            $cost = Decimal::normalize((string) (10 + ($k % 200)).'.250');
            $price = Decimal::mul($cost, '1.300');
            $costs[$k] = $cost;
            $prices[$k] = $price;
            $rows[] = [
                'id' => $base + $k, 'code' => sprintf('PERF-%05d', $k), 'name' => 'صنف أداء '.$k, 'category' => 'coffee_beans',
                'unit' => 'كجم', 'current_stock' => '300.000', 'cost_price' => $cost, 'weighted_avg_cost' => $cost,
                'selling_price' => $price, 'min_stock_level' => '1.000', 'is_active' => $k % 25 !== 0,
                'created_at' => $stamp, 'updated_at' => $stamp,
            ];
            foreach ($stores as $storeId) {
                $stockRows[] = ['store_id' => $storeId, 'item_id' => $base + $k, 'quantity' => '100.000', 'created_at' => $stamp, 'updated_at' => $stamp];
            }
            if (count($rows) >= 1000) {
                DB::table('items')->insert($rows);
                $rows = [];
            }
            if (count($stockRows) >= 3000) {
                DB::table('store_stocks')->insert($stockRows);
                $stockRows = [];
            }
        }
        $this->flushInsert('items', $rows);
        $this->flushInsert('store_stocks', $stockRows);

        $customers = [];
        for ($c = 0; $c < 200; $c++) {
            $customers[] = ['id' => $base + $c, 'name' => 'عميل أداء '.$c, 'phone' => sprintf('0109%07d', $c), 'price_tier' => 'retail', 'current_balance' => '0.000', 'is_active' => true, 'created_at' => $stamp, 'updated_at' => $stamp];
        }
        DB::table('customers')->insert($customers);

        $invoiceRows = [];
        $lineRows = [];
        $paymentRows = [];
        $returnRows = [];
        $returnLineRows = [];
        $lineId = $base;
        $counts = ['lines' => 0, 'payments' => 0, 'returns' => 0];

        for ($i = 0; $i < $invoices; $i++) {
            $invoiceId = $base + $i;
            $date = $now->subDays($i % 365);
            $createdAt = $date->setTime(6 + ($i % 14), ($i * 7) % 60)->format('Y-m-d H:i:s');
            $storeId = $stores[$i % 3];
            $lineCount = 1 + ($i % 4) + ($i % 7 === 0 ? 1 : 0);

            $net = '0.000';
            $cost = '0.000';
            $firstLine = null;
            for ($j = 0; $j < $lineCount; $j++) {
                $k = ($i * 7 + $j * 13) % $items;
                $qty = $j % 3 === 2 ? '0.250' : (1 + (($i + $j) % 3)).'.000';
                $total = Decimal::mul($qty, $prices[$k]);
                $lineCost = Decimal::mul($qty, $costs[$k]);
                $net = bcadd($net, $total, 3);
                $cost = bcadd($cost, $lineCost, 3);
                $lineRows[] = [
                    'id' => $lineId, 'invoice_id' => $invoiceId, 'item_id' => $base + $k, 'quantity' => $qty,
                    'cost_price' => $costs[$k], 'unit_price' => $prices[$k], 'discount_amount' => '0.000', 'total_price' => $total,
                    'created_at' => $createdAt, 'updated_at' => $createdAt,
                ];
                $firstLine ??= ['id' => $lineId, 'item' => $base + $k, 'price' => $prices[$k]];
                $lineId++;
                $counts['lines']++;
            }

            $credit = $i % 5 === 0;
            $method = $i % 4 === 1 ? 'instapay' : 'cash';
            $paid = $credit ? '0.000' : $net;
            $invoiceRows[] = [
                'id' => $invoiceId, 'invoice_number' => sprintf('PERF-INV-%07d', $i), 'customer_id' => $base + ($i % 200),
                'user_id' => $userId, 'store_id' => $storeId, 'invoice_date' => $date->toDateString(),
                'payment_type' => $credit ? 'credit' : 'cash', 'payment_method' => $method,
                'status' => $i % 50 === 0 ? 'cancelled' : 'confirmed', 'payment_status' => $credit ? 'unpaid' : 'paid',
                'subtotal' => $net, 'net_total' => $net, 'paid_amount' => $paid, 'remaining_amount' => bcsub($net, $paid, 3),
                'total_cost' => $cost, 'created_at' => $createdAt, 'updated_at' => $createdAt,
            ];

            if (! $credit) {
                $paymentRows[] = [
                    'id' => $invoiceId, 'payment_number' => sprintf('PERF-PAY-%07d', $i), 'customer_id' => $base + ($i % 200),
                    'invoice_id' => $invoiceId, 'user_id' => $userId, 'store_id' => $storeId, 'amount' => $paid,
                    'payment_date' => $date->toDateString(), 'payment_method' => $method,
                    'created_at' => $createdAt, 'updated_at' => $createdAt,
                ];
                $counts['payments']++;
            }

            if ($i % 100 === 50 && $firstLine !== null && $i % 365 > 0) {
                $returnId = $base + $i;
                $returnDate = $date->addDay();
                $unitValue = $firstLine['price'];
                $returnRows[] = [
                    'id' => $returnId, 'return_number' => sprintf('PERF-RET-%07d', $i), 'return_type' => 'sales_return',
                    'invoice_id' => $invoiceId, 'customer_id' => $base + ($i % 200), 'user_id' => $userId, 'store_id' => $storeId,
                    'total_amount' => $unitValue, 'return_date' => $returnDate->toDateString(), 'refund_method' => 'balance',
                    'created_at' => $returnDate->setTime(13, 0)->format('Y-m-d H:i:s'), 'updated_at' => $stamp,
                ];
                $returnLineRows[] = [
                    'return_id' => $returnId, 'item_id' => $firstLine['item'], 'invoice_item_id' => $firstLine['id'],
                    'quantity' => '1.000', 'unit_price' => $unitValue, 'total_price' => $unitValue,
                    'created_at' => $stamp, 'updated_at' => $stamp,
                ];
                $counts['returns']++;
            }

            if (count($invoiceRows) >= 1000) {
                DB::table('invoices')->insert($invoiceRows);
                DB::table('invoice_items')->insert($lineRows);
                $this->flushInsert('payments', $paymentRows);
                $invoiceRows = $lineRows = $paymentRows = [];
            }
        }
        $this->flushInsert('invoices', $invoiceRows);
        $this->flushInsert('invoice_items', $lineRows);
        $this->flushInsert('payments', $paymentRows);
        $this->flushInsert('returns', $returnRows);
        $this->flushInsert('return_items', $returnLineRows);

        $expenses = [];
        for ($d = 0; $d < 365; $d++) {
            for ($e = 0; $e < 3; $e++) {
                $date = $now->subDays($d);
                $expenses[] = [
                    'expense_number' => sprintf('PERF-EXP-%03d-%d', $d, $e), 'category' => ['rent', 'utilities', 'supplies'][$e],
                    'title' => 'مصروف أداء', 'amount' => (25 + $e * 10).'.500', 'expense_date' => $date->toDateString(),
                    'payment_method' => 'cash', 'user_id' => $userId, 'store_id' => $stores[$e], 'cost_center' => 'general',
                    'created_at' => $date->setTime(15, 0)->format('Y-m-d H:i:s'), 'updated_at' => $stamp,
                ];
            }
        }
        foreach (array_chunk($expenses, 1000) as $chunk) {
            DB::table('expenses')->insert($chunk);
        }

        // Make the optimizer see the real row counts (MySQL; a no-op elsewhere).
        if (DB::connection()->getDriverName() === 'mysql') {
            foreach (['items', 'store_stocks', 'customers', 'invoices', 'invoice_items', 'payments', 'returns', 'return_items', 'expenses'] as $table) {
                DB::statement("ANALYZE TABLE {$table}");
            }
        }

        return [
            'base' => $base,
            'stores' => $stores,
            'invoices' => $invoices,
            'lines' => $counts['lines'],
            'payments' => $counts['payments'],
            'returns' => $counts['returns'],
            'expenses' => count($expenses),
            'items' => $items,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function flushInsert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
