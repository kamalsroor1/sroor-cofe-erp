<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CloseShiftRequest;
use App\Http\Requests\OpenShiftRequest;
use App\Http\Requests\StoreDailyJournalExpenseRequest;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class DailyJournalController extends Controller
{
    public function index(Request $request): Response
    {
        $date = (string)$request->input('date', now()->toDateString());
        $storeId = $request->session()->get('active_store_id') ?: Store::first()?->id;

        // Current Active Shift
        $activeShift = CashShift::where('status', 'open')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->first();

        // Invoices on selected date
        $invoices = Invoice::with('customer')
            ->whereDate('invoice_date', $date)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->get();

        $totalSales = (float)$invoices->where('status', 'confirmed')->sum('net_total');
        $cashSales = (float)$invoices->where('status', 'confirmed')->where('payment_method', 'cash')->sum('paid_amount');
        $creditSales = (float)$invoices->where('status', 'confirmed')->where('payment_method', 'credit')->sum('net_total');
        $partialSales = (float)$invoices->where('status', 'confirmed')->where('payment_method', 'partial')->sum('paid_amount');

        // Customer Payments on selected date
        $customerPayments = (float)Payment::whereNotNull('customer_id')
            ->whereDate('payment_date', $date)
            ->where('payment_method', 'cash')
            ->sum('amount');

        // Supplier Payments on selected date
        $supplierPayments = (float)Payment::whereNotNull('supplier_id')
            ->whereDate('payment_date', $date)
            ->where('payment_method', 'cash')
            ->sum('amount');

        // Expenses on selected date
        $expenses = Expense::whereDate('expense_date', $date)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->get();
        $totalExpenses = (float)$expenses->sum('amount');

        // Net Cash calculations
        $totalCashIn = $cashSales + $partialSales + $customerPayments;
        $totalCashOut = $supplierPayments + $totalExpenses;
        $netCashToday = $totalCashIn - $totalCashOut;

        $openingCashBalance = $activeShift ? (float)$activeShift->opening_cash_balance : 0.0;
        $expectedCashInDrawer = $openingCashBalance + $netCashToday;

        return Inertia::render('DailyJournal/Index', [
            'date' => $date,
            'active_shift' => $activeShift ? [
                'id' => $activeShift->id,
                'shift_number' => $activeShift->shift_number,
                'status' => $activeShift->status,
                'opened_at' => $activeShift->opened_at->format('Y-m-d H:i'),
                'opening_cash_balance' => (float)$activeShift->opening_cash_balance,
                'user_name' => $activeShift->user?->name,
            ] : null,
            'summary' => Inertia::defer(fn() => [
                'total_sales' => $totalSales,
                'cash_sales' => $cashSales + $partialSales,
                'credit_sales' => $creditSales,
                'customer_payments' => $customerPayments,
                'total_cash_in' => $totalCashIn,
                'supplier_payments' => $supplierPayments,
                'total_expenses' => $totalExpenses,
                'total_cash_out' => $totalCashOut,
                'net_cash_today' => $netCashToday,
                'opening_cash_balance' => $openingCashBalance,
                'expected_cash_in_drawer' => $expectedCashInDrawer,
            ], 'dailyJournalData'),
            'invoices' => Inertia::defer(fn() => $invoices->map(fn($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_name' => $inv->customer?->name ?: 'عميل نقدي سريع',
                'net_total' => (float)$inv->net_total,
                'paid_amount' => (float)$inv->paid_amount,
                'remaining_amount' => (float)$inv->remaining_amount,
                'payment_method' => $inv->payment_method,
                'status' => $inv->status,
                'time' => $inv->created_at->format('H:i A'),
            ]), 'dailyJournalData'),
            'expenses' => Inertia::defer(fn() => $expenses->map(fn($e) => [
                'id' => $e->id,
                'expense_number' => $e->expense_number,
                'title' => $e->title,
                'category' => $e->category,
                'cost_center_label' => $e->cost_center_label,
                'amount' => (float)$e->amount,
                'payment_method' => $e->payment_method,
            ]), 'dailyJournalData'),
        ]);
    }

    public function openShift(OpenShiftRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $storeId = $request->session()->get('active_store_id') ?: Store::first()?->id;

        DB::transaction(function () use ($validated, $storeId) {
            $shiftCount = CashShift::whereDate('created_at', now()->toDateString())->count() + 1;

            CashShift::create([
                'user_id' => auth()->id(),
                'store_id' => $storeId,
                'shift_number' => 'SHF-' . date('ymd') . '-' . str_pad((string)$shiftCount, 2, '0', STR_PAD_LEFT),
                'status' => 'open',
                'opened_at' => now(),
                'opening_cash_balance' => $validated['opening_cash_balance'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return redirect()->back()->with('success', __('treasury.shift_opened_success'));
    }

    public function closeShift(CloseShiftRequest $request, int $id): RedirectResponse
    {
        $shift = CashShift::where('status', 'open')->findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($shift, $validated) {
            $actual = (string)$validated['actual_cash_balance'];
            
            // Compute expected drawer cash
            $cashSales = Invoice::where('status', 'confirmed')
                ->where('created_at', '>=', $shift->opened_at)
                ->where('payment_method', 'cash')
                ->sum('paid_amount');

            $custPayments = Payment::whereNotNull('customer_id')
                ->where('payment_date', '>=', $shift->opened_at)
                ->where('payment_method', 'cash')
                ->sum('amount');

            $suppPayments = Payment::whereNotNull('supplier_id')
                ->where('payment_date', '>=', $shift->opened_at)
                ->where('payment_method', 'cash')
                ->sum('amount');

            $expenses = Expense::where('created_at', '>=', $shift->opened_at)
                ->sum('amount');

            $expected = bcadd((string)$shift->opening_cash_balance, (string)($cashSales + $custPayments), 3);
            $expected = bcsub($expected, (string)($suppPayments + $expenses), 3);

            $difference = bcsub($actual, $expected, 3);

            $shift->update([
                'status' => 'closed',
                'closed_at' => now(),
                'actual_cash_balance' => $actual,
                'expected_cash_balance' => $expected,
                'cash_difference' => $difference,
                'notes' => $validated['notes'] ?? $shift->notes,
            ]);
        });

        return redirect()->back()->with('success', __('treasury.shift_closed_success'));
    }

    public function storeExpense(StoreDailyJournalExpenseRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $storeId = $request->session()->get('active_store_id') ?: Store::first()?->id;

        DB::transaction(function () use ($validated, $storeId) {
            $expCount = Expense::whereDate('created_at', now()->toDateString())->count() + 1;

            Expense::create([
                'expense_number' => 'EXP-' . date('ymd') . '-' . str_pad((string)$expCount, 3, '0', STR_PAD_LEFT),
                'title' => $validated['title'],
                'amount' => $validated['amount'],
                'cost_center' => $validated['cost_center'],
                'category' => 'تشغيلي',
                'expense_date' => now()->toDateString(),
                'payment_method' => $validated['payment_method'],
                'user_id' => auth()->id(),
                'store_id' => $storeId,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return redirect()->back()->with('success', __('expenses.recorded_success'));
    }
}