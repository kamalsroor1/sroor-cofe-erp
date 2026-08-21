<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Store;
use App\Models\StoreStock;
use App\Services\ProfitService;
use App\Services\TreasuryService;
use App\Services\InventoryAnalyticsService;
use App\Services\ProfitLossService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class ReportController extends Controller
{
    public function index(
        Request $request,
        ProfitService $profitService,
        TreasuryService $treasuryService,
        InventoryAnalyticsService $inventoryAnalyticsService,
        ProfitLossService $profitLossService
    ): Response {
        $activeTab = $request->input('tab', 'sales'); // sales, items, stores, customers, expenses, inventory, treasury
        $dateFilter = $request->input('period', 'this_month'); // today, this_week, this_month, this_year, custom
        $dateFrom = $request->input('from');
        $dateTo = $request->input('to');
        $storeId = $request->input('store_id', 'all');
        $treasuryMethod = $request->input('treasury_method', 'all');
        $inventoryStockFilter = $request->input('stock_filter', 'all');

        if (!$dateFrom || !$dateTo) {
            if ($dateFilter === 'today') {
                $dateFrom = now()->toDateString();
                $dateTo = now()->toDateString();
            } elseif ($dateFilter === 'yesterday') {
                $dateFrom = now()->subDay()->toDateString();
                $dateTo = now()->subDay()->toDateString();
            } elseif ($dateFilter === 'this_week') {
                $dateFrom = now()->startOfWeek()->toDateString();
                $dateTo = now()->toDateString();
            } elseif ($dateFilter === 'this_year') {
                $dateFrom = now()->startOfYear()->toDateString();
                $dateTo = now()->toDateString();
            } else { // this_month
                $dateFrom = now()->startOfMonth()->toDateString();
                $dateTo = now()->toDateString();
            }
        }

        $storeFilter = ($storeId !== 'all') ? (int)$storeId : null;
        $stores = Store::where('is_active', true)->select('id', 'name')->get();

        // 1. Invoices & Sales Metrics (Tab 1: Sales & P&L)
        $invoicesQuery = Invoice::where('status', 'confirmed')
            ->whereDate('invoice_date', '>=', $dateFrom)
            ->whereDate('invoice_date', '<=', $dateTo)
            ->when($storeFilter, fn($q) => $q->where('store_id', $storeFilter));

        $invoices = (clone $invoicesQuery)->get();
        $totalSales = '0.000';
        $totalPaid = '0.000';
        $totalRemaining = '0.000';
        $totalCost = '0.000';
        $invoicesCount = $invoices->count();

        foreach ($invoices as $inv) {
            $totalSales = bcadd($totalSales, (string)$inv->net_total, 3);
            $totalPaid = bcadd($totalPaid, (string)$inv->paid_amount, 3);
            $totalRemaining = bcadd($totalRemaining, (string)$inv->remaining_amount, 3);
            $totalCost = bcadd($totalCost, (string)$inv->total_cost, 3);
        }

        $grossProfit = bcsub($totalSales, $totalCost, 3);
        $marginPct = '0.00';
        if (bccomp($totalSales, '0.000', 3) > 0) {
            $marginPct = bcmul(bcdiv($grossProfit, $totalSales, 4), '100', 2);
        }

        $avgInvoice = $invoicesCount > 0 ? bcdiv($totalSales, (string)$invoicesCount, 2) : '0.00';

        // 2. Operational Expenses (Tab 5: Expenses)
        $expensesQuery = Expense::whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->when($storeFilter, fn($q) => $q->where('store_id', $storeFilter));

        $totalExpenses = (string)($expensesQuery->sum('amount') ?: '0.000');
        $netProfit = bcsub($grossProfit, $totalExpenses, 3);

        $expensesByCategory = (clone $expensesQuery)
            ->select('category', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get();

        // 3. Item-level Sales & Profitability (Tab 2: Items)
        $itemProfits = InvoiceItem::whereHas('invoice', function ($q) use ($storeFilter, $dateFrom, $dateTo) {
                $q->where('status', 'confirmed')
                  ->whereDate('invoice_date', '>=', $dateFrom)
                  ->whereDate('invoice_date', '<=', $dateTo)
                  ->when($storeFilter, fn($sub) => $sub->where('store_id', $storeFilter));
            })
            ->select(
                'item_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(total_price) as total_revenue'),
                DB::raw('SUM(quantity * cost_price) as total_cogs')
            )
            ->groupBy('item_id')
            ->with('item')
            ->get()
            ->map(function ($row) {
                $profit = bcsub((string)$row->total_revenue, (string)$row->total_cogs, 3);
                $margin = '0.0';
                if (bccomp((string)$row->total_revenue, '0.000', 3) > 0) {
                    $margin = bcmul(bcdiv($profit, (string)$row->total_revenue, 4), '100', 1);
                }
                return [
                    'item_id' => $row->item_id,
                    'name' => $row->item?->name ?? 'صنف محذوف',
                    'code' => $row->item?->code,
                    'category' => $row->item?->category,
                    'unit' => $row->item?->unit,
                    'total_qty' => (float)$row->total_qty,
                    'total_revenue' => (float)$row->total_revenue,
                    'total_cogs' => (float)$row->total_cogs,
                    'profit' => (float)$profit,
                    'margin' => (float)$margin,
                ];
            })
            ->sortByDesc('total_revenue')
            ->values();

        // 4. Store Comparative Breakdown (Tab 3: Stores)
        $storeBreakdown = [];
        foreach ($stores as $st) {
            $stInvoices = Invoice::where('status', 'confirmed')
                ->where('store_id', $st->id)
                ->whereDate('invoice_date', '>=', $dateFrom)
                ->whereDate('invoice_date', '<=', $dateTo)
                ->get();

            $stSales = '0.000';
            $stPaid = '0.000';
            $stRemaining = '0.000';
            $stCost = '0.000';

            foreach ($stInvoices as $si) {
                $stSales = bcadd($stSales, (string)$si->net_total, 3);
                $stPaid = bcadd($stPaid, (string)$si->paid_amount, 3);
                $stRemaining = bcadd($stRemaining, (string)$si->remaining_amount, 3);
                $stCost = bcadd($stCost, (string)$si->total_cost, 3);
            }

            $stProfit = bcsub($stSales, $stCost, 3);
            $stMargin = '0.00';
            if (bccomp($stSales, '0.000', 3) > 0) {
                $stMargin = bcmul(bcdiv($stProfit, $stSales, 4), '100', 2);
            }

            $sharePct = '0.0';
            if (bccomp($totalSales, '0.000', 3) > 0) {
                $sharePct = bcmul(bcdiv($stSales, $totalSales, 4), '100', 1);
            }

            $storeBreakdown[] = [
                'id' => $st->id,
                'name' => $st->name,
                'invoice_count' => $stInvoices->count(),
                'total_sales' => (float)$stSales,
                'total_paid' => (float)$stPaid,
                'total_remaining' => (float)$stRemaining,
                'gross_profit' => (float)$stProfit,
                'margin' => (float)$stMargin,
                'share_pct' => (float)$sharePct,
            ];
        }

        // 5. Customer Sales & Receivables (Tab 4: Customers)
        $customerSales = (clone $invoicesQuery)
            ->select(
                'customer_id',
                DB::raw('COUNT(*) as total_invoices'),
                DB::raw('SUM(net_total) as total_bought'),
                DB::raw('SUM(paid_amount) as total_paid'),
                DB::raw('SUM(remaining_amount) as total_debt_in_period')
            )
            ->groupBy('customer_id')
            ->with('customer')
            ->orderByDesc('total_bought')
            ->take(25)
            ->get()
            ->map(fn($c) => [
                'customer_id' => $c->customer_id,
                'name' => $c->customer?->name ?? 'عميل محذوف',
                'phone' => $c->customer?->phone,
                'current_balance' => (float)($c->customer?->current_balance ?? 0),
                'total_invoices' => (int)$c->total_invoices,
                'total_bought' => (float)$c->total_bought,
                'total_paid' => (float)$c->total_paid,
                'total_debt_in_period' => (float)$c->total_debt_in_period,
            ]);

        $totalAllCustomersDebt = (float)(Customer::where('is_active', true)->sum('current_balance') ?: 0);

        // 6. Inventory Valuation & ABC Analysis (Tab 6: Inventory)
        $stockCostValuation = '0.000';
        $stockSellingValuation = '0.000';
        $inventoryItems = [];

        if ($storeFilter) {
            $storeStocks = StoreStock::with('item')
                ->where('store_id', $storeFilter)
                ->whereHas('item', fn($q) => $q->where('is_active', true))
                ->get();

            foreach ($storeStocks as $stk) {
                $item = $stk->item;
                if (!$item) continue;

                $qty = (string)($stk->quantity ?? '0.000');
                $costPrice = (string)($item->cost_price ?? '0.000');
                $sellingPrice = (string)($stk->custom_selling_price ?: $item->selling_price);

                $costVal = bcmul($qty, $costPrice, 3);
                $sellVal = bcmul($qty, $sellingPrice, 3);
                $profit = bcsub($sellVal, $costVal, 3);

                $stockCostValuation = bcadd($stockCostValuation, $costVal, 3);
                $stockSellingValuation = bcadd($stockSellingValuation, $sellVal, 3);

                $inventoryItems[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'category' => $item->category,
                    'unit' => $item->unit,
                    'current_stock' => (float)$qty,
                    'cost_price' => (float)$costPrice,
                    'selling_price' => (float)$sellingPrice,
                    'cost_val' => (float)$costVal,
                    'sell_val' => (float)$sellVal,
                    'profit' => (float)$profit,
                ];
            }
        } else {
            $allItems = Item::where('is_active', true)->get();
            foreach ($allItems as $itm) {
                $qty = (string)($itm->current_stock ?? '0.000');
                $costPrice = (string)($itm->cost_price ?? '0.000');
                $sellingPrice = (string)($itm->selling_price ?? '0.000');

                $costVal = bcmul($qty, $costPrice, 3);
                $sellVal = bcmul($qty, $sellingPrice, 3);
                $profit = bcsub($sellVal, $costVal, 3);

                $stockCostValuation = bcadd($stockCostValuation, $costVal, 3);
                $stockSellingValuation = bcadd($stockSellingValuation, $sellVal, 3);

                $inventoryItems[] = [
                    'id' => $itm->id,
                    'name' => $itm->name,
                    'code' => $itm->code,
                    'category' => $itm->category,
                    'unit' => $itm->unit,
                    'current_stock' => (float)$qty,
                    'cost_price' => (float)$costPrice,
                    'selling_price' => (float)$sellingPrice,
                    'cost_val' => (float)$costVal,
                    'sell_val' => (float)$sellVal,
                    'profit' => (float)$profit,
                ];
            }
        }

        if ($inventoryStockFilter === 'in_stock') {
            $inventoryItems = array_values(array_filter($inventoryItems, fn($i) => $i['current_stock'] > 0));
        } elseif ($inventoryStockFilter === 'zero_stock') {
            $inventoryItems = array_values(array_filter($inventoryItems, fn($i) => $i['current_stock'] <= 0));
        }

        $expectedStockProfit = bcsub($stockSellingValuation, $stockCostValuation, 3);
        $abcData = $inventoryAnalyticsService->getAbcAnalysis($dateFrom, $dateTo, $storeFilter);

        // 7. Treasury & Inflow / Outflow Report (Tab 7: Treasury)
        $treasuryData = $treasuryService->getTreasuryReport(
            fromDate: $dateFrom,
            toDate: $dateTo,
            storeId: $storeFilter,
        return Inertia::render('Reports/Index', [
            // Fast immediate layout metadata
            'active_tab' => $activeTab,
            'stores' => $stores,
            'filters' => [
                'tab' => $activeTab,
                'period' => $dateFilter,
                'from' => $dateFrom,
                'to' => $dateTo,
                'store_id' => $storeId,
                'treasury_method' => $treasuryMethod,
                'stock_filter' => $inventoryStockFilter,
            ],

            // Deferred Heavy Calculations
            'summary' => Inertia::defer(fn() => [
                'total_sales' => (float)$totalSales,
                'invoices_count' => $invoicesCount,
                'total_paid' => (float)$totalPaid,
                'total_remaining' => (float)$totalRemaining,
                'total_cogs' => (float)$totalCost,
                'gross_profit' => (float)$grossProfit,
                'margin_percentage' => (float)$marginPct,
                'avg_invoice' => (float)$avgInvoice,
                'total_expenses' => (float)$totalExpenses,
                'net_profit' => (float)$netProfit,
                'total_customers_debt' => $totalAllCustomersDebt,
                'stock_cost_valuation' => (float)$stockCostValuation,
                'stock_selling_valuation' => (float)$stockSellingValuation,
                'expected_stock_profit' => (float)$expectedStockProfit,
            ], 'reportsData'),

            'item_profits' => Inertia::defer(fn() => $itemProfits, 'reportsData'),
            'store_breakdown' => Inertia::defer(fn() => $storeBreakdown, 'reportsData'),
            'customer_sales' => Inertia::defer(fn() => $customerSales, 'reportsData'),
            'expenses_breakdown' => Inertia::defer(fn() => $expensesByCategory->map(fn($e) => [
                'category' => $e->category,
                'amount' => (float)$e->total_amount,
                'count' => (int)$e->count,
            ]), 'reportsData'),
            'inventory_items' => Inertia::defer(fn() => $inventoryItems, 'reportsData'),
            'abc_data' => Inertia::defer(fn() => $abcData, 'reportsData'),
            'treasury_data' => Inertia::defer(fn() => $treasuryData, 'reportsData'),
        ]);
    }

    public function exportAbc(Request $request, InventoryAnalyticsService $service, ExportService $exportService)
    {
        $dateFrom = $request->input('from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('to', now()->toDateString());
        $storeId = $request->input('store_id', 'all');
        $storeFilter = ($storeId !== 'all') ? (int)$storeId : null;

        $data = $service->getAbcAnalysis($dateFrom, $dateTo, $storeFilter);
        $filename = 'abc-inventory-' . $dateFrom . '-to-' . $dateTo . '.csv';

        return $exportService->exportAbcAnalysis($data, $filename);
    }
}