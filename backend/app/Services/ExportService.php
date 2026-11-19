<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\Reports\CarryingCost;
use App\Support\Csv\CsvCell;
use App\Support\Money\Decimal;
use App\Support\ProfitVisibility;
use App\Support\StockMovementTypes;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /** P&L summary key => its label, in the screen's order. */
    private const SUMMARY_LABELS = [
        'gross_sales' => 'reports.pl_gross_sales',
        'total_returns' => 'reports.pl_total_returns',
        'returns_count' => 'reports.pl_returns_count',
        'returns_cost' => 'reports.pl_returns_cost',
        'total_sales' => 'reports.pl_total_sales',
        'gross_cogs' => 'reports.pl_gross_cogs',
        'total_cogs' => 'reports.pl_total_cogs',
        'gross_profit' => 'reports.pl_gross_profit',
        'margin_percentage' => 'reports.pl_margin_percentage',
        'total_expenses' => 'reports.pl_total_expenses',
        'expenses_count' => 'reports.pl_expenses_count',
        'net_profit' => 'reports.pl_net_profit',
        'invoices_count' => 'reports.pl_invoices_count',
        'avg_invoice' => 'reports.pl_avg_invoice',
        'total_paid' => 'reports.pl_total_paid',
        'total_remaining' => 'reports.pl_total_remaining',
        'total_customers_debt' => 'reports.pl_total_customers_debt',
        'stock_cost_valuation' => 'reports.pl_stock_cost_valuation',
        'stock_selling_valuation' => 'reports.pl_stock_selling_valuation',
        'expected_stock_profit' => 'reports.pl_expected_stock_profit',
    ];

    /**
     * SEC-1 (M6): P&L figures that are a cost or derive it from a figure next to them (sales -
     * gross profit = COGS). Left out of the CSV of a user without items.view_cost.
     */
    private const SUMMARY_COST_KEYS = [
        'returns_cost', 'gross_cogs', 'total_cogs', 'gross_profit', 'margin_percentage',
        'net_profit', 'stock_cost_valuation', 'expected_stock_profit',
    ];

    /** Item profitability key => its column label. */
    private const ITEM_COLUMNS = [
        'name' => 'reports.col_item',
        'code' => 'reports.col_item_code',
        'category' => 'reports.col_category',
        'unit' => 'reports.col_unit',
        'gross_qty' => 'reports.col_gross_qty',
        'returned_qty' => 'reports.col_returns_qty',
        'total_qty' => 'reports.col_net_qty',
        'gross_revenue' => 'reports.col_gross_sales',
        'returned_value' => 'reports.col_returns_value',
        'total_revenue' => 'reports.col_net_sales',
        'total_cogs' => 'reports.col_cogs',
        'profit' => 'reports.col_profit',
        'margin' => 'reports.col_margin',
    ];

    /** Item profitability columns that are a cost or derive it (revenue - profit = COGS). */
    private const ITEM_COST_KEYS = ['total_cogs', 'gross_cogs', 'returned_cogs', 'profit', 'margin'];

    /** Item columns summed into the top-items totals row. */
    private const ITEM_SUM_KEYS = ['gross_qty', 'returned_qty', 'total_qty', 'gross_revenue', 'returned_value', 'total_revenue', 'total_cogs', 'profit'];

    /**
     * Helper to stream CSV with UTF-8 BOM for perfect Arabic display in Excel
     */
    protected function streamCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // Output UTF-8 BOM for Microsoft Excel Arabic support
            fwrite($handle, "\xEF\xBB\xBF");

            // IMP-02: CsvCell neutralises formula-looking text (an item named "=1+1", "=HYPERLINK(...)")
            // so Excel shows it as text, while real numbers stay numbers.
            fwrite($handle, CsvCell::row($headers));

            foreach ($rows as $row) {
                fwrite($handle, CsvCell::row($row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export customer ledger to CSV
     */
    public function exportCustomerStatement(Customer $customer): StreamedResponse
    {
        $headers = [
            __('common.date'),
            __('inventory.movement_type'),
            __('inventory.document_number'),
            __('contacts.statement_debit_col'),
            __('contacts.statement_credit_col'),
            __('contacts.statement_balance_col'),
            __('inventory.notes_and_statement'),
        ];

        $entries = collect();

        // 1. Invoices
        $invoices = Invoice::where('customer_id', $customer->id)->where('status', 'confirmed')->get();
        foreach ($invoices as $inv) {
            $entries->push([
                'date' => $inv->invoice_date->format('Y-m-d'),
                'type' => __('invoices.sales_invoice'),
                'ref' => $inv->invoice_number,
                'debit' => $inv->net_total,
                'credit' => '0.000',
                'notes' => $inv->notes ?? __('invoices.status_confirmed'),
                'timestamp' => $inv->created_at->timestamp,
            ]);
        }

        // 2. Payments
        $payments = Payment::where('customer_id', $customer->id)->excludingCancelledInvoices()->get();
        foreach ($payments as $pay) {
            // BE-10: a refund voucher (negative amount) is cash given back: a debit, same balance effect.
            $isRefund = bccomp((string) $pay->amount, '0.000', 3) < 0;
            $entries->push([
                'date' => $pay->payment_date->format('Y-m-d'),
                'type' => $isRefund ? __('treasury.customer_refund_label') : __('contacts.collect_cash_payment'),
                'ref' => $pay->payment_number,
                'debit' => $isRefund ? bcsub('0.000', (string) $pay->amount, 3) : '0.000',
                'credit' => $isRefund ? '0.000' : $pay->amount,
                'notes' => $pay->notes ?? ($isRefund ? __('treasury.customer_refund_label') : __('contacts.cash_receipt_voucher')),
                'timestamp' => $pay->created_at->timestamp,
            ]);
        }

        // 3. Returns
        $returns = ReturnDocument::where('customer_id', $customer->id)->where('return_type', 'sales_return')->get();
        foreach ($returns as $ret) {
            $entries->push([
                'date' => $ret->return_date->format('Y-m-d'),
                'type' => __('returns.sales_return'),
                'ref' => $ret->return_number,
                'debit' => '0.000',
                'credit' => $ret->total_amount,
                'notes' => $ret->reason ?? __('returns.returned_goods'),
                'timestamp' => $ret->created_at->timestamp,
            ]);
        }

        $sorted = $entries->sortBy('timestamp')->values();
        $runningBalance = '0.000';
        $rows = [];

        foreach ($sorted as $row) {
            $runningBalance = bcadd($runningBalance, $row['debit'], 3);
            $runningBalance = bcsub($runningBalance, $row['credit'], 3);

            $rows[] = [
                $row['date'],
                $row['type'],
                $row['ref'],
                number_format((float) $row['debit'], 2),
                number_format((float) $row['credit'], 2),
                number_format((float) $runningBalance, 2),
                $row['notes'],
            ];
        }

        $filename = "statement_{$customer->id}_".date('Y-m-d').'.csv';

        return $this->streamCsv($filename, $headers, $rows);
    }

    /**
     * Export supplier ledger to CSV
     */
    public function exportSupplierStatement(Supplier $supplier): StreamedResponse
    {
        $headers = [
            __('common.date'),
            __('inventory.movement_type'),
            __('inventory.document_number'),
            __('contacts.statement_debit_col'),
            __('contacts.statement_credit_col'),
            __('contacts.statement_balance_col'),
            __('inventory.notes_and_statement'),
        ];

        $entries = collect();

        $purchases = Purchase::where('supplier_id', $supplier->id)->where('status', 'confirmed')->get();
        foreach ($purchases as $pur) {
            $entries->push([
                'date' => $pur->purchase_date->format('Y-m-d'),
                'type' => __('purchases.title'),
                'ref' => $pur->purchase_number,
                'debit' => $pur->net_total,
                'credit' => '0.000',
                'notes' => $pur->notes ?? __('purchases.status_confirmed'),
                'timestamp' => $pur->created_at->timestamp,
            ]);
        }

        $payments = Payment::where('supplier_id', $supplier->id)->get();
        foreach ($payments as $pay) {
            $entries->push([
                'date' => $pay->payment_date->format('Y-m-d'),
                'type' => __('contacts.disburse_cash_payment'),
                'ref' => $pay->payment_number,
                'debit' => '0.000',
                'credit' => $pay->amount,
                'notes' => $pay->notes ?? __('contacts.cash_disbursement_voucher'),
                'timestamp' => $pay->created_at->timestamp,
            ]);
        }

        $returns = ReturnDocument::where('supplier_id', $supplier->id)->where('return_type', 'purchase_return')->get();
        foreach ($returns as $ret) {
            $entries->push([
                'date' => $ret->return_date->format('Y-m-d'),
                'type' => __('returns.purchase_return'),
                'ref' => $ret->return_number,
                'debit' => '0.000',
                'credit' => $ret->total_amount,
                'notes' => $ret->reason ?? __('returns.returned_goods'),
                'timestamp' => $ret->created_at->timestamp,
            ]);
        }

        $sorted = $entries->sortBy('timestamp')->values();
        $runningBalance = '0.000';
        $rows = [];

        foreach ($sorted as $row) {
            $runningBalance = bcadd($runningBalance, $row['debit'], 3);
            $runningBalance = bcsub($runningBalance, $row['credit'], 3);

            $rows[] = [
                $row['date'],
                $row['type'],
                $row['ref'],
                number_format((float) $row['debit'], 2),
                number_format((float) $row['credit'], 2),
                number_format((float) $runningBalance, 2),
                $row['notes'],
            ];
        }

        $filename = "supplier_statement_{$supplier->id}_".date('Y-m-d').'.csv';

        return $this->streamCsv($filename, $headers, $rows);
    }

    /**
     * Export warehouse inventory valuation to CSV
     */
    public function exportInventory(): StreamedResponse
    {
        $headers = [
            __('inventory.code'),
            __('inventory.item_name'),
            __('inventory.category'),
            __('inventory.unit'),
            __('inventory.current_stock'),
            __('inventory.average_cost'),
            __('inventory.selling_price'),
            __('reports.stock_cost_val_label'),
            __('reports.stock_sell_val_label'),
            __('reports.expected_profit_val'),
        ];

        $items = Item::active()->orderBy('category')->orderBy('name')->get();
        $rows = [];

        foreach ($items as $itm) {
            // BUG-02: valued at the carrying cost (weighted average), half-up like the report.
            $unitCost = CarryingCost::forItem($itm);
            $costVal = Decimal::mul((string) $itm->current_stock, $unitCost);
            $sellVal = Decimal::mul((string) $itm->current_stock, (string) $itm->selling_price);
            $profitExp = bcsub($sellVal, $costVal, 3);

            $rows[] = [
                $itm->code,
                $itm->name,
                $itm->category ?? __('inventory.general_category'),
                $itm->unit,
                number_format((float) $itm->current_stock, 3),
                number_format((float) $unitCost, 2),
                number_format((float) $itm->selling_price, 2),
                number_format((float) $costVal, 2),
                number_format((float) $sellVal, 2),
                number_format((float) $profitExp, 2),
            ];
        }

        $filename = 'inventory_valuation_'.date('Y-m-d').'.csv';

        return $this->streamCsv($filename, $headers, $rows);
    }

    /**
     * Export Item Movements (Stock Card) to CSV with full Arabic Excel support
     */
    public function exportItemMovements(
        Item $item,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $storeId = null,
        ?string $filterType = null
    ): StreamedResponse {
        $filename = "item_movements_{$item->id}_".date('Y-m-d').'.csv';
        $headers = [
            __('inventory.movement_date_time'),
            __('inventory.movement_type'),
            __('inventory.document_number'),
            __('common.store'),
            __('inventory.inbound_plus'),
            __('inventory.outbound_minus'),
            __('inventory.balance_after_movement'),
            __('common.user'),
            __('inventory.notes_and_statement'),
        ];

        $query = StockMovement::with(['user', 'store'])
            ->where('item_id', $item->id)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($fromDate, fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('created_at', '<=', $toDate))
            ->when($filterType === 'in', fn ($q) => $q->whereIn('movement_type', StockMovementTypes::INBOUND))
            ->when($filterType === 'out', fn ($q) => $q->whereIn('movement_type', StockMovementTypes::OUTBOUND))
            ->when($filterType === 'adjustments', fn ($q) => $q->whereIn('movement_type', StockMovementTypes::ADJUSTMENTS))
            ->oldest('created_at');

        $rows = [];
        foreach ($query->get() as $row) {
            $isIn = StockMovementTypes::isInbound((string) $row->movement_type);
            $typeLabel = match ($row->movement_type) {
                'sales_out' => __('invoices.sales_invoice'),
                'purchase_in' => __('purchases.title'),
                'purchase_cancel_out' => __('purchases.cancel_purchase'),
                'purchase_restore_in' => __('purchases.title'),
                'cancellation_in' => __('invoices.cancel_invoice'),
                'stock_adjustment_in' => __('inventory.movement_adj_in'),
                'stock_adjustment_out' => __('inventory.movement_adj_out'),
                'stock_deposit_in' => __('inventory.movement_deposit'),
                'opening_balance_in' => __('inventory.movement_opening_balance'),
                'transfer_in' => __('inventory.transfers_title'),
                'transfer_out' => __('inventory.transfers_title'),
                'sales_return_in' => __('returns.sales_return'),
                'stock_count_in' => __('stock_counts.movement_in'),
                'stock_count_out' => __('stock_counts.movement_out'),
                default => $row->movement_type,
            };

            $rows[] = [
                $row->created_at->format('Y-m-d H:i'),
                $typeLabel,
                $row->document_number ?: '—',
                $row->store?->name ?? __('common.main_store_default'),
                $isIn ? number_format((float) $row->quantity, 3) : '0.000',
                ! $isIn ? number_format((float) $row->quantity, 3) : '0.000',
                number_format((float) $row->stock_after, 3).' '.$item->unit,
                $row->user?->name ?? __('common.system'),
                $row->notes ?: '—',
            ];
        }

        return $this->streamCsv($filename, $headers, $rows);
    }

    /**
     * Export ABC Inventory Analysis and Dead Stock to Excel / CSV
     */
    public function exportAbcAnalysis(array $abcData, string $filename = 'abc-inventory-analysis.csv'): StreamedResponse
    {
        $headers = [
            'ABC Class',
            __('inventory.code'),
            __('inventory.item_name'),
            __('inventory.current_stock'),
            __('inventory.unit'),
            __('reports.daily_velocity'),
            __('reports.sold_quantity'),
            __('reports.total_revenue_egp'),
            __('reports.cogs_egp'),
            __('reports.gross_profit_egp'),
            __('reports.profit_margin_pct'),
            __('reports.profit_contribution_pct'),
        ];

        $rows = [];
        foreach ($abcData['items'] as $item) {
            $rows[] = [
                'Class '.$item['abc_class'],
                $item['code'],
                $item['name'],
                number_format((float) $item['current_stock'], 3),
                $item['unit'],
                number_format((float) $item['velocity'], 3),
                number_format((float) $item['quantity_sold'], 3),
                number_format((float) $item['revenue'], 2),
                number_format((float) $item['cogs'], 2),
                number_format((float) $item['gross_profit'], 2),
                $item['profit_margin'].'%',
                $item['profit_share'].'%',
            ];
        }

        return $this->streamCsv($filename, $headers, $rows);
    }

    /**
     * SHOP-05: one report table as CSV: a header row, one row per report row, and the totals row
     * LAST (the same figures the screen shows). $columns maps a row key to its header label.
     *
     * A null $totals writes no totals row (a label / value sheet such as the P&L summary).
     *
     * @param  array<string, string>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals
     */
    protected function exportReportTable(string $filename, array $columns, array $rows, ?array $totals): StreamedResponse
    {
        $keys = array_keys($columns);

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = array_map(fn (string $key): mixed => $row[$key] ?? '', $keys);
        }

        if ($totals !== null) {
            $totalsLine = array_map(fn (string $key): mixed => $totals[$key] ?? '', $keys);
            $totalsLine[0] = __('reports.csv_totals_row');
            $lines[] = $totalsLine;
        }

        return $this->streamCsv($filename, array_values($columns), $lines);
    }

    /**
     * @param  array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>}  $report
     */
    public function exportSalesByCashier(array $report): StreamedResponse
    {
        return $this->exportReportTable('sales_by_cashier_'.date('Y-m-d').'.csv', [
            'name' => __('reports.col_cashier'),
            'invoices_count' => __('reports.col_invoices_count'),
            'sales' => __('reports.col_sales'),
            'discounts' => __('reports.col_discounts'),
            'returns_count' => __('reports.col_returns_count'),
            'returns_value' => __('reports.col_returns_value'),
            'net_sales' => __('reports.col_net_sales'),
            'collected' => __('reports.col_collected'),
            'credit' => __('reports.col_credit'),
            'avg_invoice' => __('reports.col_avg_invoice'),
        ], $report['rows'], $report['totals']);
    }

    /**
     * @param  array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>}  $report
     */
    public function exportSalesByCategory(array $report): StreamedResponse
    {
        return $this->exportReportTable('sales_by_category_'.date('Y-m-d').'.csv', [
            'name' => __('reports.col_category'),
            'quantity' => __('reports.col_quantity'),
            'gross_sales' => __('reports.col_gross_sales'),
            'returns_qty' => __('reports.col_returns_qty'),
            'returns_value' => __('reports.col_returns_value'),
            'net_sales' => __('reports.col_net_sales'),
            'share_percent' => __('reports.col_share_percent'),
        ], $report['rows'], $report['totals']);
    }

    /**
     * @param  array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>}  $report
     */
    public function exportCustomerAging(array $report): StreamedResponse
    {
        return $this->exportReportTable('customer_aging_'.date('Y-m-d').'.csv', $this->agingColumns(__('reports.col_customer')), $report['rows'], $report['totals']);
    }

    /**
     * @param  array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>}  $report
     */
    public function exportSupplierPayables(array $report): StreamedResponse
    {
        return $this->exportReportTable('supplier_payables_'.date('Y-m-d').'.csv', $this->agingColumns(__('reports.col_supplier')), $report['rows'], $report['totals']);
    }

    /**
     * SHOP-05: the P&L summary as label / value rows (the report has no daily breakdown): the
     * period, then every figure of $summary in the screen's order. $summary is already passed
     * through ProfitVisibility::summary() (plan gate); without items.view_cost ($showCost false)
     * every cost or cost-derived figure (COGS, profit, margin, stock cost) is left out too.
     *
     * @param  array{from_date?: mixed, to_date?: mixed}  $period
     * @param  array<string, mixed>  $summary
     */
    public function exportProfitLossSummary(array $period, array $summary, bool $showCost): StreamedResponse
    {
        $rows = [
            ['label' => __('reports.csv_period_from'), 'value' => (string) ($period['from_date'] ?? '')],
            ['label' => __('reports.csv_period_to'), 'value' => (string) ($period['to_date'] ?? '')],
        ];

        foreach (self::SUMMARY_LABELS as $key => $label) {
            if (! array_key_exists($key, $summary) || (! $showCost && in_array($key, self::SUMMARY_COST_KEYS, true))) {
                continue;
            }
            $rows[] = ['label' => __($label), 'value' => (string) $summary[$key]];
        }

        return $this->exportReportTable('profit_loss_summary_'.date('Y-m-d').'.csv', [
            'label' => __('reports.csv_metric'),
            'value' => __('reports.csv_value'),
        ], $rows, null);
    }

    /**
     * SHOP-05: item profitability (every row of the period, not one page) with the report's
     * totals of every item last. Rows and totals are already passed through ProfitVisibility.
     *
     * @param  array{rows: list<array<string, mixed>>, totals: array<string, mixed>}  $report
     */
    public function exportItemsProfitability(array $report, bool $showCost): StreamedResponse
    {
        return $this->exportReportTable(
            'items_profitability_'.date('Y-m-d').'.csv',
            $this->itemColumns($report['totals'], $showCost),
            $report['rows'],
            $report['totals'],
        );
    }

    /**
     * SHOP-05: the top-selling items (the rows /reports/top-items returns). The totals row is
     * the sum of THESE rows (the margin recomputed from their sums), not of every item sold.
     *
     * @param  list<array<string, mixed>>  $rows  already passed through ProfitVisibility::itemRows()
     */
    public function exportTopItems(array $rows, bool $showCost): StreamedResponse
    {
        $totals = array_fill_keys(self::ITEM_SUM_KEYS, '0.000');
        foreach ($rows as $row) {
            foreach (self::ITEM_SUM_KEYS as $key) {
                if (array_key_exists($key, $row)) {
                    $totals[$key] = bcadd($totals[$key], (string) $row[$key], 3);
                }
            }
        }

        // The plan gate strips the same keys from the totals as from every row.
        $totals = ProfitVisibility::itemRow($totals);
        if (array_key_exists('profit', $totals)) {
            $totals['margin'] = bccomp($totals['total_revenue'], '0.000', 3) > 0
                ? Decimal::normalize(bcmul(bcdiv($totals['profit'], $totals['total_revenue'], 4), '100', 1), 1)
                : '0.0';
        }

        return $this->exportReportTable('top_items_'.date('Y-m-d').'.csv', $this->itemColumns($totals, $showCost), $rows, $totals);
    }

    /**
     * The item profitability columns present in $sample (ProfitVisibility removed the plan-gated
     * ones), minus the cost / profit columns for a user without items.view_cost.
     *
     * @param  array<string, mixed>  $sample  the totals row (holds every column the rows hold)
     * @return array<string, string>
     */
    private function itemColumns(array $sample, bool $showCost): array
    {
        $columns = [];
        foreach (self::ITEM_COLUMNS as $key => $label) {
            $isNumeric = ! in_array($key, ['name', 'code', 'category', 'unit'], true);
            if ($isNumeric && ! array_key_exists($key, $sample)) {
                continue;
            }
            if (! $showCost && in_array($key, self::ITEM_COST_KEYS, true)) {
                continue;
            }
            $columns[$key] = __($label);
        }

        return $columns;
    }

    /**
     * @return array<string, string>
     */
    private function agingColumns(string $partyLabel): array
    {
        return [
            'name' => $partyLabel,
            'phone' => __('reports.col_phone'),
            'bucket_0_30' => __('reports.aging_0_30'),
            'bucket_31_60' => __('reports.aging_31_60'),
            'bucket_61_90' => __('reports.aging_61_90'),
            'bucket_90_plus' => __('reports.aging_90_plus'),
            'unallocated' => __('reports.aging_unallocated'),
            'total' => __('reports.aging_total'),
        ];
    }
}
