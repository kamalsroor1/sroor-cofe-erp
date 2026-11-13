<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\DTOs\Logs\ActivityLogStoreScope;
use App\Filters\ActivityLogs\ReadableStoresFilter;
use App\Models\ActivityLog;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Supplier;
use App\Services\Settings\TenantSettings;
use App\Support\ProfitVisibility;
use App\Support\TenantClock;

class GetDashboardApiOverviewAction
{
    public function __construct(
        private readonly TenantClock $tenantClock,
        private readonly TenantSettings $tenantSettings,
    ) {}

    /**
     * حساب وتجميع مؤشرات أداء لوحة التحكم لـ API الموبايل والويب
     *
     * LOG-01: $storeId is an access-checked branch. Without it (every store) the recent-logs feed
     * follows $logScope (the reader's branches; store-less logs only for all-store readers) and
     * shows nothing when no scope is given, so it can never fall back to every branch.
     */
    public function execute(?int $storeId = null, ?ActivityLogStoreScope $logScope = null): array
    {
        // SETG-2 (+ ext): "today" is the tenant's current business day (timezone + cutoff).
        $today = $this->tenantClock->today();

        // 1. Customers & Suppliers counts and debts
        $customersCount = Customer::count();
        $suppliersCount = Supplier::count();
        $totalReceivable = bcadd((string) (Customer::where('current_balance', '>', 0)->sum('current_balance') ?: '0'), '0.000', 3);
        $totalPayable = bcadd((string) (Supplier::where('current_balance', '>', 0)->sum('current_balance') ?: '0'), '0.000', 3);

        // 2. Today's Invoices & Revenue
        $todayInvoicesQuery = Invoice::whereDate('invoice_date', $today)
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId));

        $todayInvoices = $todayInvoicesQuery->get();

        $netSales = bcadd((string) ($todayInvoices->sum('net_total') ?: '0'), '0.000', 3);
        $totalPaid = bcadd((string) ($todayInvoices->sum('paid_amount') ?: '0'), '0.000', 3);
        $invoicesCount = $todayInvoices->count();

        // 3. Today's Expenses
        $todayExpenses = bcadd((string) (Expense::whereDate('expense_date', $today)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->sum('amount') ?: '0'), '0.000', 3);

        // 4. Today's COGS
        $todayInvoiceIds = $todayInvoices->pluck('id');
        $totalCogs = '0.000';
        if ($todayInvoiceIds->isNotEmpty()) {
            $totalCogs = bcadd((string) (InvoiceItem::whereIn('invoice_id', $todayInvoiceIds)
                ->selectRaw('SUM(quantity * cost_price) as total_cogs')
                ->value('total_cogs') ?: '0'), '0.000', 3);
        }

        $grossProfit = bcsub($netSales, $totalCogs, 3);
        $netProfit = bcsub($grossProfit, $todayExpenses, 3);
        $margin = '0.0';
        if (bccomp($netSales, '0.000', 3) > 0) {
            $margin = bcdiv(bcmul($netProfit, '100', 3), $netSales, 1);
        }

        // 5. Active Cash Shift
        $activeShift = CashShift::with('user')
            ->where('status', 'open')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->latest('opened_at')
            ->first();

        // 6. Low stock count (SETG-10: items without their own minimum use the tenant default threshold)
        $lowStockCount = $this->tenantSettings->whereLowStock(Item::active())->count();

        // 7. Recent 4 Invoices
        $recentInvoices = Invoice::with(['customer', 'store'])
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->take(4)
            ->get();

        // 8. Recent 4 Activity Logs
        $logsScope = $storeId !== null
            ? new ActivityLogStoreScope($logScope?->readableStoreIds, $storeId)
            : ($logScope ?? new ActivityLogStoreScope([]));
        $recentLogs = (new ReadableStoresFilter($logsScope))->apply(ActivityLog::with('user'))
            ->latest('id')
            ->take(4)
            ->get();

        return [
            'customers_count' => $customersCount,
            'suppliers_count' => $suppliersCount,
            'total_receivable' => $totalReceivable,
            'total_payable' => $totalPayable,
            'today_metrics' => [
                'net_sales' => $netSales,
                'total_paid' => $totalPaid,
                'total_expenses' => $todayExpenses,
                'invoices_count' => $invoicesCount,
            ] + (ProfitVisibility::allows() ? [
                'total_cogs' => $totalCogs,
                'net_profit' => $netProfit,
                'margin_percentage' => $margin,
            ] : []),
            'current_shift' => $activeShift,
            'has_active_shift' => (bool) $activeShift,
            'low_stock_count' => $lowStockCount,
            'recent_invoices' => $recentInvoices,
            'recent_logs' => $recentLogs,
        ];
    }
}
