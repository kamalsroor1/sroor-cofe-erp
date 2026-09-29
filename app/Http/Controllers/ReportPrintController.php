<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Expense;
use App\Models\Store;
use App\Models\Customer;
use App\Services\ProfitService;
use Illuminate\Support\Facades\DB;

class ReportPrintController extends Controller
{
    public function printReport(Request $request, ProfitService $profitService)
    {
        $tab = $request->query('tab', 'sales');
        $storeId = ($request->query('store_id') && $request->query('store_id') !== 'all') ? (int)$request->query('store_id') : null;
        $fromDate = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $toDate = $request->query('to') ?: now()->toDateString();

        $storeName = 'كافة الفروع والمخازن';
        if ($storeId) {
            $st = Store::find($storeId);
            if ($st) $storeName = $st->name;
        }

        return match ($tab) {
            'items'     => $this->printItemsReport($fromDate, $toDate, $storeId, $storeName),
            'stores'    => $this->printStoresReport($fromDate, $toDate, $storeName),
            'customers' => $this->printCustomersReport($fromDate, $toDate, $storeId, $storeName),
            'expenses'  => $this->printExpensesReport($fromDate, $toDate, $storeId, $storeName),
            'inventory' => $this->printInventoryReport($storeId, $storeName),
            'treasury'  => $this->printTreasuryReport($fromDate, $toDate, $storeId, $storeName, $request->query('method', 'all')),
            'abc'       => $this->printAbcReport($fromDate, $toDate, $storeId, $storeName),
            'pnl'       => $this->printProfitLossReport($fromDate, $toDate, $storeId, $storeName),
            default     => $this->printSalesReport($fromDate, $toDate, $storeId, $storeName, $profitService),
        };
    }

