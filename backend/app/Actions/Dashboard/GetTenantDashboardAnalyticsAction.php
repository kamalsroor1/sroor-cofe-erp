<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Customer;
use App\Models\CashShift;
use App\Models\Store;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\ProfitService;
use Illuminate\Support\Facades\DB;

class GetTenantDashboardAnalyticsAction
{
    /**
     * Cache for memoizing execution results within the same request lifecycle
     */
    protected array $memoized = [];

    public function __construct(
        protected DashboardAnalyticsService $analyticsService,
        protected ProfitService $profitService
    ) {}

    /**
     * حساب وتجميع مؤشرات أداء الداشبورد لمستأجر ERP مع التخزين المؤقت للطلب
     */
    public function execute(?User $user): array
    {
        $storeId = session('current_store_id');
        $cacheKey = ($user?->id ?? 0) . ':' . ($storeId ?? 'all');

        if (isset($this->memoized[$cacheKey])) {
            return $this->memoized[$cacheKey];
        }

        $today = now()->toDateString();

        // 1. Resolve Active Store
        $activeStore = null;
        if ($storeId) {
            $activeStore = Store::where('id', $storeId)->where('is_active', true)->first();
        }
        if (!$activeStore && $user) {
            $activeStore = $user->getCurrentStore();
            if ($activeStore) {
                $storeId = $activeStore->id;
            }
        }

        // Store filter for non-admin or scoped store
        $storeFilter = $storeId ? (int)$storeId : null;

        // 2. Analytics Service (7-Day trend, Peak hours, Payment distribution)
        $analytics = $this->analyticsService->getAnalytics(storeId: $storeFilter, trendDays: 7);

        // 3. Today's Invoices & Sales Metrics
        $todayInvoicesQuery = Invoice::with(['customer', 'store'])
            ->where('status', 'confirmed')
            ->whereDate('invoice_date', $today);

        if ($storeFilter) {
            $todayInvoicesQuery->where('store_id', $storeFilter);
        }

        $todayInvoices = (clone $todayInvoicesQuery)->latest('id')->get();
        $totalSales = (string)($todayInvoices->sum('net_total') ?: '0.000');
        $invoicesCount = $todayInvoices->count();

        $cashSales = (string)($todayInvoices->where('payment_type', 'cash')->sum('net_total') ?: '0.000');
        $creditSales = (string)($todayInvoices->where('payment_type', 'credit')->sum('net_total') ?: '0.000');
        $partialSales = (string)($todayInvoices->where('payment_type', 'partial')->sum('net_total') ?: '0.000');
        $partialPaid = (string)($todayInvoices->where('payment_type', 'partial')->sum('paid_amount') ?: '0.000');

        // 4. Cash Collected from Customer Vouchers
        $customerPayments = (string)(Payment::whereDate('payment_date', $today)
            ->whereNotNull('customer_id')
            ->sum('amount') ?: '0.000');

        $totalCashCollected = bcadd(bcadd($cashSales, $partialPaid, 3), $customerPayments, 3);

        // 5. Operating Expenses Today
        $expensesQuery = Expense::whereDate('expense_date', $today);
        if ($storeFilter) {
            $expensesQuery->where('store_id', $storeFilter);
        }
        $totalExpenses = (string)($expensesQuery->sum('amount') ?: '0.000');

        // 6. Supplier Payments Today
        $supplierPaid = (string)(Payment::whereDate('payment_date', $today)
            ->whereNotNull('supplier_id')
            ->sum('amount') ?: '0.000');

        $totalOutflows = bcadd($totalExpenses, $supplierPaid, 3);
        $netCashToday = bcsub($totalCashCollected, $totalOutflows, 3);

        // 7. Customer Debts Total
        $totalCustomersDebt = (float)Customer::where('is_active', true)->sum('current_balance');

        // 8. Low Stock Radar
        $lowStockQuery = Item::where('is_active', true)
            ->whereNotNull('min_stock_level')
            ->where('min_stock_level', '>', 0)
            ->whereColumn('current_stock', '<=', 'min_stock_level')
            ->orderBy('current_stock', 'asc')
            ->take(6);

        $lowStockItems = $lowStockQuery->get(['id', 'name', 'code', 'current_stock', 'min_stock_level', 'unit']);

        // 9. Periodic Profits (Monthly Gross & Margin)
        $startOfMonth = now()->startOfMonth()->toDateString();
        $periodic = $this->profitService->getPeriodicProfits($startOfMonth, $today, $storeFilter);

        // 10. Top Selling Coffee & Products this Month
        $topSellingItems = InvoiceItem::select(
                'items.id as item_id',
                'items.name as item_name',
                DB::raw('SUM(invoice_items.quantity) as total_qty'),
                DB::raw('SUM(invoice_items.total_price) as total_revenue')
            )
            ->join('items', 'items.id', '=', 'invoice_items.item_id')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', 'confirmed')
            ->whereDate('invoices.invoice_date', '>=', $startOfMonth)
            ->groupBy('items.id', 'items.name')
            ->orderByDesc('total_qty')
            ->take(5)
            ->get();

        // 11. Active Cash Shift
        $activeShift = null;
        if ($storeFilter) {
            $activeShift = CashShift::where('store_id', $storeFilter)
                ->where('status', 'open')
                ->latest('id')
                ->first();
        }

        $result = [
            'metrics' => [
                'total_sales' => (float)$totalSales,
                'invoices_count' => $invoicesCount,
                'cash_sales' => (float)$cashSales,
                'credit_sales' => (float)$creditSales,
                'partial_sales' => (float)$partialSales,
                'total_cash_collected' => (float)$totalCashCollected,
                'total_expenses' => (float)$totalExpenses,
                'supplier_payments' => (float)$supplierPaid,
                'net_cash_today' => (float)$netCashToday,
                'total_customers_debt' => $totalCustomersDebt,
                'monthly_sales' => (float)$periodic['total_sales'],
                'monthly_gross_profit' => (float)$periodic['gross_profit'],
                'monthly_margin' => $periodic['margin_percentage'],
            ],
            'analytics' => $analytics,
            'recent_invoices' => \App\Http\Resources\InvoiceSummaryResource::collection($todayInvoices->take(6))->resolve(),
            'low_stock_items' => \App\Http\Resources\POSItemResource::collection($lowStockItems)->resolve(),
            'top_selling_items' => $topSellingItems->map(fn($t) => [
                'item_id' => $t->item_id,
                'name' => $t->item_name,
                'total_qty' => (float)$t->total_qty,
                'total_revenue' => (float)$t->total_revenue,
            ]),
            'active_shift' => $activeShift ? (new \App\Http\Resources\Api\CashShiftResource($activeShift))->resolve() : null,
            'active_store' => $activeStore ? (new \App\Http\Resources\StoreResource($activeStore))->resolve() : null,
        ];

        $this->memoized[$cacheKey] = $result;

        return $result;
    }
}