<?php

declare(strict_types=1);

namespace App\Actions\Perf;

use App\Actions\Reports\GetStockDriftReportAction;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OpeningBalance;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use App\Models\Supplier;
use App\Services\Settings\TenantSettings;
use App\Support\Money\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * PERF-04: read-only consistency checks of the CURRENT tenant, run after a load test (or on a
 * seeded tenant). Every problem names its check and the offending rows:
 *
 *  - duplicate_number: a document number used twice (invoices, payments, returns, purchases,
 *    expenses; soft-deleted rows included, as DocumentNumberService does);
 *  - stock_drift: SUM(store_stocks) != items.current_stock (GetStockDriftReportAction);
 *  - negative_stock: a branch balance below zero while negative stock is not allowed;
 *  - missing_voucher / voucher_mismatch: a confirmed invoice whose sale + collection vouchers
 *    (refund and reversal vouchers excluded) do not add up to its paid_amount;
 *  - customer_balance / supplier_balance: current_balance differs from the app's formula
 *    (CustomerBalanceService / SupplierBalanceService) recomputed from the documents;
 *  - stock_vs_baseline (only with a SnapshotTenantStockAction snapshot): a branch balance differs from
 *    start - sold + returned + every other movement since the snapshot.
 *
 * Grouped SQL only selects grouped columns and aggregates (ONLY_FULL_GROUP_BY safe on MySQL
 * 8.4 / MariaDB 11.8 / sqlite); the money comparison happens in PHP with bcmath.
 */
final class VerifyTenantConsistencyAction
{
    /** Rows listed per problem (the count is always exact). */
    private const SAMPLE = 10;

    private const NUMBERED = [
        'invoices' => 'invoice_number',
        'payments' => 'payment_number',
        'returns' => 'return_number',
        'purchases' => 'purchase_number',
        'expenses' => 'expense_number',
    ];

    /** Movements already counted through the documents in the baseline check. */
    private const DOCUMENT_MOVEMENTS = ['sales_out', 'sales_return_in'];

    public function __construct(
        private readonly GetStockDriftReportAction $stockDrift,
        private readonly TenantSettings $settings,
    ) {}

    /**
     * @param  array{max_ids: array{invoices: int, returns: int, stock_movements: int}, stocks: array<string, string>}|null  $baseline
     * @return array{problems: list<array{check: string, count: int, sample: list<string>}>, checked: array<string, int>}
     */
    public function execute(?array $baseline = null): array
    {
        $problems = [];
        $checked = [];

        foreach (self::NUMBERED as $table => $column) {
            $duplicates = DB::table($table)->select($column)->selectRaw('COUNT(*) as uses')
                ->groupBy($column)->havingRaw('COUNT(*) > 1')->orderBy($column)->get();
            $checked[$table] = DB::table($table)->count();
            if ($duplicates->isNotEmpty()) {
                $problems[] = $this->problem('duplicate_number', $duplicates->count(), $duplicates->map(
                    fn (object $row): string => $table.': '.$row->{$column}.' x'.$row->uses,
                )->all());
            }
        }

        $drift = $this->stockDrift->execute(1, 0)['items'];
        if ($drift !== []) {
            $problems[] = $this->problem('stock_drift', count($drift), array_map(
                fn (array $item): string => ($item['code'] ?? '#'.$item['id']).': '.$item['current_stock'].' != '.$item['stores_stock_sum'],
                $drift,
            ));
        }

        if (! $this->settings->allowNegativeStock()) {
            $negative = DB::table('store_stocks')->whereNull('deleted_at')->where('quantity', '<', 0)
                ->orderBy('id')->get(['store_id', 'item_id', 'quantity']);
            if ($negative->isNotEmpty()) {
                $problems[] = $this->problem('negative_stock', $negative->count(), $negative->map(
                    fn (object $row): string => 'store '.$row->store_id.' item '.$row->item_id.': '.Decimal::normalize((string) $row->quantity),
                )->all());
            }
        }

        $problems = [...$problems, ...$this->vouchers()];
        $problems = [...$problems, ...$this->partyBalances()];

        if ($baseline !== null) {
            $problems = [...$problems, ...$this->againstBaseline($baseline)];
        }

        $checked['store_stocks'] = DB::table('store_stocks')->whereNull('deleted_at')->count();
        $checked['customers'] = DB::table('customers')->count();
        $checked['suppliers'] = DB::table('suppliers')->count();

        return ['problems' => $problems, 'checked' => $checked];
    }

