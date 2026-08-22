<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Item;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;

class ExportService
{
    /**
     * Helper to stream CSV with UTF-8 BOM for perfect Arabic display in Excel
     */
    protected function streamCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            
            // Output UTF-8 BOM for Microsoft Excel Arabic support
            fputs($handle, "\xEF\xBB\xBF");
            
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
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
            __('inventory.notes_and_statement')
        ];

        $entries = collect();

        // 1. Invoices
        $invoices = Invoice::where('customer_id', $customer->id)->where('status', 'confirmed')->get();
        foreach ($invoices as $inv) {
            $entries->push([
                'date'       => $inv->invoice_date->format('Y-m-d'),
                'type'       => __('invoices.sales_invoice'),
                'ref'        => $inv->invoice_number,
                'debit'      => $inv->net_total,
                'credit'     => '0.000',
                'notes'      => $inv->notes ?? __('invoices.status_confirmed'),
                'timestamp'  => $inv->created_at->timestamp,
            ]);
        }

        // 2. Payments
        $payments = Payment::where('customer_id', $customer->id)->get();
        foreach ($payments as $pay) {
            $entries->push([
                'date'       => $pay->payment_date->format('Y-m-d'),
                'type'       => __('contacts.collect_cash_payment'),
                'ref'        => $pay->payment_number,
                'debit'      => '0.000',
                'credit'     => $pay->amount,
                'notes'      => $pay->notes ?? __('contacts.cash_receipt_voucher'),
                'timestamp'  => $pay->created_at->timestamp,
            ]);
        }

        // 3. Returns
        $returns = ReturnDocument::where('customer_id', $customer->id)->where('return_type', 'sales_return')->get();
        foreach ($returns as $ret) {
            $entries->push([
                'date'       => $ret->return_date->format('Y-m-d'),
                'type'       => __('returns.sales_return'),
                'ref'        => $ret->return_number,
                'debit'      => '0.000',
                'credit'     => $ret->total_amount,
                'notes'      => $ret->reason ?? __('returns.returned_goods'),
                'timestamp'  => $ret->created_at->timestamp,
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
                number_format((float)$row['debit'], 2),
                number_format((float)$row['credit'], 2),
                number_format((float)$runningBalance, 2),
                $row['notes'],
            ];
        }

        $filename = "statement_{$customer->id}_" . date('Y-m-d') . ".csv";
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
            __('inventory.notes_and_statement')
        ];

        $entries = collect();

        $purchases = Purchase::where('supplier_id', $supplier->id)->where('status', 'confirmed')->get();
        foreach ($purchases as $pur) {
            $entries->push([
                'date'       => $pur->purchase_date->format('Y-m-d'),
                'type'       => __('purchases.title'),
                'ref'        => $pur->purchase_number,
                'debit'      => $pur->net_total,
                'credit'     => '0.000',
                'notes'      => $pur->notes ?? __('purchases.status_confirmed'),
                'timestamp'  => $pur->created_at->timestamp,
            ]);
        }

        $payments = Payment::where('supplier_id', $supplier->id)->get();
        foreach ($payments as $pay) {
            $entries->push([
                'date'       => $pay->payment_date->format('Y-m-d'),
                'type'       => __('contacts.disburse_cash_payment'),
                'ref'        => $pay->payment_number,
                'debit'      => '0.000',
                'credit'     => $pay->amount,
                'notes'      => $pay->notes ?? __('contacts.cash_disbursement_voucher'),
                'timestamp'  => $pay->created_at->timestamp,
            ]);
        }

        $returns = ReturnDocument::where('supplier_id', $supplier->id)->where('return_type', 'purchase_return')->get();
        foreach ($returns as $ret) {
            $entries->push([
                'date'       => $ret->return_date->format('Y-m-d'),
                'type'       => __('returns.purchase_return'),
                'ref'        => $ret->return_number,
                'debit'      => '0.000',
                'credit'     => $ret->total_amount,
                'notes'      => $ret->reason ?? __('returns.returned_goods'),
                'timestamp'  => $ret->created_at->timestamp,
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
                number_format((float)$row['debit'], 2),
                number_format((float)$row['credit'], 2),
                number_format((float)$runningBalance, 2),
                $row['notes'],
            ];
        }

        $filename = "supplier_statement_{$supplier->id}_" . date('Y-m-d') . ".csv";
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
            __('inventory.cost_price'),
            __('inventory.selling_price'),
            __('reports.stock_cost_val_label'),
            __('reports.stock_sell_val_label'),
            __('reports.expected_profit_val')
        ];

        $items = Item::active()->orderBy('category')->orderBy('name')->get();
        $rows = [];

        foreach ($items as $itm) {
            $costVal = bcmul($itm->current_stock, $itm->cost_price, 3);
            $sellVal = bcmul($itm->current_stock, $itm->selling_price, 3);
            $profitExp = bcsub($sellVal, $costVal, 3);

            $rows[] = [
                $itm->code,
                $itm->name,
                $itm->category ?? __('inventory.general_category'),
                $itm->unit,
                number_format((float)$itm->current_stock, 3),
                number_format((float)$itm->cost_price, 2),
                number_format((float)$itm->selling_price, 2),
                number_format((float)$costVal, 2),
                number_format((float)$sellVal, 2),
                number_format((float)$profitExp, 2),
            ];
        }

        $filename = "inventory_valuation_" . date('Y-m-d') . ".csv";
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
        $filename = "item_movements_{$item->id}_" . date('Y-m-d') . ".csv";
        $headers = [
            __('inventory.movement_date_time'),
            __('inventory.movement_type'),
            __('inventory.document_number'),
            __('common.store'),
            __('inventory.inbound_plus'),
            __('inventory.outbound_minus'),
            __('inventory.balance_after_movement'),
            __('common.user'),
            __('inventory.notes_and_statement')
        ];

        $inTypes = [
            'purchase_in', 'stock_deposit_in', 'stock_adjustment_in',
            'cancellation_in', 'transfer_in', 'sales_return_in', 'purchase_restore_in'
        ];

        $outTypes = [
            'sales_out', 'waste_out', 'stock_adjustment_out',
            'transfer_out', 'purchase_cancel_out', 'purchase_return_out'
        ];

        $adjTypes = ['stock_adjustment_in', 'stock_adjustment_out', 'stock_deposit_in'];

        $query = \App\Models\StockMovement::with(['user', 'store'])
            ->where('item_id', $item->id)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('created_at', '<=', $toDate))
            ->when($filterType === 'in', fn($q) => $q->whereIn('movement_type', $inTypes))
            ->when($filterType === 'out', fn($q) => $q->whereIn('movement_type', $outTypes))
            ->when($filterType === 'adjustments', fn($q) => $q->whereIn('movement_type', $adjTypes))
            ->oldest('created_at');

        $rows = [];
        foreach ($query->get() as $row) {
            $isIn = in_array($row->movement_type, $inTypes);
            $typeLabel = match ($row->movement_type) {
                'sales_out'            => __('invoices.sales_invoice'),
                'purchase_in'          => __('purchases.title'),
                'purchase_cancel_out'  => __('purchases.cancel_purchase'),
                'purchase_restore_in'  => __('purchases.title'),
                'cancellation_in'      => __('invoices.cancel_invoice'),
                'stock_adjustment_in'  => __('inventory.movement_adj_in'),
                'stock_adjustment_out' => __('inventory.movement_adj_out'),
                'stock_deposit_in'     => __('inventory.movement_deposit'),
                'transfer_in'          => __('inventory.transfers_title'),
                'transfer_out'         => __('inventory.transfers_title'),
                'sales_return_in'      => __('returns.sales_return'),
                default                => $row->movement_type,
            };

            $rows[] = [
                $row->created_at->format('Y-m-d H:i'),
                $typeLabel,
                $row->document_number ?: '—',
                $row->store?->name ?? __('common.main_store_default'),
                $isIn ? number_format((float)$row->quantity, 3) : '0.000',
                !$isIn ? number_format((float)$row->quantity, 3) : '0.000',
                number_format((float)$row->stock_after, 3) . ' ' . $item->unit,
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
                'Class ' . $item['abc_class'],
                $item['code'],
                $item['name'],
                number_format((float)$item['current_stock'], 3),
                $item['unit'],
                number_format((float)$item['velocity'], 3),
                number_format((float)$item['quantity_sold'], 3),
                number_format((float)$item['revenue'], 2),
                number_format((float)$item['cogs'], 2),
                number_format((float)$item['gross_profit'], 2),
                $item['profit_margin'] . '%',
                $item['profit_share'] . '%',
            ];
        }

        return $this->streamCsv($filename, $headers, $rows);
    }
}
