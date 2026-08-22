<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\ProfitService;
use Illuminate\Support\Facades\DB;

final class GetDashboardOverviewAction
{
    public function __construct(
        private readonly DashboardAnalyticsService $analyticsService,
        private readonly ProfitService $profitService
    ) {}

    /**
     * Compute comprehensive dashboard performance metrics for SPA
     */
    public function execute(?User $user = null, ?int $storeId = null): array
    {
        $today = now()->toDateString();

        // 1. Resolve Store
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
        if (!$activeStore) {
            $activeStore = Store::getMainStore();
            if ($activeStore) {
                $storeId = $activeStore->id;
            }
        }

        $storeFilter = $storeId ? (int)$storeId : null;

        // 2. 7-Day Trend & Peak Analytics
        $analytics = $this->analyticsService->getAnalytics(storeId: $storeFilter, trendDays: 7);

        // 3. Today's Invoices & Sales Metrics
        $todayInvoicesQuery = Invoice::with(['customer', 'store', 'user'])
            ->where('status', 'confirmed')
            ->whereDate('invoice_date', $today)
            ->when($storeFilter, fn($q) => $q->where('store_id', $storeFilter));

        $todayInvoices = (clone $todayInvoicesQuery)->latest('id')->get();
        $totalSales = '0.000';
        $totalPaid = '0.000';
        $totalRemaining = '0.000';
        $cashSales = '0.000';
        $creditSales = '0.000';
        $partialSales = '0.000';
        $partialPaid = '0.000';
        $invoicesCount = $todayInvoices->count();

        foreach ($todayInvoices as $inv) {
            $totalSales = bcadd($totalSales, (string)$inv->net_total, 3);
            $totalPaid = bcadd($totalPaid, (string)$inv->paid_amount, 3);
            $totalRemaining = bcadd($totalRemaining, (string)$inv->remaining_amount, 3);

            if ($inv->payment_type === 'cash') {
                $cashSales = bcadd($cashSales, (string)$inv->net_total, 3);
            } elseif ($inv->payment_type === 'credit') {
                $creditSales = bcadd($creditSales, (string)$inv->net_total, 3);
            } elseif ($inv->payment_type === 'partial') {
                $partialSales = bcadd($partialSales, (string)$inv->net_total, 3);
                $partialPaid = bcadd($partialPaid, (string)$inv->paid_amount, 3);
            }
        }

        // 4. Customer Voucher Payments Today
        $customerPayments = (string)(Payment::whereDate('payment_date', $today)
            ->whereNotNull('customer_id')
            ->sum('amount') ?: '0.000');

        $totalCashCollected = bcadd(bcadd($cashSales, $partialPaid, 3), $customerPayments, 3);

        // 5. Operating Expenses Today
        $expensesQuery = Expense::whereDate('expense_date', $today)
            ->when($storeFilter, fn($q) => $q->where('store_id', $storeFilter));

        $totalExpenses = (string)($expensesQuery->sum('amount') ?: '0.000');
        $expensesCount = $expensesQuery->count();

        // 6. Supplier Payments Today
        $supplierPaid = (string)(Payment::whereDate('payment_date', $today)
            ->whereNotNull('supplier_id')
            ->sum('amount') ?: '0.000');

        $totalOutflows = bcadd($totalExpenses, $supplierPaid, 3);
        $netCashToday = bcsub($totalCashCollected, $totalOutflows, 3);

        // 7. Customers & Suppliers Totals
        $customersCount = (int)Customer::where('is_active', true)->count();
        $suppliersCount = (int)Supplier::where('is_active', true)->count();
        $totalCustomersDebt = (float)(Customer::where('is_active', true)->sum('current_balance') ?: 0);
        $totalSuppliersDebt = (float)(Supplier::where('is_active', true)->sum('current_balance') ?: 0);

        // 8. Low Stock Items Radar
        $lowStockItems = Item::where('is_active', true)
            ->where(function ($q) {
                $q->whereColumn('current_stock', '<=', 'min_stock_level')
                  ->orWhere('current_stock', '<=', 5);
            })
            ->orderBy('current_stock', 'asc')
            ->take(8)
            ->get(['id', 'name', 'code', 'category', 'current_stock', 'min_stock_level', 'unit'])
            ->map(fn($it) => [
                'id'            => $it->id,
                'name'          => $it->name,
                'code'          => $it->code,
                'category'      => $it->category,
                'current_stock' => (float)$it->current_stock,
                'min_stock'     => (float)($it->min_stock_level ?? 5),
                'unit'          => $it->unit ?? 'كجم',
            ]);

        $lowStockCount = Item::where('is_active', true)
            ->where(function ($q) {
                $q->whereColumn('current_stock', '<=', 'min_stock_level')
                  ->orWhere('current_stock', '<=', 5);
            })
            ->count();

        // 9. Monthly Profits & Margin
        $startOfMonth = now()->startOfMonth()->toDateString();
        $periodic = $this->profitService->getPeriodicProfits($startOfMonth, $today, $storeFilter);

        // 10. Top Selling Items this Month
        $topSellingItems = InvoiceItem::select(
                'items.id as item_id',
                'items.name as item_name',
                'items.code as item_code',
                'items.unit',
                DB::raw('SUM(invoice_items.quantity) as total_qty'),
                DB::raw('SUM(invoice_items.total_price) as total_revenue')
            )
            ->join('items', 'items.id', '=', 'invoice_items.item_id')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', 'confirmed')
            ->whereDate('invoices.invoice_date', '>=', $startOfMonth)
            ->when($storeFilter, fn($q) => $q->where('invoices.store_id', $storeFilter))
            ->groupBy('items.id', 'items.name', 'items.code', 'items.unit')
            ->orderByDesc('total_qty')
            ->take(6)
            ->get()
            ->map(fn($t) => [
                'item_id'       => $t->item_id,
                'name'          => $t->item_name,
                'code'          => $t->item_code,
                'unit'          => $t->unit ?? 'كجم',
                'total_qty'     => (float)$t->total_qty,
                'total_revenue' => (float)$t->total_revenue,
            ]);

        // 11. Active Cash Shift
        $activeShift = null;
        if ($storeFilter) {
            $shift = CashShift::with('user')
                ->where('store_id', $storeFilter)
                ->where('status', 'open')
                ->latest('opened_at')
                ->first();

            if ($shift) {
                $activeShift = [
                    'id'            => $shift->id,
                    'shift_number'  => $shift->shift_number,
                    'user_name'     => $shift->user?->name ?? 'الكاشير',
                    'starting_cash' => (float)$shift->starting_cash,
                    'current_cash'  => (float)($shift->current_cash ?? $shift->starting_cash),
                    'opened_at'     => $shift->opened_at?->toDateTimeString(),
                ];
            }
        }

        // 12. Recent Invoices
        $recentInvoices = Invoice::with(['customer', 'store'])
            ->where('status', '!=', 'cancelled')
            ->when($storeFilter, fn($q) => $q->where('store_id', $storeFilter))
            ->latest('id')
            ->take(8)
            ->get()
            ->map(fn($inv) => [
                'id'               => $inv->id,
                'invoice_number'   => $inv->invoice_number,
                'customer_name'    => $inv->customer?->name ?? 'عميل نقدي',
                'invoice_date'     => $inv->invoice_date?->toDateString() ?? $inv->created_at?->toDateString(),
                'net_total'        => (float)$inv->net_total,
                'paid_amount'      => (float)$inv->paid_amount,
                'remaining_amount' => (float)$inv->remaining_amount,
                'payment_type'     => $inv->payment_type,
                'status'           => $inv->status,
            ]);

        return [
            'active_store' => $activeStore ? [
                'id'   => $activeStore->id,
                'name' => $activeStore->name,
                'code' => $activeStore->code,
                'type' => $activeStore->type,
            ] : null,
            'metrics' => [
                'today_sales'          => (float)$totalSales,
                'today_invoices_count' => $invoicesCount,
                'cash_sales'           => (float)$cashSales,
                'credit_sales'         => (float)$creditSales,
                'total_cash_collected' => (float)$totalCashCollected,
                'today_expenses'       => (float)$totalExpenses,
                'expenses_count'       => $expensesCount,
                'supplier_payments'    => (float)$supplierPaid,
                'net_cash_today'       => (float)$netCashToday,
                'customers_debt'       => $totalCustomersDebt,
                'suppliers_debt'       => $totalSuppliersDebt,
                'monthly_sales'        => (float)$periodic['total_sales'],
                'monthly_gross_profit' => (float)$periodic['gross_profit'],
                'monthly_margin'       => (float)$periodic['margin_percentage'],
                'customers_count'      => $customersCount,
                'suppliers_count'      => $suppliersCount,
                'low_stock_count'      => $lowStockCount,
            ],
            'analytics'         => $analytics,
            'low_stock_items'   => $lowStockItems,
            'top_selling_items' => $topSellingItems,
            'active_shift'      => $activeShift,
            'recent_invoices'   => $recentInvoices,
        ];
    }
}
