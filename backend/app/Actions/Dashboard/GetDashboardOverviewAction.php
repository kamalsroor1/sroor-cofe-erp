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
use App\Services\Reports\InvoiceAggregates;
use App\Services\Reports\ReportDateRange;
use App\Services\Reports\SalesReturnAggregates;
use App\Services\Settings\TenantSettings;
use App\Services\ShiftService;
use App\Support\CostVisibility;
use App\Support\DashboardCache;
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
        private readonly SalesReturnAggregates $salesReturns,
        private readonly InvoiceAggregates $invoices,
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

        // SEC-1 (M6): profit, margin and receivable/payable totals are financial report data:
        // reports.view only (there is no dashboard.* permission). Without it they are null.
        $showFinancials = $user instanceof User && $user->can('reports.view');

        // PERF-01: the aggregate blocks are cached for a few seconds per tenant, store scope,
        // permission set, business day and locale (see DashboardCache). The live blocks below
        // (active shift, recent invoices) are always fresh.
        $cacheKey = DashboardCache::key($storeFilter, $showFinancials, CostVisibility::allows($user), $today, app()->getLocale());
        $aggregates = DashboardCache::remember(
            $cacheKey,
            fn (): array => $this->aggregates($storeFilter, $today, $showFinancials),
        );

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
            'metrics' => $aggregates['metrics'],
            'can_view_financials' => $showFinancials,
            'analytics' => $aggregates['analytics'],
            'low_stock_items' => $aggregates['low_stock_items'],
            'top_selling_items' => $aggregates['top_selling_items'],
            'active_shift' => $activeShift,
            'recent_invoices' => $recentInvoices,
        ];
    }

    /**
     * The cacheable part of the dashboard: every figure that is an aggregate of the period.
     *
     * @return array{metrics: array<string, mixed>, analytics: array<string, mixed>, low_stock_items: list<array<string, mixed>>, top_selling_items: list<array<string, mixed>>}
     */
    private function aggregates(?int $storeFilter, string $today, bool $showFinancials): array
    {

        // 2. 7-Day Trend & Peak Analytics
        $analytics = $this->analyticsService->getAnalytics(storeId: $storeFilter, trendDays: 7);

        // 3. Today's Invoices & Sales Metrics (PERF-01: one grouped statement)
        $todayByType = $this->invoices->byColumn('payment_type', $today, $today, $storeFilter);
        $grossSales = '0.000';
        $invoicesCount = 0;
        foreach ($todayByType as $typeSold) {
            $grossSales = bcadd($grossSales, $typeSold['net_total'], 3);
            $invoicesCount += $typeSold['count'];
        }
        $cashSales = $todayByType['cash']['net_total'] ?? '0.000';
        $creditSales = $todayByType['credit']['net_total'] ?? '0.000';

        // BUG-01: today's sales are net of the sales returns booked today.
        $todayReturns = $this->salesReturns->totals($today, $today, $storeFilter);
        $totalSales = bcsub($grossSales, $todayReturns['value'], 3);

        // 4. Cash collected today (BUG-01): the cash vouchers of the store(s) in scope only. The
        // invoice writes its own sale voucher, so adding invoices.paid_amount counted it twice.
        $cashFlow = $this->dailyCashFlow->forDate($today, $storeFilter);
        $totalCashCollected = $cashFlow['total_cash_in'];

        // 5. Operating Expenses Today (all methods; only the cash ones leave the drawer)
        $expensesQuery = Expense::query()->when($storeFilter, fn ($q) => $q->where('store_id', $storeFilter));
        ReportDateRange::apply($expensesQuery, 'expense_date', $today, $today);
        $expenseRow = $expensesQuery->toBase()
            ->selectRaw('COUNT(*) as expenses_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as expenses_sum')
            ->first(); // an aggregate without GROUP BY always returns one row
        $totalExpenses = Decimal::normalize(InvoiceAggregates::scalar($expenseRow->expenses_sum));
        $expensesCount = (int) $expenseRow->expenses_count;

        // 6. Supplier cash payments today and the day's net drawer cash
        $supplierPaid = $cashFlow['supplier_paid'];
        $netCashToday = $cashFlow['net_cash'];

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
                'unit' => $it->unit ?? __('inventory.unit_weight_short'),
            ])
            ->values()
            ->all();

        $lowStockCount = $this->tenantSettings->whereLowStock(Item::where('is_active', true))->count();

        // 9. Monthly Profits & Margin
        $startOfMonth = $this->tenantClock->businessNow()->startOfMonth()->toDateString();
        $periodic = $this->profitService->getPeriodicProfits($startOfMonth, $today, $storeFilter);

        // 10. Top Selling Items this Month (BUG-01: net of this month's sales returns; the
        // returns are pre-grouped per item, so the join never multiplies the invoice lines)
        $monthReturns = $this->salesReturns->byItemSubquery($startOfMonth, $today, $storeFilter);
        $topSellingItems = InvoiceItem::select(
            'items.id as item_id',
            'items.name as item_name',
            'items.code as item_code',
            'items.unit',
            DB::raw('SUM(invoice_items.quantity) - COALESCE(MAX(item_returns.returned_qty), 0) as total_qty'),
            DB::raw('SUM(invoice_items.total_price) - COALESCE(MAX(item_returns.returned_value), 0) as total_revenue')
        )
            ->join('items', 'items.id', '=', 'invoice_items.item_id')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoinSub($monthReturns, 'item_returns', 'item_returns.returned_item_id', '=', 'items.id')
            ->where('invoices.status', 'confirmed')
            ->whereNull('invoices.deleted_at')
            ->where('invoices.invoice_date', '>=', $startOfMonth)
            ->when($storeFilter, fn ($q) => $q->where('invoices.store_id', $storeFilter))
            ->groupBy('items.id', 'items.name', 'items.code', 'items.unit')
            ->orderByDesc('total_qty')
            ->orderBy('items.id') // PERF-01: deterministic order of equal quantities on every engine
            ->take(6)
            ->get()
            ->map(fn ($t) => [
                'item_id' => $t->item_id,
                'name' => $t->item_name,
                'code' => $t->item_code,
                'unit' => $t->unit ?? __('inventory.unit_weight_short'),
                'total_qty' => Decimal::normalize((string) $t->total_qty),
                'total_revenue' => Decimal::normalize((string) $t->total_revenue),
            ])
            ->values()
            ->all();

        return [
            'metrics' => [
                // BUG-01: today_sales, monthly_sales, monthly_gross_profit and monthly_margin are NET of returns.
                'today_sales' => Decimal::normalize((string) $totalSales),
                'today_gross_sales' => Decimal::normalize((string) $grossSales),
                'today_returns' => $todayReturns['value'],
                'today_returns_count' => $todayReturns['count'],
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
                'monthly_gross_sales' => Decimal::normalize((string) $periodic['gross_sales']),
                'monthly_returns' => Decimal::normalize((string) $periodic['total_returns']),
                'monthly_gross_profit' => $showFinancials ? Decimal::normalize((string) $periodic['gross_profit']) : null,
                'monthly_margin' => $showFinancials ? Decimal::normalize((string) $periodic['margin_percentage'], 2) : null,
                'customers_count' => $customersCount,
                'suppliers_count' => $suppliersCount,
                'low_stock_count' => $lowStockCount,
            ],
            'analytics' => $analytics,
            'low_stock_items' => $lowStockItems,
            'top_selling_items' => $topSellingItems,
        ];
    }
}
