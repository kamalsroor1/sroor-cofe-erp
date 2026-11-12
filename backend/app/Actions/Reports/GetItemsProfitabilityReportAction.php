<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Services\Reports\InvoiceAggregates;
use App\Services\Reports\SalesReturnAggregates;
use App\Support\Money\Decimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class GetItemsProfitabilityReportAction
{
    public function __construct(
        private readonly SalesReturnAggregates $salesReturns,
        private readonly InvoiceAggregates $invoices,
    ) {}

    /**
     * Item-level sales, revenue, COGS, gross profit and margin, one page at a time.
     *
     * BUG-01: quantities and values are NET of the period's sales returns (by return date):
     * total_qty / total_revenue / total_cogs / profit / margin are net; gross_* and returned_*
     * expose the two parts. An item returned in the period but sold earlier still gets a row.
     *
     * PERF-01: ONE statement (the period's invoice lines grouped per item UNION ALL the per-item
     * returns, summed per item) yields at most one light row of 7 numbers per item; nothing is loaded
     * as a model. The rows are ordered by net revenue (highest first, then item id) and paged
     * here; `totals` is the exact bcmath sum of EVERY row, so it never depends on the page.
     * Only the page's items are read for their names. `sold_by_item` (gross sold quantity and
     * revenue of every item sold) lets the ABC analysis of the same period skip its own scan.
     *
     * @return array{rows: list<array<string, mixed>>, total: int, totals: array<string, mixed>, sold_by_item: array<int, array{quantity: string, revenue: string}>}
     */
    public function execute(ReportFilterDTO $dto, int $page = 1, int $perPage = 100): array
    {
        $rows = [];
        foreach ($this->groupedRows($dto)->cursor() as $row) {
            $rows[] = $this->figures($row);
        }

        usort($rows, fn (array $a, array $b): int => bccomp($b['total_revenue'], $a['total_revenue'], 3) ?: $a['item_id'] <=> $b['item_id']);

        $totals = $this->totals($rows);

        /** @var array<int, array{quantity: string, revenue: string}> $soldByItem */
        $soldByItem = [];
        foreach ($rows as $row) {
            if ($row['sold']) {
                $soldByItem[$row['item_id']] = ['quantity' => $row['gross_qty'], 'revenue' => $row['gross_revenue']];
            }
        }

        $pageRows = array_slice($rows, (max(1, $page) - 1) * max(1, $perPage), max(1, $perPage));
        unset($rows);

        return [
            'rows' => $this->withItemDetails($pageRows),
            'total' => $totals['items_count'],
            'totals' => $totals,
            'sold_by_item' => $soldByItem,
        ];
    }

    /**
     * item_id, gross_qty, gross_revenue, gross_cogs, returned_qty, returned_value,
     * returned_cost, sold_lines (0 = returned only) per item of the period.
     */
    private function groupedRows(ReportFilterDTO $dto): Builder
    {
        $storeId = $dto->store_id ?: null;

        // Pre-grouped per item (one row per item, not one per line) before the UNION ALL, so
        // the database never materializes the period's invoice lines.
        $soldLines = $this->invoices->confirmed($dto->from_date, $dto->to_date, $storeId)
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereNull('invoice_items.deleted_at')
            ->groupBy('invoice_items.item_id')
            ->select('invoice_items.item_id as report_item_id')
            ->selectRaw('1 as sold_lines')
            ->selectRaw('SUM(invoice_items.quantity) as gross_qty')
            ->selectRaw('SUM(invoice_items.total_price) as gross_revenue')
            ->selectRaw('SUM(invoice_items.quantity * invoice_items.cost_price) as gross_cogs')
            ->selectRaw('0 as returned_qty')
            ->selectRaw('0 as returned_value')
            ->selectRaw('0 as returned_cost');

        $returned = DB::query()
            ->fromSub($this->salesReturns->byItemSubquery($dto->from_date, $dto->to_date, $dto->store_id), 'item_returns')
            ->select('item_returns.returned_item_id as report_item_id')
            ->selectRaw('0 as sold_lines')
            ->selectRaw('0 as gross_qty')
            ->selectRaw('0 as gross_revenue')
            ->selectRaw('0 as gross_cogs')
            ->selectRaw('item_returns.returned_qty as returned_qty')
            ->selectRaw('item_returns.returned_value as returned_value')
            ->selectRaw('item_returns.returned_cost as returned_cost');

        return DB::query()
            ->fromSub($soldLines->unionAll($returned), 'report_lines')
            ->groupBy('report_lines.report_item_id')
            ->select('report_lines.report_item_id')
            ->selectRaw('SUM(report_lines.sold_lines) as sold_lines')
            ->selectRaw('SUM(report_lines.gross_qty) as gross_qty')
            ->selectRaw('SUM(report_lines.gross_revenue) as gross_revenue')
            ->selectRaw('SUM(report_lines.gross_cogs) as gross_cogs')
            ->selectRaw('SUM(report_lines.returned_qty) as returned_qty')
            ->selectRaw('SUM(report_lines.returned_value) as returned_value')
            ->selectRaw('SUM(report_lines.returned_cost) as returned_cost')
            ->orderBy('report_lines.report_item_id');
    }

    /**
     * The scale-3 figures of one grouped row (net = gross - returned).
     *
     * @return array<string, mixed>
     */
    private function figures(object $row): array
    {
        $grossQty = Decimal::normalize(InvoiceAggregates::scalar($row->gross_qty));
        $grossRevenue = Decimal::normalize(InvoiceAggregates::scalar($row->gross_revenue));
        $grossCogs = Decimal::normalize(InvoiceAggregates::scalar($row->gross_cogs));
        $returnedQty = Decimal::normalize(InvoiceAggregates::scalar($row->returned_qty));
        $returnedValue = Decimal::normalize(InvoiceAggregates::scalar($row->returned_value));
        $returnedCost = Decimal::normalize(InvoiceAggregates::scalar($row->returned_cost));
        $netRevenue = bcsub($grossRevenue, $returnedValue, 3);
        $netCogs = bcsub($grossCogs, $returnedCost, 3);

        return [
            'item_id' => (int) $row->report_item_id,
            'sold' => (int) $row->sold_lines > 0,
            'total_qty' => bcsub($grossQty, $returnedQty, 3),
            'total_revenue' => $netRevenue,
            'total_cogs' => $netCogs,
            'profit' => bcsub($netRevenue, $netCogs, 3),
            'gross_qty' => $grossQty,
            'gross_revenue' => $grossRevenue,
            'gross_cogs' => $grossCogs,
            'returned_qty' => $returnedQty,
            'returned_value' => $returnedValue,
            'returned_cogs' => $returnedCost,
        ];
    }

    /**
     * The response rows of one page: item name / code / category / unit added.
     * An item sold in the period is shown only while it is not deleted (as its relation
     * excluded trashed items); an item only returned is shown even when deleted.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withItemDetails(array $rows): array
    {
        $items = DB::table('items')
            ->whereIn('id', array_column($rows, 'item_id'))
            ->get(['id', 'name', 'code', 'category', 'unit', 'deleted_at'])
            ->keyBy('id');

        $out = [];
        foreach ($rows as $row) {
            $item = $items->get($row['item_id']);
            if ($item !== null && $row['sold'] && $item->deleted_at !== null) {
                $item = null;
            }

            $out[] = [
                'item_id' => $row['item_id'],
                'name' => $item?->name ?? __('inventory.deleted_item'),
                'code' => $item?->code,
                'category' => $item?->category,
                'unit' => $item?->unit,
                'total_qty' => $row['total_qty'],
                'total_revenue' => $row['total_revenue'],
                'total_cogs' => $row['total_cogs'],
                'profit' => $row['profit'],
                'margin' => $this->margin($row['profit'], $row['total_revenue'], 1),
                'gross_qty' => $row['gross_qty'],
                'gross_revenue' => $row['gross_revenue'],
                'gross_cogs' => $row['gross_cogs'],
                'returned_qty' => $row['returned_qty'],
                'returned_value' => $row['returned_value'],
                'returned_cogs' => $row['returned_cogs'],
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totals(array $rows): array
    {
        $sum = array_fill_keys(['total_qty', 'total_revenue', 'total_cogs', 'profit', 'gross_qty', 'gross_revenue', 'gross_cogs', 'returned_qty', 'returned_value', 'returned_cogs'], '0.000');
        foreach ($rows as $row) {
            foreach ($sum as $key => $value) {
                $sum[$key] = bcadd($value, $row[$key], 3);
            }
        }

        return array_merge(['items_count' => count($rows)], $sum, [
            'margin' => $this->margin($sum['profit'], $sum['total_revenue'], 1),
        ]);
    }

    private function margin(string $profit, string $revenue, int $scale): string
    {
        if (bccomp($revenue, '0.000', 3) <= 0) {
            return Decimal::normalize('0', $scale);
        }

        return Decimal::normalize(bcmul(bcdiv($profit, $revenue, 4), '100', $scale), $scale);
    }
}