    protected function printSalesReport($fromDate, $toDate, $storeId, $storeName, ProfitService $profitService)
    {
        $reportTitle = 'تقرير المبيعات والأرباح التفصيلي';

        $invoices = Invoice::with(['customer', 'store'])
            ->where('status', 'confirmed')
            ->when($fromDate, fn($q) => $q->whereDate('invoice_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('invoice_date', '<=', $toDate))
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('invoice_date')
            ->get();

        $totalSales = '0.000';
        $totalCost = '0.000';
        $totalPaid = '0.000';
        $totalRemaining = '0.000';

        foreach ($invoices as $inv) {
            $totalSales = bcadd($totalSales, (string)$inv->net_total, 3);
            $totalCost = bcadd($totalCost, (string)$inv->total_cost, 3);
            $totalPaid = bcadd($totalPaid, (string)$inv->paid_amount, 3);
            $totalRemaining = bcadd($totalRemaining, (string)$inv->remaining_amount, 3);
        }

        $grossProfit = bcsub($totalSales, $totalCost, 3);
        $expenses = Expense::when($fromDate, fn($q) => $q->whereDate('expense_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('expense_date', '<=', $toDate))
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->sum('amount') ?: '0.000';

        $netProfit = bcsub($grossProfit, (string)$expenses, 3);

        $kpis = [
            ['label' => 'إجمالي المبيعات', 'value' => number_format((float)$totalSales, 2) . ' ج.م', 'class' => 'text-slate-950'],
            ['label' => 'إجمالي التكلفة', 'value' => number_format((float)$totalCost, 2) . ' ج.م', 'class' => 'text-slate-600'],
            ['label' => 'مجمل الربح', 'value' => number_format((float)$grossProfit, 2) . ' ج.م', 'class' => 'text-emerald-700'],
            ['label' => 'المصروفات التشغيلية', 'value' => number_format((float)$expenses, 2) . ' ج.م', 'class' => 'text-rose-700'],
            ['label' => 'صافي الأرباح', 'value' => number_format((float)$netProfit, 2) . ' ج.م', 'class' => 'text-indigo-800 font-bold'],
        ];

        $tableHeaders = [
            ['title' => 'رقم الفاتورة'],
            ['title' => 'التاريخ'],
            ['title' => 'العميل'],
            ['title' => 'الفرع'],
            ['title' => 'طريقة السداد'],
            ['title' => 'الإجمالي المطلوب'],
            ['title' => 'المدفوع'],
            ['title' => 'المتبقي'],
            ['title' => 'الربح المحقق'],
        ];

        $tableRows = [];
        foreach ($invoices as $inv) {
            $profit = bcsub((string)$inv->net_total, (string)$inv->total_cost, 3);
            $pm = $inv->payment_method ?? 'cash';
            $pmLabel = match ($pm) {
                'instapay' => '⚡ إنستاباي',
                'e_wallet' => '📲 محفظة',
                'visa'     => '💳 فيزا',
                default    => '💵 كاش',
            };

            $tableRows[] = [
                ['value' => $inv->invoice_number, 'class' => 'font-mono font-bold'],
                ['value' => $inv->invoice_date->format('Y-m-d'), 'class' => 'font-mono'],
                ['value' => $inv->customer?->name ?? 'عميل نقدي'],
                ['value' => $inv->store?->name ?? '—'],
                ['value' => $pmLabel],
                ['value' => number_format((float)$inv->net_total, 2) . ' ج.م', 'class' => 'font-mono font-bold'],
                ['value' => number_format((float)$inv->paid_amount, 2), 'class' => 'font-mono text-emerald-700'],
                ['value' => number_format((float)$inv->remaining_amount, 2), 'class' => 'font-mono ' . (bccomp($inv->remaining_amount, '0.000', 3) > 0 ? 'text-rose-700 font-bold' : '')],
                ['value' => number_format((float)$profit, 2) . ' ج.م', 'class' => 'font-mono font-bold text-emerald-700'],
            ];
        }

        $tableTotals = [
            ['value' => 'الإجمالي (' . count($invoices) . ' فاتورة)'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => number_format((float)$totalSales, 2) . ' ج.م', 'class' => 'font-mono font-bold'],
            ['value' => number_format((float)$totalPaid, 2), 'class' => 'font-mono'],
            ['value' => number_format((float)$totalRemaining, 2), 'class' => 'font-mono text-rose-700 font-bold'],
            ['value' => number_format((float)$grossProfit, 2) . ' ج.م', 'class' => 'font-mono font-bold text-emerald-700'],
        ];

        return view('layouts.print-report-a4', compact(
            'reportTitle', 'storeName', 'fromDate', 'toDate', 'kpis', 'tableHeaders', 'tableRows', 'tableTotals'
        ));
    }

    protected function printItemsReport($fromDate, $toDate, $storeId, $storeName)
    {
        $reportTitle = 'تقرير حركة وربحية الأصناف ومبيعات التوليفات';

        $itemsData = InvoiceItem::whereHas('invoice', function ($q) use ($fromDate, $toDate, $storeId) {
            $q->where('status', 'confirmed')
              ->when($fromDate, fn($sub) => $sub->whereDate('invoice_date', '>=', $fromDate))
              ->when($toDate, fn($sub) => $sub->whereDate('invoice_date', '<=', $toDate))
              ->when($storeId, fn($sub) => $sub->where('store_id', $storeId));
        })
        ->select(
            'item_id',
            DB::raw('SUM(quantity) as total_quantity'),
            DB::raw('SUM(total_price) as total_revenue'),
            DB::raw('SUM(cost_price * quantity) as total_cost_amount')
        )
        ->groupBy('item_id')
        ->with('item')
        ->get();

        $kpis = [
            ['label' => 'عدد الأصناف المباعة', 'value' => count($itemsData) . ' صنف'],
            ['label' => 'إجمالي الإيرادات', 'value' => number_format((float)$itemsData->sum('total_revenue'), 2) . ' ج.م', 'class' => 'text-slate-950'],
            ['label' => 'إجمالي التكلفة', 'value' => number_format((float)$itemsData->sum('total_cost_amount'), 2) . ' ج.م', 'class' => 'text-slate-600'],
            ['label' => 'إجمالي الأرباح المحققة', 'value' => number_format((float)($itemsData->sum('total_revenue') - $itemsData->sum('total_cost_amount')), 2) . ' ج.م', 'class' => 'text-emerald-700 font-bold'],
        ];

        $tableHeaders = [
            ['title' => 'كود الصنف'],
            ['title' => 'اسم الصنف'],
            ['title' => 'القسم'],
            ['title' => 'الكمية المباعة'],
            ['title' => 'إجمالي المبيعات'],
            ['title' => 'إجمالي التكلفة'],
            ['title' => 'الربح المحقق'],
            ['title' => 'هامش الربح'],
        ];

        $tableRows = [];
        foreach ($itemsData as $row) {
            $itm = $row->item;
            $rev = (string)$row->total_revenue;
            $cost = (string)$row->total_cost_amount;
            $profit = bcsub($rev, $cost, 3);
            $margin = bccomp($rev, '0.000', 3) > 0 ? bcmul(bcdiv($profit, $rev, 4), '100', 1) . '%' : '0%';

            $tableRows[] = [
                ['value' => $itm?->code ?? '—', 'class' => 'font-mono'],
                ['value' => $itm?->name ?? 'صنف محذوف', 'class' => 'font-bold'],
                ['value' => $itm?->category ?? 'عام'],
                ['value' => number_format((float)$row->total_quantity, 3) . ' ' . ($itm?->unit ?? 'كجم'), 'class' => 'font-mono font-bold'],
                ['value' => number_format((float)$rev, 2) . ' ج.م', 'class' => 'font-mono font-bold'],
                ['value' => number_format((float)$cost, 2) . ' ج.م', 'class' => 'font-mono text-slate-600'],
                ['value' => number_format((float)$profit, 2) . ' ج.م', 'class' => 'font-mono font-bold text-emerald-700'],
                ['value' => $margin, 'class' => 'font-mono font-bold text-indigo-700'],
            ];
        }

        return view('layouts.print-report-a4', compact(
            'reportTitle', 'storeName', 'fromDate', 'toDate', 'kpis', 'tableHeaders', 'tableRows'
        ));
    }

    protected function printStoresReport($fromDate, $toDate, $storeName)
    {
        $reportTitle = 'تقرير مقارنة أداء الفروع وعربات التوزيع';

        $stores = Store::active()->get();
        $tableHeaders = [
            ['title' => 'الفرع / نقطة البيع'],
            ['title' => 'النوع'],
            ['title' => 'عدد الفواتير'],
            ['title' => 'إجمالي المبيعات'],
            ['title' => 'المحصل نقداً'],
            ['title' => 'المبيعات الآجلة'],
            ['title' => 'الأرباح المحققة'],
        ];

        $tableRows = [];
        $totalSalesAll = '0.000';
        $totalProfitsAll = '0.000';

        foreach ($stores as $st) {
            $invoices = Invoice::where('store_id', $st->id)
                ->where('status', 'confirmed')
                ->when($fromDate, fn($q) => $q->whereDate('invoice_date', '>=', $fromDate))
                ->when($toDate, fn($q) => $q->whereDate('invoice_date', '<=', $toDate))
                ->get();

            $sales = (string)($invoices->sum('net_total') ?: '0.000');
            $cost = (string)($invoices->sum('total_cost') ?: '0.000');
            $paid = (string)($invoices->sum('paid_amount') ?: '0.000');
            $rem = (string)($invoices->sum('remaining_amount') ?: '0.000');
            $profit = bcsub($sales, $cost, 3);

            $totalSalesAll = bcadd($totalSalesAll, $sales, 3);
            $totalProfitsAll = bcadd($totalProfitsAll, $profit, 3);

            $tableRows[] = [
                ['value' => $st->name, 'class' => 'font-bold'],
                ['value' => $st->type === 'wholesale_van' ? '🚚 عربة توزيع' : ($st->is_main ? '🏢 رئيسي' : '🏬 فرع')],
                ['value' => $invoices->count() . ' فاتورة', 'class' => 'font-mono'],
                ['value' => number_format((float)$sales, 2) . ' ج.م', 'class' => 'font-mono font-bold'],
                ['value' => number_format((float)$paid, 2), 'class' => 'font-mono text-emerald-700'],
                ['value' => number_format((float)$rem, 2), 'class' => 'font-mono text-rose-700'],
                ['value' => number_format((float)$profit, 2) . ' ج.م', 'class' => 'font-mono font-bold text-emerald-700'],
            ];
        }

        $kpis = [
            ['label' => 'عدد الفروع والعربيات', 'value' => $stores->count() . ' فرع / سيارة'],
            ['label' => 'إجمالي مبيعات الفروع', 'value' => number_format((float)$totalSalesAll, 2) . ' ج.م', 'class' => 'text-slate-950'],
            ['label' => 'إجمالي أرباح الفروع', 'value' => number_format((float)$totalProfitsAll, 2) . ' ج.م', 'class' => 'text-emerald-700 font-bold'],
        ];

        return view('layouts.print-report-a4', compact(
            'reportTitle', 'storeName', 'fromDate', 'toDate', 'kpis', 'tableHeaders', 'tableRows'
        ));
    }

    protected function printCustomersReport($fromDate, $toDate, $storeId, $storeName)
    {
        $reportTitle = 'تقرير مبيعات وحسابات العملاء';

        $customers = Customer::active()->withCount(['invoices as period_invoices_count' => function ($q) use ($fromDate, $toDate, $storeId) {
            $q->where('status', 'confirmed')
              ->when($fromDate, fn($sub) => $sub->whereDate('invoice_date', '>=', $fromDate))
              ->when($toDate, fn($sub) => $sub->whereDate('invoice_date', '<=', $toDate))
              ->when($storeId, fn($sub) => $sub->where('store_id', $storeId));
        }])->get();

        $tableHeaders = [
            ['title' => 'اسم العميل'],
            ['title' => 'الهاتف'],
            ['title' => 'عدد الفواتير'],
            ['title' => 'الرصيد / المديونية الحالية'],
        ];

        $tableRows = [];
        foreach ($customers as $c) {
            if ($c->period_invoices_count > 0 || bccomp($c->current_balance, '0.000', 3) > 0) {
                $tableRows[] = [
                    ['value' => $c->name, 'class' => 'font-bold'],
                    ['value' => $c->phone ?: '—', 'class' => 'font-mono'],
                    ['value' => $c->period_invoices_count . ' فاتورة', 'class' => 'font-mono'],
                    ['value' => number_format((float)$c->current_balance, 2) . ' ج.م', 'class' => 'font-mono font-bold ' . (bccomp($c->current_balance, '0.000', 3) > 0 ? 'text-rose-700' : 'text-slate-700')],
                ];
            }
        }

        $kpis = [
            ['label' => 'إجمالي العملاء النشطين', 'value' => count($tableRows) . ' عميل'],
            ['label' => 'إجمالي المديونيات المستحقة', 'value' => number_format((float)$customers->sum('current_balance'), 2) . ' ج.م', 'class' => 'text-rose-700 font-bold'],
        ];

        return view('layouts.print-report-a4', compact(
            'reportTitle', 'storeName', 'fromDate', 'toDate', 'kpis', 'tableHeaders', 'tableRows'
        ));
    }

    protected function printExpensesReport($fromDate, $toDate, $storeId, $storeName)
    {
        $reportTitle = 'تقرير المصروفات والنثريات وتكلفة التشغيل';

        $expenses = Expense::with(['user', 'store'])
            ->when($fromDate, fn($q) => $q->whereDate('expense_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('expense_date', '<=', $toDate))
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('expense_date')
            ->get();

        $totalExpenses = (string)($expenses->sum('amount') ?: '0.000');

        $kpis = [
            ['label' => 'عدد بنود المصروفات', 'value' => $expenses->count() . ' سند'],
            ['label' => 'إجمالي المصروفات', 'value' => number_format((float)$totalExpenses, 2) . ' ج.م', 'class' => 'text-rose-700 font-bold'],
        ];

        $tableHeaders = [
            ['title' => 'رقم السند'],
            ['title' => 'التاريخ'],
            ['title' => 'التصنيف'],
            ['title' => 'بيان الصرف'],
            ['title' => 'المبلغ المسدد'],
            ['title' => 'المسؤول'],
        ];

        $tableRows = [];
        foreach ($expenses as $exp) {
            $tableRows[] = [
                ['value' => $exp->expense_number, 'class' => 'font-mono font-bold'],
                ['value' => $exp->expense_date->format('Y-m-d'), 'class' => 'font-mono'],
                ['value' => $exp->category],
                ['value' => $exp->title, 'class' => 'font-bold'],
                ['value' => number_format((float)$exp->amount, 2) . ' ج.م', 'class' => 'font-mono font-bold text-rose-700'],
                ['value' => $exp->user?->name ?? '—'],
            ];
        }

        $tableTotals = [
            ['value' => 'الإجمالي'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => number_format((float)$totalExpenses, 2) . ' ج.م', 'class' => 'font-mono font-bold text-rose-700'],
            ['value' => '—'],
        ];

        return view('layouts.print-report-a4', compact(
            'reportTitle', 'storeName', 'fromDate', 'toDate', 'kpis', 'tableHeaders', 'tableRows', 'tableTotals'
        ));
    }

    protected function printInventoryReport($storeId, $storeName)
    {
        $reportTitle = 'تقرير جرد وتقييم بضاعة المخزن';

        $items = Item::active()->orderBy('category')->orderBy('name')->get();
        $totalCostVal = '0.000';
        $totalSellVal = '0.000';

        $tableHeaders = [
            ['title' => 'كود الصنف'],
            ['title' => 'اسم الصنف'],
            ['title' => 'القسم'],
            ['title' => 'الرصيد الحالي'],
            ['title' => 'سعر التكلفة'],
            ['title' => 'سعر البيع'],
            ['title' => 'القيمة بالتكلفة'],
            ['title' => 'القيمة بالبيع'],
            ['title' => 'الربح المتوقع'],
        ];

        $tableRows = [];
        foreach ($items as $itm) {
            $unitCost = $itm->effectiveCost();
            $costVal = bcmul((string)$itm->current_stock, $unitCost, 3);
            $sellVal = bcmul((string)$itm->current_stock, (string)$itm->selling_price, 3);
            $expProfit = bcsub($sellVal, $costVal, 3);

            $totalCostVal = bcadd($totalCostVal, $costVal, 3);
            $totalSellVal = bcadd($totalSellVal, $sellVal, 3);

            $tableRows[] = [
                ['value' => $itm->code, 'class' => 'font-mono'],
                ['value' => $itm->name, 'class' => 'font-bold'],
                ['value' => $itm->category ?: 'عام'],
                ['value' => number_format((float)$itm->current_stock, 3) . ' ' . $itm->unit, 'class' => 'font-mono font-bold'],
                ['value' => number_format((float)$unitCost, 2), 'class' => 'font-mono'],
                ['value' => number_format((float)$itm->selling_price, 2), 'class' => 'font-mono font-bold text-emerald-700'],
                ['value' => number_format((float)$costVal, 2) . ' ج.م', 'class' => 'font-mono'],
                ['value' => number_format((float)$sellVal, 2) . ' ج.م', 'class' => 'font-mono font-bold'],
                ['value' => number_format((float)$expProfit, 2) . ' ج.م', 'class' => 'font-mono font-bold text-emerald-700'],
            ];
        }

        $totalExpProfit = bcsub($totalSellVal, $totalCostVal, 3);

        $kpis = [
            ['label' => 'عدد الأصناف المعرفة', 'value' => count($items) . ' صنف'],
            ['label' => 'إجمالي قيمة البضاعة (بالتكلفة)', 'value' => number_format((float)$totalCostVal, 2) . ' ج.م', 'class' => 'text-slate-900 font-bold'],
            ['label' => 'إجمالي قيمة البضاعة (بسعر البيع)', 'value' => number_format((float)$totalSellVal, 2) . ' ج.م', 'class' => 'text-emerald-700 font-bold'],
            ['label' => 'إجمالي الأرباح المتوقعة', 'value' => number_format((float)$totalExpProfit, 2) . ' ج.م', 'class' => 'text-indigo-800 font-bold'],
        ];

        $tableTotals = [
            ['value' => 'الإجمالي (' . count($items) . ' صنف)'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => number_format((float)$totalCostVal, 2) . ' ج.م', 'class' => 'font-mono font-bold'],
            ['value' => number_format((float)$totalSellVal, 2) . ' ج.م', 'class' => 'font-mono font-bold'],
            ['value' => number_format((float)$totalExpProfit, 2) . ' ج.م', 'class' => 'font-mono font-bold text-emerald-700'],
        ];

        $fromDate = null;
        $toDate = now()->toDateString();

        return view('layouts.print-report-a4', compact(
            'reportTitle', 'storeName', 'fromDate', 'toDate', 'kpis', 'tableHeaders', 'tableRows', 'tableTotals'
        ));
    }

    protected function printTreasuryReport($fromDate, $toDate, $storeId, $storeName, $selectedMethod = 'all')
    {
        $reportTitle = 'تقرير الخزائن والسيولة وسجل التحويلات المالية';
        $treasuryService = app(\App\Services\TreasuryService::class);
        $data = $treasuryService->getTreasuryReport($fromDate, $toDate, $storeId, $selectedMethod);

        $kpis = [
            ['label' => 'درج النقدية (كاش)', 'value' => number_format((float)($data['accounts']['cash']['closing_balance'] ?? 0), 2) . ' ج.م', 'class' => 'text-emerald-700 font-bold'],
            ['label' => 'إنستاباي (InstaPay)', 'value' => number_format((float)($data['accounts']['instapay']['closing_balance'] ?? 0), 2) . ' ج.م', 'class' => 'text-purple-700 font-bold'],
            ['label' => 'المحافظ الذكية', 'value' => number_format((float)($data['accounts']['e_wallet']['closing_balance'] ?? 0), 2) . ' ج.م', 'class' => 'text-amber-700 font-bold'],
            ['label' => 'إجمالي المقبوضات (+)', 'value' => '+' . number_format((float)$data['total_inflows'], 2) . ' ج.م', 'class' => 'text-slate-800'],
            ['label' => 'إجمالي المدفوعات (-)', 'value' => '-' . number_format((float)$data['total_outflows'], 2) . ' ج.م', 'class' => 'text-rose-700'],
            ['label' => '💰 إجمالي السيولة المجمعة (الكامل في الجميع)', 'value' => number_format((float)$data['total_liquidity'], 2) . ' ج.م', 'class' => 'text-emerald-800 text-base font-black'],
        ];

        $tableHeaders = [
            ['title' => 'التاريخ والوقت'],
            ['title' => 'رقم السند / المستند'],
            ['title' => 'نوع الحركة'],
            ['title' => 'الخزينة'],
            ['title' => 'الطرف والبيان'],
            ['title' => 'المقبوضات (+)'],
            ['title' => 'المدفوعات (-)'],
            ['title' => 'الرصيد بعد الحركة'],
        ];

        $tableRows = [];
        foreach ($data['ledger_entries'] as $ent) {
            $tableRows[] = [
                ['value' => $ent['date'] . ' ' . $ent['time'], 'class' => 'font-mono text-slate-500'],
                ['value' => $ent['doc_number'], 'class' => 'font-mono font-bold'],
                ['value' => $ent['type_label']],
                ['value' => $ent['method_label'], 'class' => 'font-bold'],
                ['value' => $ent['party'] . ($ent['notes'] ? " - {$ent['notes']}" : '')],
                ['value' => bccomp($ent['debit'], '0.000', 3) > 0 ? '+' . number_format((float)$ent['debit'], 2) . ' ج.م' : '—', 'class' => 'font-mono font-bold text-emerald-700'],
                ['value' => bccomp($ent['credit'], '0.000', 3) > 0 ? '-' . number_format((float)$ent['credit'], 2) . ' ج.م' : '—', 'class' => 'font-mono font-bold text-rose-700'],
                ['value' => number_format((float)$ent['running_balance'], 2) . ' ج.م', 'class' => 'font-mono font-bold text-slate-900'],
            ];
        }

        $tableTotals = [
            ['value' => 'الإجمالي المجمع (الكامل في الجميع)'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => '+' . number_format((float)$data['total_inflows'], 2) . ' ج.م', 'class' => 'font-mono font-bold text-emerald-700'],
            ['value' => '-' . number_format((float)$data['total_outflows'], 2) . ' ج.م', 'class' => 'font-mono font-bold text-rose-700'],
            ['value' => number_format((float)$data['total_liquidity'], 2) . ' ج.م', 'class' => 'font-mono font-black text-emerald-800'],
        ];

        return view('layouts.print-report-a4', compact(
            'reportTitle', 'storeName', 'fromDate', 'toDate', 'kpis', 'tableHeaders', 'tableRows', 'tableTotals'
        ));
    }

    protected function printAbcReport($fromDate, $toDate, $storeId, $storeName)
    {
        $reportTitle = 'تقرير تحليل حركة البضاعة والأرباح (ABC Analysis)';

        $service = app(\App\Services\InventoryAnalyticsService::class);
        $data = $service->getAbcAnalysis($fromDate, $toDate, $storeId);

        $kpis = [
            ['label' => 'الأصناف الذهبية (Class A)', 'value' => "{$data['class_a']['count']} صنف ({$data['class_a']['share']}%)"],
            ['label' => 'الأصناف المتوسطة (Class B)', 'value' => "{$data['class_b']['count']} صنف ({$data['class_b']['share']}%)"],
            ['label' => 'الأصناف الراكدة (Class C)', 'value' => "{$data['class_c']['count']} صنف ({$data['class_c']['share']}%)"],
            ['label' => 'إجمالي مجمل الأرباح', 'value' => number_format((float)$data['total_profit'], 2) . ' ج.م'],
        ];

        $tableHeaders = [
            ['title' => 'التصنيف'],
            ['title' => 'الكود'],
            ['title' => 'اسم الصنف'],
            ['title' => 'الرصيد بالمخزن'],
            ['title' => 'السحب اليومي'],
            ['title' => 'الكمية المباعة'],
            ['title' => 'إجمالي الإيراد'],
            ['title' => 'مجمل الربح'],
            ['title' => 'هامش الربح %'],
            ['title' => 'نسبة المساهمة %'],
        ];

        $tableRows = [];
        foreach ($data['items'] as $item) {
            $tableRows[] = [
                ['value' => 'Class ' . $item['abc_class'], 'class' => 'font-bold ' . ($item['abc_class'] === 'A' ? 'text-amber-600' : ($item['abc_class'] === 'B' ? 'text-indigo-600' : 'text-slate-600'))],
                ['value' => $item['code'], 'class' => 'font-mono text-slate-500'],
                ['value' => $item['name'], 'class' => 'font-bold'],
                ['value' => number_format((float)$item['current_stock'], 2) . ' ' . $item['unit'], 'class' => 'font-mono'],
                ['value' => number_format((float)$item['velocity'], 2) . ' /يوم', 'class' => 'font-mono text-cyan-700'],
                ['value' => number_format((float)$item['quantity_sold'], 2), 'class' => 'font-mono font-bold'],
                ['value' => number_format((float)$item['revenue'], 2) . ' ج.م', 'class' => 'font-mono'],
                ['value' => number_format((float)$item['gross_profit'], 2) . ' ج.م', 'class' => 'font-mono font-bold text-emerald-700'],
                ['value' => $item['profit_margin'] . '%', 'class' => 'font-mono'],
                ['value' => $item['profit_share'] . '%', 'class' => 'font-mono font-bold text-amber-700'],
            ];
        }

        $tableTotals = [
            ['value' => 'الإجمالي'],
            ['value' => '—'],
            ['value' => count($data['items']) . ' صنف'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => number_format((float)$data['total_revenue'], 2) . ' ج.م', 'class' => 'font-mono font-bold'],
            ['value' => number_format((float)$data['total_profit'], 2) . ' ج.م', 'class' => 'font-mono font-bold text-emerald-800'],
            ['value' => '—'],
            ['value' => '100%'],
        ];

        return view('layouts.print-report-a4', compact(
            'reportTitle', 'storeName', 'fromDate', 'toDate', 'kpis', 'tableHeaders', 'tableRows', 'tableTotals'
        ));
    }

    protected function printProfitLossReport($fromDate, $toDate, $storeId, $storeName)
    {
        $reportTitle = 'قائمة الدخل والأرباح والخسائر للفروع (P&L)';

        $service = app(\App\Services\ProfitLossService::class);
        $data = $service->getProfitLossReport($fromDate, $toDate, $storeId);

        $kpis = [
            ['label' => 'صافي المبيعات الإجمالية', 'value' => number_format((float)$data['grand_revenue'], 2) . ' ج.م'],
            ['label' => 'تكلفة البضاعة المباعة (COGS)', 'value' => number_format((float)$data['grand_cogs'], 2) . ' ج.م'],
            ['label' => 'المصروفات التشغيلية', 'value' => number_format((float)$data['grand_expenses'], 2) . ' ج.م'],
            ['label' => 'صافي الربح التشغيلي', 'value' => number_format((float)$data['grand_net_profit'], 2) . ' ج.م (' . $data['grand_net_margin'] . '%)'],
        ];

        $tableHeaders = [
            ['title' => 'الفرع / النقطة'],
            ['title' => 'عدد الفواتير'],
            ['title' => 'إجمالي المبيعات'],
            ['title' => 'المرتجعات'],
            ['title' => 'صافي الإيراد'],
            ['title' => 'تكلفة البضاعة (COGS)'],
            ['title' => 'مجمل الربح'],
            ['title' => 'المصروفات'],
            ['title' => 'صافي الربح النهائي'],
            ['title' => 'هامش الصافي %'],
        ];

        $tableRows = [];
        foreach ($data['stores'] as $st) {
            $tableRows[] = [
                ['value' => $st['store_name'] . ' (' . $st['store_code'] . ')', 'class' => 'font-bold'],
                ['value' => (string)$st['invoices_count'], 'class' => 'font-mono'],
                ['value' => number_format((float)$st['gross_sales'], 2) . ' ج.م', 'class' => 'font-mono'],
                ['value' => number_format((float)$st['returns_amount'], 2) . ' ج.م', 'class' => 'font-mono text-rose-600'],
                ['value' => number_format((float)$st['net_revenue'], 2) . ' ج.م', 'class' => 'font-mono font-bold'],
                ['value' => number_format((float)$st['cogs'], 2) . ' ج.م', 'class' => 'font-mono text-slate-500'],
                ['value' => number_format((float)$st['gross_profit'], 2) . ' ج.م', 'class' => 'font-mono font-bold text-emerald-700'],
                ['value' => number_format((float)$st['expenses_total'], 2) . ' ج.م', 'class' => 'font-mono text-rose-700'],
                ['value' => number_format((float)$st['net_operating_profit'], 2) . ' ج.م', 'class' => 'font-mono font-black ' . (bccomp((string)$st['net_operating_profit'], '0.000', 3) >= 0 ? 'text-emerald-800' : 'text-rose-800')],
                ['value' => $st['net_margin'] . '%', 'class' => 'font-mono font-bold'],
            ];
        }

        $tableTotals = [
            ['value' => 'الإجمالي المجمع'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => '—'],
            ['value' => number_format((float)$data['grand_revenue'], 2) . ' ج.م', 'class' => 'font-mono font-bold'],
            ['value' => number_format((float)$data['grand_cogs'], 2) . ' ج.م', 'class' => 'font-mono'],
            ['value' => number_format((float)$data['grand_gross_profit'], 2) . ' ج.م', 'class' => 'font-mono font-bold text-emerald-700'],
            ['value' => number_format((float)$data['grand_expenses'], 2) . ' ج.م', 'class' => 'font-mono text-rose-700'],
            ['value' => number_format((float)$data['grand_net_profit'], 2) . ' ج.م', 'class' => 'font-mono font-black text-emerald-800'],
            ['value' => $data['grand_net_margin'] . '%', 'class' => 'font-mono font-bold'],
        ];

        return view('layouts.print-report-a4', compact(
            'reportTitle', 'storeName', 'fromDate', 'toDate', 'kpis', 'tableHeaders', 'tableRows', 'tableTotals'
        ));
    }
}
