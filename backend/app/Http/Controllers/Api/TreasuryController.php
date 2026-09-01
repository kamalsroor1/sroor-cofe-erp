<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use App\Services\TreasuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TreasuryController extends Controller
{
    public function __construct(
        private readonly TreasuryService $treasuryService
    ) {}

    /**
     * Treasury & Financial Summary for active store
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('daily_journal.view') && !$user->can('reports.view')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $storeId = (int)($request->header('X-Store-Id') ?: $request->input('store_id') ?: session('current_store_id') ?: 1);
        $today = now()->toDateString();

        // Today's Sales
        $todaySalesQuery = Invoice::query()->where('status', '!=', 'cancelled')->whereDate('invoice_date', $today);
        if ($storeId) {
            $todaySalesQuery->where('store_id', $storeId);
        }
        $todaySalesTotal = (string)$todaySalesQuery->sum('net_total');
        $todayCashCollected = (string)$todaySalesQuery->sum('paid_amount');

        // Today's Customer Receipts
        $todayReceipts = (string)Payment::whereNotNull('customer_id')->whereDate('payment_date', $today)->sum('amount');

        // Today's Supplier Payments
        $todaySupplierPaid = (string)Payment::whereNotNull('supplier_id')->whereDate('payment_date', $today)->sum('amount');

        // Today's Expenses
        $todayExpensesQuery = Expense::query()->whereDate('expense_date', $today);
        if ($storeId) {
            $todayExpensesQuery->where('store_id', $storeId);
        }
        $todayExpensesTotal = (string)$todayExpensesQuery->sum('amount');

        // Net Cash Flow Today = (Cash from sales + Customer receipts) - (Supplier paid + Expenses)
        $totalInflow = bcadd($todayCashCollected, $todayReceipts, 3);
        $totalOutflow = bcadd($todaySupplierPaid, $todayExpensesTotal, 3);
        $netCashToday = bcsub($totalInflow, $totalOutflow, 3);

        // All Time Receivables & Payables
        $totalReceivable = (string)Customer::where('current_balance', '>', 0)->sum('current_balance');
        $totalPayable    = (string)Supplier::where('current_balance', '>', 0)->sum('current_balance');

        // Active Shift
        $activeShift = CashShift::where('store_id', $storeId)->where('status', 'open')->latest('id')->first();

        // Liquidity Balances across all payment channels
        $balances = $this->treasuryService->getBalances($storeId);

        return response()->json([
            'success'  => true,
            'store_id' => $storeId,
            'today'    => [
                'date'              => $today,
                'sales_total'       => (float)$todaySalesTotal,
                'cash_collected'    => (float)$todayCashCollected,
                'customer_receipts' => (float)$todayReceipts,
                'total_inflow'      => (float)$totalInflow,
                'supplier_paid'     => (float)$todaySupplierPaid,
                'expenses_total'    => (float)$todayExpensesTotal,
                'total_outflow'     => (float)$totalOutflow,
                'net_cash'          => (float)$netCashToday,
            ],
            'balances' => [
                'total_receivable' => (float)$totalReceivable,
                'total_payable'    => (float)$totalPayable,
                'accounts'         => $balances,
            ],
            'active_shift' => $activeShift ? [
                'id'                   => $activeShift->id,
                'shift_number'         => $activeShift->shift_number ?? $activeShift->id,
                'opening_cash_balance' => (float)$activeShift->opening_cash_balance,
                'opened_at'            => $activeShift->opened_at,
            ] : null,
        ], 200);
    }
}
