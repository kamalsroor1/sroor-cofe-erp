<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DailyCashFlowService;
use App\Services\DashboardAnalyticsService;
use App\Services\ProfitService;
use App\Services\Settings\TenantSettings;
use App\Services\ShiftService;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Illuminate\Support\Facades\DB;

final class GetDashboardOverviewAction
{
    public function __construct(
        private readonly DashboardAnalyticsService $analyticsService,
        private readonly ProfitService $profitService,
        private readonly TenantClock $tenantClock,
        private readonly TenantSettings $tenantSettings,
        private readonly ShiftService $shiftService,
        private readonly DailyCashFlowService $dailyCashFlow,
    ) {}

    /**
     * Compute comprehensive dashboard performance metrics for SPA
     *
     * STOR-2: $storeId is the request's already access-checked store and is used as is (also a
     * closed branch the user explicitly selected). $allStores (a verified `X-Store-Id: all`)
     * aggregates every store. Without either, the legacy fallback picks the user's store.
     */
    public function execute(?User $user = null, ?int $storeId = null, bool $allStores = false): array
    {
        // SETG-2 (+ ext): "today" is the tenant's current business day (timezone + cutoff).
        $today = $this->tenantClock->today();

        // 1. Resolve Store
        $activeStore = null;
        if ($storeId) {
            $activeStore = Store::query()->whereKey($storeId)->first();
            if (! $activeStore instanceof Store) {
                // Never widen an unknown store id into "every store".
                $storeId = null;
            }
        }
        if ($allStores && ! $activeStore) {
            $storeId = null;
        } elseif (! $activeStore && $user) {
            $activeStore = $user->getCurrentStore();
            if ($activeStore) {
                $storeId = $activeStore->id;
            }
        }
        if (! $activeStore && ! $allStores) {
            $activeStore = Store::getMainStore();
            if ($activeStore) {
                $storeId = $activeStore->id;
            }
        }

        $storeFilter = $storeId ? (int) $storeId : null;

        // 2. 7-Day Trend & Peak Analytics
        $analytics = $this->analyticsService->getAnalytics(storeId: $storeFilter, trendDays: 7);

        // 3. Today's Invoices & Sales Metrics
        $todayInvoicesQuery = Invoice::with(['customer', 'store', 'user'])
            ->where('status', 'confirmed')
            ->whereDate('invoice_date', $today)
            ->when($storeFilter, fn ($q) => $q->where('store_id', $storeFilter));

        $todayInvoices = (clone $todayInvoicesQuery)->latest('id')->get();
        $totalSales = '0.000';
        $totalPaid = '0.000';
        $totalRemaining = '0.000';
        $cashSales = '0.000';
        $creditSales = '0.000';
        $partialSales = '0.000';
        $invoicesCount = $todayInvoices->count();

        foreach ($todayInvoices as $inv) {
            $totalSales = bcadd($totalSales, (string) $inv->net_total, 3);
            $totalPaid = bcadd($totalPaid, (string) $inv->paid_amount, 3);
            $totalRemaining = bcadd($totalRemaining, (string) $inv->remaining_amount, 3);

            if ($inv->payment_type === 'cash') {
                $cashSales = bcadd($cashSales, (string) $inv->net_total, 3);
            } elseif ($inv->payment_type === 'credit') {
                $creditSales = bcadd($creditSales, (string) $inv->net_total, 3);
            } elseif ($inv->payment_type === 'partial') {
                $partialSales = bcadd($partialSales, (string) $inv->net_total, 3);
            }
        }

        // 4. Cash collected today (BUG-01): the cash vouchers of the store(s) in scope only. The
        // invoice writes its own sale voucher, so adding invoices.paid_amount counted it twice.
        $cashFlow = $this->dailyCashFlow->forDate($today, $storeFilter);
        $totalCashCollected = $cashFlow['total_cash_in'];

        // 5. Operating Expenses Today (all methods; only the cash ones leave the drawer)
        $expensesQuery = Expense::whereDate('expense_date', $today)
            ->when($storeFilter, fn ($q) => $q->where('store_id', $storeFilter));

        $totalExpenses = (string) ($expensesQuery->sum('amount') ?: '0.000');
        $expensesCount = $expensesQuery->count();

        // 6. Supplier cash payments today and the day's net drawer cash
        $supplierPaid = $cashFlow['supplier_paid'];
        $netCashToday = $cashFlow['net_cash'];

        // SEC-1 (M6): profit, margin and receivable/payable totals are financial report data:
        // reports.view only (there is no dashboard.* permission). Without it they are null.
        $showFinancials = $user instanceof User && $user->can('reports.view');

        // 7. Customers & Suppliers Totals
        $customersCount = (int) Customer::where('is_active', true)->count();
        $suppliersCount = (int) Supplier::where('is_active', true)->count();
        $totalCustomersDebt = $showFinancials ? Decimal::normalize((string) (Customer::where('is_active', true)->sum('current_balance') ?: 0)) : null;
        $totalSuppliersDebt = $showFinancials ? Decimal::normalize((string) (Supplier::where('is_active', true)->sum('current_balance') ?: 0)) : null;

        // 8. Low Stock Items Radar (SETG-10: own minimum, else the tenant default threshold)
        $lowStockThreshold = $this->tenantSettings->lowStockDefaultThreshold();
        $lowStockItems = $this->tenantSettings->whereLowStock(Item::where('is_active', true))
            ->orderBy('current_stock', 'asc')
            ->take(8)
            ->get(['id', 'name', 'code', 'category', 'current_stock', 'min_stock_level', 'unit'])
            ->map(fn ($it) => [
                'id' => $it->id,
                'name' => $it->name,
                'code' => $it->code,
                'category' => $it->category,
                'current_stock' => Decimal::normalize((string) $it->current_stock),
                'min_stock' => Decimal::normalize((string) (bccomp((string) ($it->min_stock_level ?? '0'), '0', 3) > 0 ? $it->min_stock_level : $lowStockThreshold)),
                'unit' => $it->unit ?? 'كجم',
            ]);

        $lowStockCount = $this->tenantSettings->whereLowStock(Item::where('is_active', true))->count();

        // 9. Monthly Profits & Margin
        $startOfMonth = $this->tenantClock->businessNow()->startOfMonth()->toDateString();
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
            ->when($storeFilter, fn ($q) => $q->where('invoices.store_id', $storeFilter))
            ->groupBy('items.id', 'items.name', 'items.code', 'items.unit')
            ->orderByDesc('total_qty')
            ->take(6)
            ->get()
            ->map(fn ($t) => [
                'item_id' => $t->item_id,
                'name' => $t->item_name,
                'code' => $t->item_code,
                'unit' => $t->unit ?? 'كجم',
                'total_qty' => Decimal::normalize((string) $t->total_qty),
                'total_revenue' => Decimal::normalize((string) $t->total_revenue),
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
                    'id' => $shift->id,
                    'shift_number' => $shift->shift_number,
                    'user_name' => $shift->user?->name ?? __('common.cashier'),
                    'starting_cash' => Decimal::normalize((string) $shift->opening_cash_balance),
                    'current_cash' => Decimal::normalize((string) $this->shiftService->calculateShiftTotals($shift)['expected_cash_balance']),
                    'opened_at' => $shift->opened_at?->toDateTimeString(),
                ];
            }
        }