    /**
     * @return list<array{check: string, count: int, sample: list<string>}>
     */
    private function vouchers(): array
    {
        // Per-invoice voucher totals in a derived table (no correlated subquery in an aggregate).
        $vouchers = DB::table('payments')
            ->select('invoice_id')
            ->selectRaw('SUM(amount) as total')
            ->selectRaw('COUNT(*) as vouchers')
            ->whereNotNull('invoice_id')
            ->whereNotNull('customer_id')
            ->whereNull('return_id')
            ->whereNull('reverses_payment_id')
            ->whereNull('deleted_at')
            ->groupBy('invoice_id');

        $missing = [];
        $mismatch = [];
        $rows = DB::table('invoices')
            ->leftJoinSub($vouchers, 'v', 'v.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', 'confirmed')
            ->whereNull('invoices.deleted_at')
            ->orderBy('invoices.id')
            ->select(['invoices.id', 'invoices.invoice_number', 'invoices.payment_type', 'invoices.paid_amount', 'v.total', 'v.vouchers']);

        foreach ($rows->lazy(2_000) as $row) {
            $paid = Decimal::normalize((string) $row->paid_amount);
            $total = Decimal::normalize((string) ($row->total ?? '0'));
            if (bccomp($paid, $total, 3) === 0 && ! ($row->payment_type === 'cash' && (int) $row->vouchers === 0)) {
                continue;
            }

            if ((int) $row->vouchers === 0) {
                $missing[] = $row->invoice_number.': paid '.$paid;
            } else {
                $mismatch[] = $row->invoice_number.': paid '.$paid.' != vouchers '.$total;
            }
        }

        $problems = [];
        if ($missing !== []) {
            $problems[] = $this->problem('missing_voucher', count($missing), $missing);
        }
        if ($mismatch !== []) {
            $problems[] = $this->problem('voucher_mismatch', count($mismatch), $mismatch);
        }

        return $problems;
    }

    /**
     * @return list<array{check: string, count: int, sample: list<string>}>
     */
    private function partyBalances(): array
    {
        $customers = $this->expected([
            [Invoice::query()->where('status', 'confirmed'), 'customer_id', 'net_total', '1'],
            [Payment::query()->whereNotNull('customer_id')->excludingCancelledInvoices(), 'customer_id', 'amount', '-1'],
            [ReturnDocument::query()->where('return_type', 'sales_return'), 'customer_id', 'total_amount', '-1'],
            [OpeningBalance::query()->where('party_type', OpeningBalance::PARTY_CUSTOMER), 'party_id', 'amount', '1'],
        ]);
        $suppliers = $this->expected([
            [Purchase::query()->where('status', 'confirmed'), 'supplier_id', 'net_total', '1'],
            [Payment::query()->whereNotNull('supplier_id'), 'supplier_id', 'amount', '-1'],
            [ReturnDocument::query()->where('return_type', 'purchase_return'), 'supplier_id', 'total_amount', '-1'],
            [OpeningBalance::query()->where('party_type', OpeningBalance::PARTY_SUPPLIER), 'party_id', 'amount', '1'],
        ]);

        $problems = [];
        foreach (['customer_balance' => [Customer::query(), $customers], 'supplier_balance' => [Supplier::query(), $suppliers]] as $check => [$query, $expected]) {
            $wrong = [];
            foreach ($query->select(['id', 'name', 'current_balance'])->orderBy('id')->lazy(2_000) as $party) {
                $stored = Decimal::normalize((string) $party->getAttribute('current_balance'));
                $should = $expected[(int) $party->getKey()] ?? '0.000';
                if (bccomp($stored, $should, 3) !== 0) {
                    $wrong[] = '#'.$party->getKey().' '.$party->getAttribute('name').': '.$stored.' != '.$should;
                }
            }
            if ($wrong !== []) {
                $problems[] = $this->problem($check, count($wrong), $wrong);
            }
        }

        return $problems;
    }

