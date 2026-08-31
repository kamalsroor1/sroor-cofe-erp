<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Expense;
use App\Models\Store;
use App\Models\Customer;
use App\Services\ProfitService;
use Illuminate\Support\Facades\DB;

class ReportsIndex extends Component
{
    public $activeTab = 'sales'; // sales, items, stores, customers, expenses, inventory, treasury
    public $dateFilter = 'today'; // today, this_week, this_month, this_year, custom
    public $selectedStoreId = 'all';
    public $selectedTreasuryMethod = 'all'; // all, cash, instapay, e_wallet, visa
    public $inventoryStockFilter = 'all'; // all, in_stock, out_of_stock
    public $fromDate;
    public $toDate;

    public function mount()
    {
        abort_if(!auth()->user()?->can('reports.view'), 403, 'غير مصرح لك بالوصول للتقارير المالية والأرباح.');
        $this->setFilter('today');
    }

    public function setFilter($filter)
    {
        $this->dateFilter = $filter;

        if ($filter === 'today') {
            $this->fromDate = now()->toDateString();
            $this->toDate = now()->toDateString();
        } elseif ($filter === 'this_week') {
            $this->fromDate = now()->startOfWeek()->toDateString();
            $this->toDate = now()->toDateString();
        } elseif ($filter === 'this_month') {
            $this->fromDate = now()->startOfMonth()->toDateString();
            $this->toDate = now()->toDateString();
        } elseif ($filter === 'this_year') {
            $this->fromDate = now()->startOfYear()->toDateString();
            $this->toDate = now()->toDateString();
        }
    }

    public function updatedFromDate()
    {
        $this->dateFilter = 'custom';
    }

