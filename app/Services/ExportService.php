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
        $headers = ['التاريخ', 'نوع الحركة', 'رقم السند / الفاتورة', 'المستحق على العميل (مدين)', 'المسدد من العميل (دائن)', 'الرصيد بعد الحركة', 'البيان والملاحظات'];

        $entries = collect();

        // 1. Invoices
        $invoices = Invoice::where('customer_id', $customer->id)->where('status', 'confirmed')->get();
        foreach ($invoices as $inv) {
            $entries->push([
                'date'       => $inv->invoice_date->format('Y-m-d'),
                'type'       => 'فاتورة مبيعات',
                'ref'        => $inv->invoice_number,
                'debit'      => $inv->net_total,
                'credit'     => '0.000',
                'notes'      => $inv->notes ?? 'فاتورة مبيعات معتمدة',
                'timestamp'  => $inv->created_at->timestamp,
            ]);
        }

        // 2. Payments
        $payments = Payment::where('customer_id', $customer->id)->get();
        foreach ($payments as $pay) {
            $entries->push([
                'date'       => $pay->payment_date->format('Y-m-d'),
                'type'       => 'سند تحصيل نقدي',
                'ref'        => $pay->payment_number,
                'debit'      => '0.000',
                'credit'     => $pay->amount,
                'notes'      => $pay->notes ?? 'دفعة نقدية مسددة',
                'timestamp'  => $pay->created_at->timestamp,
            ]);
        }

        // 3. Returns
        $returns = ReturnDocument::where('customer_id', $customer->id)->where('return_type', 'sales_return')->get();
        foreach ($returns as $ret) {
            $entries->push([
                'date'       => $ret->return_date->format('Y-m-d'),
                'type'       => 'مرتجع مبيعات',
                'ref'        => $ret->return_number,
                'debit'      => '0.000',
                'credit'     => $ret->total_amount,
                'notes'      => $ret->reason ?? 'بضاعة مرتجعة',
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

        $filename = "كشف_حساب_عميل_{$customer->name}_" . date('Y-m-d') . ".csv";
        return $this->streamCsv($filename, $headers, $rows);
    }

    /**
     * Export supplier ledger to CSV
     */
    public function exportSupplierStatement(Supplier $supplier): StreamedResponse
    {
        $headers = ['التاريخ', 'نوع الحركة', 'رقم الفاتورة / السند', 'مستحق للمورد (توريد)', 'سداد للمورد (صرف)', 'الرصيد بعد الحركة', 'الملاحظات'];

        $entries = collect();

        $purchases = Purchase::where('supplier_id', $supplier->id)->where('status', 'confirmed')->get();
        foreach ($purchases as $pur) {
            $entries->push([
                'date'       => $pur->purchase_date->format('Y-m-d'),
                'type'       => 'فاتورة توريد وشراء',
                'ref'        => $pur->purchase_number,
                'debit'      => $pur->net_total,
                'credit'     => '0.000',
                'notes'      => $pur->notes ?? 'توريد بضاعة للمخزن',
                'timestamp'  => $pur->created_at->timestamp,
            ]);
        }

        $payments = Payment::where('supplier_id', $supplier->id)->get();
        foreach ($payments as $pay) {
            $entries->push([
                'date'       => $pay->payment_date->format('Y-m-d'),
                'type'       => 'سند صرف نقدي',
                'ref'        => $pay->payment_number,
                'debit'      => '0.000',
                'credit'     => $pay->amount,
                'notes'      => $pay->notes ?? 'سداد مستحقات المورد',
                'timestamp'  => $pay->created_at->timestamp,
            ]);
        }

        $returns = ReturnDocument::where('supplier_id', $supplier->id)->where('return_type', 'purchase_return')->get();
        foreach ($returns as $ret) {
            $entries->push([
                'date'       => $ret->return_date->format('Y-m-d'),
                'type'       => 'مرتجع مشتريات',
                'ref'        => $ret->return_number,
                'debit'      => '0.000',
                'credit'     => $ret->total_amount,
                'notes'      => $ret->reason ?? 'مرتجع للمورد',
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

        $filename = "كشف_حساب_مورد_{$supplier->name}_" . date('Y-m-d') . ".csv";
        return $this->streamCsv($filename, $headers, $rows);
    }

    /**
     * Export warehouse inventory valuation to CSV
     */
    public function exportInventory(): StreamedResponse
    {
        $headers = ['كود الصنف', 'اسم الصنف', 'القسم', 'الوحدة', 'الرصيد الحالي بالمخزن', 'سعر التكلفة للوحدة', 'سعر البيع للوحدة', 'إجمالي قيمة المخزون بالتكلفة', 'إجمالي قيمة المخزون بالبيع', 'الربح المتوقع'];

        $items = Item::active()->orderBy('category')->orderBy('name')->get();
        $rows = [];

        foreach ($items as $itm) {
            $unitCost = $itm->effectiveCost();
            $costVal = bcmul((string)$itm->current_stock, $unitCost, 3);
            $sellVal = bcmul($itm->current_stock, $itm->selling_price, 3);
            $profitExp = bcsub($sellVal, $costVal, 3);

            $rows[] = [
                $itm->code,
                $itm->name,
                $itm->category ?? 'عام',
                $itm->unit,
                number_format((float)$itm->current_stock, 3),
                number_format((float)$unitCost, 2),
                number_format((float)$itm->selling_price, 2),
                number_format((float)$costVal, 2),
                number_format((float)$sellVal, 2),
                number_format((float)$profitExp, 2),
            ];
        }

        $filename = "جرد_وتقييم_المخزون_" . date('Y-m-d') . ".csv";
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
        $filename = "حركة_صنف_{$item->name}_" . date('Y-m-d') . ".csv";
        $headers = [
            'التاريخ والوقت',
            'نوع الحركة',
            'رقم المستند',
            'الفرع / المخزن',
            'الوارد (+)',
            'المنصرف (-)',
            'الرصيد بعد الحركة',
            'المسؤول',
            'البيان والملاحظات'
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
                'sales_out'            => 'فاتورة بيع',
                'purchase_in'          => 'توريد مشتريات',
                'purchase_cancel_out'  => 'إلغاء فاتورة شراء',
                'purchase_restore_in'  => 'استعادة فاتورة شراء',
                'cancellation_in'      => 'إلغاء فاتورة بيع',
                'stock_adjustment_in'  => 'تسوية جرد (زيادة +)',
                'stock_adjustment_out' => 'تسوية جرد (عجز/هالك -)',
                'stock_deposit_in'     => 'إيداع / رصيد افتتاحي',
                'transfer_in'          => 'تحويل وارد',
                'transfer_out'         => 'تحويل صادر',
                'sales_return_in'      => 'مرتجع مبيعات',
                default                => $row->movement_type,
            };

            $rows[] = [
                $row->created_at->format('Y-m-d H:i'),
                $typeLabel,
                $row->document_number ?: '—',
                $row->store?->name ?? 'المخزن الرئيسي',
                $isIn ? number_format((float)$row->quantity, 3) : '0.000',
                !$isIn ? number_format((float)$row->quantity, 3) : '0.000',
                number_format((float)$row->stock_after, 3) . ' ' . $item->unit,
                $row->user?->name ?? 'النظام',
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
            'تصنيف ABC',
            'كود الصنف',
            'اسم الصنف',
            'الرصيد الحالي بالمخزن',
            'الوحدة',
            'معدل السحب اليومي',
            'الكمية المباعة في الفترة',
            'إجمالي الإيراد (ج.م)',
            'تكلفة البضاعة المباعة (ج.م)',
            'مجمل الربح (ج.م)',
            'هامش الربح %',
            'نسبة المساهمة في الأرباح %',
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
