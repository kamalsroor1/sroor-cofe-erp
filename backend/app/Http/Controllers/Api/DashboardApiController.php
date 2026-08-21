<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Expense;
use App\Models\CashShift;
use App\Models\Item;
use App\Models\ActivityLog;
use App\Models\Store;
use Carbon\Carbon;

class DashboardApiController extends Controller
{
    /**
     * Get complete consolidated dashboard data in 1 single fast query
     */
    public function index(Request $request)
    {
        $today = Carbon::today()->toDateString();
        $storeId = (int)($request->header('X-Store-Id') ?: $request->input('store_id') ?: session('current_store_id') ?: 1);

        // 1. Customers & Suppliers counts and debts
        $customersCount = Customer::count();
        $suppliersCount = Supplier::count();
        $totalReceivable = (string)(Customer::where('current_balance', '>', 0)->sum('current_balance') ?: '0.000');
        $totalPayable = (string)(Supplier::where('current_balance', '>', 0)->sum('current_balance') ?: '0.000');

        // 2. Today's Invoices & Revenue
        $todayInvoices = Invoice::whereDate('invoice_date', $today)
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->get();

        $grossSales = $todayInvoices->sum('total_amount') ?: 0;
        $totalDiscount = $todayInvoices->sum('discount_amount') ?: 0;
        $netSales = bcsub((string)$grossSales, (string)$totalDiscount, 3);
        $totalPaid = (string)($todayInvoices->sum('paid_amount') ?: '0.000');
        $invoicesCount = $todayInvoices->count();

        // 3. Today's Expenses
        $todayExpenses = Expense::whereDate('expense_date', $today)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->sum('amount') ?: 0;

        // 4. Today's COGS
        $todayInvoiceIds = $todayInvoices->pluck('id');
        $totalCogs = \App\Models\InvoiceItem::whereIn('invoice_id', $todayInvoiceIds)
            ->selectRaw('SUM(quantity * cost_price) as total_cogs')
            ->value('total_cogs') ?: '0.000';

        $grossProfit = bcsub($netSales, (string)$totalCogs, 3);
        $netProfit = bcsub($grossProfit, (string)$todayExpenses, 3);
        $margin = bccomp($netSales, '0.000', 3) > 0 
            ? round((float)bcdiv(bcmul($netProfit, '100', 3), $netSales, 3), 1) 
            : 0;

        // 5. Active Cash Shift
        $activeShift = CashShift::with('user')
            ->where('status', 'open')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('opened_at')
            ->first();

        // 6. Low stock count
        $lowStockCount = Item::active()->lowStock()->count();

        // 7. Recent 4 Invoices
        $recentInvoices = Invoice::with('customer')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->take(4)
            ->get()
            ->map(function ($inv) {
                return [
                    'id'             => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'customer_name'  => $inv->customer?->name ?? 'عميل نقدي',
                    'total_amount'   => (string)$inv->total_amount,
                    'payment_status' => $inv->payment_status,
                    'invoice_date'   => $inv->invoice_date,
                ];
            });

        // 8. Recent 4 Activity Logs
        $recentLogs = ActivityLog::with('user')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->take(4)
            ->get()
            ->map(function ($log) {
                $badge = $log->module_badge;
                return [
                    'id'          => $log->id,
                    'description' => $log->description,
                    'module_icon' => $badge['icon'] ?? '⚙️',
                    'user_name'   => $log->user?->name ?? 'النظام',
                    'time_ago'    => $log->created_at?->diffForHumans(),
                ];
            });

        return response()->json([
            'success'          => true,
            'customers_count'  => $customersCount,
            'suppliers_count'  => $suppliersCount,
            'total_receivable' => $totalReceivable,
            'total_payable'    => $totalPayable,
            'today_metrics'    => [
                'net_sales'         => (string)$netSales,
                'total_paid'        => (string)$totalPaid,
                'total_cogs'        => (string)$totalCogs,
                'total_expenses'    => (string)$todayExpenses,
                'net_profit'        => (string)$netProfit,
                'margin_percentage' => (string)$margin,
                'invoices_count'    => $invoicesCount,
            ],
            'current_shift'    => $activeShift,
            'has_active_shift' => (bool)$activeShift,
            'low_stock_count'  => $lowStockCount,
            'recent_invoices'  => $recentInvoices,
            'recent_logs'      => $recentLogs,
        ]);
    }
}
