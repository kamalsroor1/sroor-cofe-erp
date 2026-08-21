<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Expense;
use App\Models\CashShift;
use App\Models\Item;
use App\Models\InvoiceItem;
use App\Models\ActivityLog;
use Carbon\Carbon;

class GetDashboardApiOverviewAction
{
    /**
     * حساب وتجميع مؤشرات أداء لوحة التحكم لـ API الموبايل والويب
     */
    public function execute(?int $storeId = null): array
    {
        $today = Carbon::today()->toDateString();

        // 1. Customers & Suppliers counts and debts
        $customersCount = Customer::count();
        $suppliersCount = Supplier::count();
        $totalReceivable = bcadd((string)(Customer::where('current_balance', '>', 0)->sum('current_balance') ?: '0'), '0.000', 3);
        $totalPayable = bcadd((string)(Supplier::where('current_balance', '>', 0)->sum('current_balance') ?: '0'), '0.000', 3);

        // 2. Today's Invoices & Revenue
        $todayInvoicesQuery = Invoice::whereDate('invoice_date', $today)
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId));

        $todayInvoices = $todayInvoicesQuery->get();

        $netSales = bcadd((string)($todayInvoices->sum('net_total') ?: '0'), '0.000', 3);
        $totalPaid = bcadd((string)($todayInvoices->sum('paid_amount') ?: '0'), '0.000', 3);
        $invoicesCount = $todayInvoices->count();

        // 3. Today's Expenses
        $todayExpenses = bcadd((string)(Expense::whereDate('expense_date', $today)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->sum('amount') ?: '0'), '0.000', 3);

        // 4. Today's COGS
        $todayInvoiceIds = $todayInvoices->pluck('id');
        $totalCogs = '0.000';
        if ($todayInvoiceIds->isNotEmpty()) {
            $totalCogs = bcadd((string)(InvoiceItem::whereIn('invoice_id', $todayInvoiceIds)
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
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('opened_at')
            ->first();

        // 6. Low stock count
        $lowStockCount = Item::active()->lowStock()->count();

        // 7. Recent 4 Invoices
        $recentInvoices = Invoice::with(['customer', 'store'])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->take(4)
            ->get();

        // 8. Recent 4 Activity Logs
        $recentLogs = ActivityLog::with('user')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->take(4)
            ->get();

        return [
            'customers_count'  => $customersCount,
            'suppliers_count'  => $suppliersCount,
            'total_receivable' => $totalReceivable,
            'total_payable'    => $totalPayable,
            'today_metrics'    => [
                'net_sales'         => $netSales,
                'total_paid'        => $totalPaid,
                'total_cogs'        => $totalCogs,
                'total_expenses'    => $todayExpenses,
                'net_profit'        => $netProfit,
                'margin_percentage' => $margin,
                'invoices_count'    => $invoicesCount,
            ],
            'current_shift'    => $activeShift,
            'has_active_shift' => (bool)$activeShift,
            'low_stock_count'  => $lowStockCount,
            'recent_invoices'  => $recentInvoices,
            'recent_logs'      => $recentLogs,
        ];
    }
}