        // 12. Recent Invoices
        $recentInvoices = Invoice::with(['customer', 'store'])
            ->where('status', '!=', 'cancelled')
            ->when($storeFilter, fn ($q) => $q->where('store_id', $storeFilter))
            ->latest('id')
            ->take(8)
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_name' => $inv->customer?->name ?? __('pos.walk_in_customer'),
                'invoice_date' => $inv->invoice_date?->toDateString() ?? $inv->created_at?->toDateString(),
                'net_total' => Decimal::normalize((string) $inv->net_total),
                'paid_amount' => Decimal::normalize((string) $inv->paid_amount),
                'remaining_amount' => Decimal::normalize((string) $inv->remaining_amount),
                'payment_type' => $inv->payment_type,
                'status' => $inv->status,
            ]);

        return [
            'active_store' => $activeStore ? [
                'id' => $activeStore->id,
                'name' => $activeStore->name,
                'code' => $activeStore->code,
                'type' => $activeStore->type,
            ] : null,
            'metrics' => [
                'today_sales' => Decimal::normalize((string) $totalSales),
                'today_invoices_count' => $invoicesCount,
                'cash_sales' => Decimal::normalize((string) $cashSales),
                'credit_sales' => Decimal::normalize((string) $creditSales),
                'total_cash_collected' => Decimal::normalize((string) $totalCashCollected),
                'today_expenses' => Decimal::normalize((string) $totalExpenses),
                'expenses_count' => $expensesCount,
                'supplier_payments' => Decimal::normalize((string) $supplierPaid),
                'net_cash_today' => Decimal::normalize((string) $netCashToday),
                'customers_debt' => $totalCustomersDebt,
                'suppliers_debt' => $totalSuppliersDebt,
                'monthly_sales' => Decimal::normalize((string) $periodic['total_sales']),
                'monthly_gross_profit' => $showFinancials ? Decimal::normalize((string) $periodic['gross_profit']) : null,
                'monthly_margin' => $showFinancials ? Decimal::normalize((string) $periodic['margin_percentage'], 2) : null,
                'customers_count' => $customersCount,
                'suppliers_count' => $suppliersCount,
                'low_stock_count' => $lowStockCount,
            ],
            'can_view_financials' => $showFinancials,
            'analytics' => $analytics,
            'low_stock_items' => $lowStockItems,
            'top_selling_items' => $topSellingItems,
            'active_shift' => $activeShift,
            'recent_invoices' => $recentInvoices,
        ];
    }
}