    /**
     * @param  list<array{0: Builder<covariant Model>, 1: string, 2: string, 3: string}>  $sources  query, party column, amount column, sign
     * @return array<int, string> party id => expected balance
     */
    private function expected(array $sources): array
    {
        $balances = [];
        foreach ($sources as [$query, $partyColumn, $amountColumn, $sign]) {
            $table = $query->getModel()->getTable();
            $rows = $query->toBase()
                ->select($table.'.'.$partyColumn.' as party_id')
                ->selectRaw('SUM('.$table.'.'.$amountColumn.') as total')
                ->whereNotNull($table.'.'.$partyColumn)
                ->groupBy($table.'.'.$partyColumn)
                ->get();
            foreach ($rows as $row) {
                $id = (int) $row->party_id;
                $balances[$id] = bcadd($balances[$id] ?? '0.000', bcmul(Decimal::normalize((string) $row->total), $sign, 3), 3);
            }
        }

        return $balances;
    }

    /**
     * stock now == stock at the snapshot - sold + returned (documents created since) + every
     * other movement since (purchases, transfers, adjustments, cancellations...). A cancelled
     * or edited invoice counts its original lines as sold and its cancellation_in movement
     * back, so it nets to zero.
     *
     * @param  array{max_ids: array{invoices: int, returns: int, stock_movements: int}, stocks: array<string, string>}  $baseline
     * @return list<array{check: string, count: int, sample: list<string>}>
     */
    private function againstBaseline(array $baseline): array
    {
        // Assumes every invoice created after the snapshot moved stock (confirmed POS sales, which
        // is all the load script produces). Held / draft sales would show up as a false drift.
        $expected = $baseline['stocks'];
        $apply = function (iterable $rows, string $sign) use (&$expected): void {
            foreach ($rows as $row) {
                $key = $row->store_id.':'.$row->item_id;
                $expected[$key] = bcadd($expected[$key] ?? '0.000', bcmul(Decimal::normalize((string) $row->qty), $sign, 3), 3);
            }
        };

        $apply(DB::table('invoice_items')->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.id', '>', $baseline['max_ids']['invoices'])
            ->select(['invoices.store_id', 'invoice_items.item_id'])->selectRaw('SUM(invoice_items.quantity) as qty')
            ->groupBy('invoices.store_id', 'invoice_items.item_id')->get(), '-1');

        $apply(DB::table('return_items')->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->where('returns.id', '>', $baseline['max_ids']['returns'])->where('returns.return_type', 'sales_return')
            ->select(['returns.store_id', 'return_items.item_id'])->selectRaw('SUM(return_items.quantity) as qty')
            ->groupBy('returns.store_id', 'return_items.item_id')->get(), '1');

        $other = DB::table('stock_movements')->where('id', '>', $baseline['max_ids']['stock_movements'])
            ->whereNotIn('movement_type', self::DOCUMENT_MOVEMENTS)
            ->select(['store_id', 'item_id', 'movement_type'])->selectRaw('SUM(quantity) as qty')
            ->groupBy('store_id', 'item_id', 'movement_type')->get();
        foreach ($other as $row) {
            $sign = str_ends_with((string) $row->movement_type, '_out') ? '-1' : '1';
            $apply([$row], $sign);
        }

        $wrong = [];
        foreach (DB::table('store_stocks')->whereNull('deleted_at')->orderBy('id')->select(['id', 'store_id', 'item_id', 'quantity'])->lazy(2_000) as $row) {
            $key = $row->store_id.':'.$row->item_id;
            $now = Decimal::normalize((string) $row->quantity);
            $should = $expected[$key] ?? '0.000';
            unset($expected[$key]);
            if (bccomp($now, $should, 3) !== 0) {
                $wrong[] = 'store '.$row->store_id.' item '.$row->item_id.': '.$now.' != '.$should;
            }
        }
        foreach ($expected as $key => $should) {
            if (bccomp($should, '0.000', 3) !== 0) {
                $wrong[] = $key.': missing row, expected '.$should;
            }
        }

        return $wrong === [] ? [] : [$this->problem('stock_vs_baseline', count($wrong), $wrong)];
    }

    /**
     * @param  array<int, string>  $rows
     * @return array{check: string, count: int, sample: list<string>}
     */
    private function problem(string $check, int $count, array $rows): array
    {
        return ['check' => $check, 'count' => $count, 'sample' => array_slice(array_values($rows), 0, self::SAMPLE)];
    }
}