    public function updatedToDate()
    {
        $this->dateFilter = 'custom';
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function exportAbc()
    {
        $storeFilter = ($this->selectedStoreId && $this->selectedStoreId !== 'all') ? (int)$this->selectedStoreId : null;
        $service = app(\App\Services\InventoryAnalyticsService::class);
        $data = $service->getAbcAnalysis($this->fromDate, $this->toDate, $storeFilter);

        $exportService = app(\App\Services\ExportService::class);
        $filename = 'abc-inventory-' . $this->fromDate . '-to-' . $this->toDate . '.csv';

        return $exportService->exportAbcAnalysis($data, $filename);
    }

    public function render(ProfitService $profitService)
    {
        $storeFilter = ($this->selectedStoreId && $this->selectedStoreId !== 'all') 
            ? (int)$this->selectedStoreId 
            : null;

        $stores = Store::active()->get();

        // 1. Invoices base query for the period & store
        $invoicesQuery = Invoice::where('status', 'confirmed')
            ->when($this->fromDate, fn($q) => $q->whereDate('invoice_date', '>=', $this->fromDate))
            ->when($this->toDate, fn($q) => $q->whereDate('invoice_date', '<=', $this->toDate))
            ->when($storeFilter, fn($q) => $q->where('store_id', $storeFilter));

        $invoices = (clone $invoicesQuery)->get();

        $totalSales     = '0.000';
        $totalPaid      = '0.000';
        $totalRemaining = '0.000';
        $totalCost      = '0.000';
        $invoiceCount   = $invoices->count();

        foreach ($invoices as $inv) {
            $totalSales     = bcadd($totalSales, $inv->net_total, 3);
            $totalPaid      = bcadd($totalPaid, $inv->paid_amount, 3);
            $totalRemaining = bcadd($totalRemaining, $inv->remaining_amount, 3);
            $totalCost      = bcadd($totalCost, $inv->total_cost, 3);
        }

        $grossProfit = bcsub($totalSales, $totalCost, 3);
        $marginPct   = '0.00';
        if (bccomp($totalSales, '0.000', 3) > 0) {
            $marginPct = bcmul(bcdiv($grossProfit, $totalSales, 4), '100', 2);
        }

        $avgInvoiceValue = '0.00';
        if ($invoiceCount > 0) {
            $avgInvoiceValue = bcdiv($totalSales, (string)$invoiceCount, 2);
        }

        $totalCustomerDebt = (string)(Customer::where('current_balance', '>', 0)->sum('current_balance') ?: '0.000');

        $periodic = [
            'invoice_count'       => $invoiceCount,
            'total_sales'         => $totalSales,
            'total_paid'          => $totalPaid,
            'total_remaining'     => $totalRemaining,
            'total_customer_debt' => $totalCustomerDebt,
            'total_cost'          => $totalCost,
            'gross_profit'        => $grossProfit,
            'margin_percentage'   => $marginPct,
            'avg_invoice'         => $avgInvoiceValue,
        ];

        // 2. Operational Expenses in Period
        $expensesQuery = Expense::when($this->fromDate, fn($q) => $q->whereDate('expense_date', '>=', $this->fromDate))
            ->when($this->toDate, fn($q) => $q->whereDate('expense_date', '<=', $this->toDate))
            ->when($storeFilter, fn($q) => $q->where('store_id', $storeFilter));

        $totalExpenses = (clone $expensesQuery)->sum('amount') ?: '0.000';
        $netProfitAfterExpenses = bcsub((string)$grossProfit, (string)$totalExpenses, 3);

        $expensesByCategory = (clone $expensesQuery)
            ->select('category', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get();

        // 3. Item-level Sales & Profitability
        $itemProfits = InvoiceItem::whereHas('invoice', function ($q) use ($storeFilter) {
                $q->where('status', 'confirmed')
                  ->when($this->fromDate, fn($sub) => $sub->whereDate('invoice_date', '>=', $this->fromDate))
                  ->when($this->toDate, fn($sub) => $sub->whereDate('invoice_date', '<=', $this->toDate))
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
                    'item'          => $row->item,
                    'total_qty'     => $row->total_qty,
                    'total_revenue' => $row->total_revenue,
                    'total_cogs'    => $row->total_cogs,
                    'profit'        => $profit,
                    'margin'        => $margin,
                ];
            })
            ->sortByDesc('total_revenue')
            ->values();

        // 4. Store-by-Store Comparative Breakdown
        $storeBreakdown = [];
        foreach ($stores as $st) {
            $stInvoices = Invoice::where('status', 'confirmed')
                ->where('store_id', $st->id)
                ->when($this->fromDate, fn($q) => $q->whereDate('invoice_date', '>=', $this->fromDate))
                ->when($this->toDate, fn($q) => $q->whereDate('invoice_date', '<=', $this->toDate))
                ->get();

            $stSales = '0.000';
            $stPaid = '0.000';
            $stRemaining = '0.000';
            $stCost = '0.000';

            foreach ($stInvoices as $si) {
                $stSales     = bcadd($stSales, $si->net_total, 3);
                $stPaid      = bcadd($stPaid, $si->paid_amount, 3);
                $stRemaining = bcadd($stRemaining, $si->remaining_amount, 3);
                $stCost      = bcadd($stCost, $si->total_cost, 3);
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
                'store'         => $st,
                'invoice_count' => $stInvoices->count(),
                'total_sales'   => $stSales,
                'total_paid'    => $stPaid,
                'total_remaining'=> $stRemaining,
                'total_cost'    => $stCost,
                'gross_profit'  => $stProfit,
                'margin'        => $stMargin,
                'share_pct'     => $sharePct,
            ];
        }

        // 5. Customer Sales & Receivables in this period
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
            ->take(20)
            ->get();

        $totalAllCustomersDebt = Customer::active()->sum('current_balance') ?: '0.000';

        // 6. Stock Inventory Valuation (Fully linked to selected store or all stores)
        $stockCostValuation    = '0.000';
        $stockSellingValuation = '0.000';
        $inventoryItems        = [];
        $selectedStore         = null;

        if ($storeFilter) {
            $selectedStore = Store::find($storeFilter);
            
            $storeStocks = \App\Models\StoreStock::with('item')
                ->where('store_id', $storeFilter)
                ->whereHas('item', fn($q) => $q->where('is_active', true))
                ->get();

            $existingItemIds = $storeStocks->pluck('item_id')->toArray();
            $missingItems = Item::active()->whereNotIn('id', $existingItemIds)->get();

            foreach ($storeStocks as $stk) {
                $item = $stk->item;
                if (!$item) continue;

                $qty          = (string)($stk->quantity ?? '0.000');
                $costPrice    = (string)($item->cost_price ?? '0.000');
                $sellingPrice = (string)$stk->effective_selling_price;

                $costVal = bcmul($qty, $costPrice, 3);
                $sellVal = bcmul($qty, $sellingPrice, 3);
                $profit  = bcsub($sellVal, $costVal, 3);

                $stockCostValuation    = bcadd($stockCostValuation, $costVal, 3);
                $stockSellingValuation = bcadd($stockSellingValuation, $sellVal, 3);

                $inventoryItems[] = (object)[
                    'id'               => $item->id,
                    'name'             => $item->name,
                    'code'             => $item->code,
                    'category'         => $item->category,
                    'unit'             => $item->unit,
                    'current_stock'    => $qty,
                    'cost_price'       => $costPrice,
                    'selling_price'    => $sellingPrice,
                    'cost_val'         => $costVal,
                    'sell_val'         => $sellVal,
                    'profit'           => $profit,
                    'has_custom_price' => ($stk->custom_selling_price !== null && bccomp((string)$stk->custom_selling_price, '0.000', 3) > 0),
                ];
            }

            foreach ($missingItems as $mItm) {
                $costPrice    = (string)($mItm->cost_price ?? '0.000');
                $sellingPrice = (string)($mItm->selling_price ?? '0.000');

                $inventoryItems[] = (object)[
                    'id'               => $mItm->id,
                    'name'             => $mItm->name,
                    'code'             => $mItm->code,
                    'category'         => $mItm->category,
                    'unit'             => $mItm->unit,
                    'current_stock'    => '0.000',
                    'cost_price'       => $costPrice,
                    'selling_price'    => $sellingPrice,
                    'cost_val'         => '0.000',
                    'sell_val'         => '0.000',
                    'profit'           => '0.000',
                    'has_custom_price' => false,
                ];
            }
        } else {
            $allItems = Item::active()->get();
            foreach ($allItems as $itm) {
                $qty          = (string)($itm->current_stock ?? '0.000');
                $costPrice    = (string)($itm->cost_price ?? '0.000');
                $sellingPrice = (string)($itm->selling_price ?? '0.000');

                $costVal = bcmul($qty, $costPrice, 3);
                $sellVal = bcmul($qty, $sellingPrice, 3);
                $profit  = bcsub($sellVal, $costVal, 3);

                $stockCostValuation    = bcadd($stockCostValuation, $costVal, 3);
                $stockSellingValuation = bcadd($stockSellingValuation, $sellVal, 3);

                $inventoryItems[] = (object)[
                    'id'               => $itm->id,
                    'name'             => $itm->name,
                    'code'             => $itm->code,
                    'category'         => $itm->category,
                    'unit'             => $itm->unit,
                    'current_stock'    => $qty,
                    'cost_price'       => $costPrice,
                    'selling_price'    => $sellingPrice,
                    'cost_val'         => $costVal,
                    'sell_val'         => $sellVal,
                    'profit'           => $profit,
                    'has_custom_price' => false,
                ];
            }
        }

        if ($this->inventoryStockFilter === 'in_stock') {
            $filteredInventoryItems = array_values(array_filter($inventoryItems, function ($item) {
                return bccomp((string)$item->current_stock, '0.000', 3) > 0;
            }));
        } elseif ($this->inventoryStockFilter === 'zero_stock') {
            $filteredInventoryItems = array_values(array_filter($inventoryItems, function ($item) {
                return bccomp((string)$item->current_stock, '0.000', 3) <= 0;
            }));
        } else {
            $filteredInventoryItems = $inventoryItems;
        }

        $expectedStockProfit = bcsub($stockSellingValuation, $stockCostValuation, 3);

        $treasuryService = app(\App\Services\TreasuryService::class);
        $treasuryData = $treasuryService->getTreasuryReport(
            fromDate: $this->fromDate,
            toDate: $this->toDate,
            storeId: $storeFilter,
            selectedMethod: $this->selectedTreasuryMethod
        );

        $inventoryAnalyticsService = app(\App\Services\InventoryAnalyticsService::class);
        $abcData = $inventoryAnalyticsService->getAbcAnalysis(
            fromDate: $this->fromDate,
            toDate: $this->toDate,
            storeId: $storeFilter
        );

        $profitLossService = app(\App\Services\ProfitLossService::class);
        $pnlData = $profitLossService->getProfitLossReport(
            fromDate: $this->fromDate,
            toDate: $this->toDate,
            storeId: $storeFilter
        );

        return view('livewire.reports-index', [
            'stores'                 => $stores,
            'selectedStore'          => $selectedStore,
            'periodic'               => $periodic,
            'totalExpenses'          => $totalExpenses,
            'expensesByCategory'     => $expensesByCategory,
            'netProfitAfterExpenses' => $netProfitAfterExpenses,
            'itemProfits'            => $itemProfits,
            'storeBreakdown'         => $storeBreakdown,
            'customerSales'          => $customerSales,
            'totalAllCustomersDebt'  => $totalAllCustomersDebt,
            'stockCostValuation'     => $stockCostValuation,
            'stockSellingValuation'  => $stockSellingValuation,
            'expectedStockProfit'    => $expectedStockProfit,
            'inventoryItems'         => $filteredInventoryItems,
            'allItems'               => $filteredInventoryItems,
            'totalInventoryCount'    => count($inventoryItems),
            'inStockCount'           => count(array_filter($inventoryItems, fn($i) => bccomp((string)$i->current_stock, '0.000', 3) > 0)),
            'zeroStockCount'         => count(array_filter($inventoryItems, fn($i) => bccomp((string)$i->current_stock, '0.000', 3) <= 0)),
            'treasuryData'           => $treasuryData,
            'abcData'                => $abcData,
            'pnlData'                => $pnlData,
        ])->layout('components.layouts.app', ['title' => 'التقارير المالية والمبيعات والأرباح']);
    }
}

