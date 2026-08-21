<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string)$request->input('search', ''));
        $category = (string)$request->input('category', 'all');
        $costCenter = (string)$request->input('cost_center', 'all');
        $paymentMethod = (string)$request->input('payment_method', 'all');
        $dateFrom = $request->input('from');
        $dateTo = $request->input('to');

        $query = Expense::with(['user', 'store']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category !== 'all') {
            $query->where('category', $category);
        }

        if ($costCenter !== 'all') {
            $query->where('cost_center', $costCenter);
        }

        if ($paymentMethod !== 'all') {
            $query->where('payment_method', $paymentMethod);
        }

        if ($dateFrom) {
            $query->whereDate('expense_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('expense_date', '<=', $dateTo);
        }

        $expenses = $query->latest('expense_date')->latest('id')->paginate(15)->withQueryString();

        $monthStart = now()->startOfMonth()->toDateString();
        $totalMonthExpenses = (float)Expense::whereDate('expense_date', '>=', $monthStart)->sum('amount');
        $totalCashExpenses = (float)Expense::where('payment_method', 'cash')->whereDate('expense_date', '>=', $monthStart)->sum('amount');
        $totalFilteredExpenses = (float)$query->sum('amount');

        $costCentersList = [
            'operational' => 'مصاريف تشغيلية ونثريات',
            'rent'        => 'إيجارات مقرات وفروع',
            'utilities'   => 'كهرباء ومياه وغاز ومرافق',
            'salaries'    => 'رواتب وعمالة وإكراميات',
            'vehicles'    => 'وقود وزيوت وصيانة سيارات',
            'maintenance' => 'صيانة معدات وديكورات',
            'packaging'   => 'مطبوعات وكراتين وتعبئة',
            'hospitality' => 'ضيافة ونظافة وبوفيه',
            'marketing'   => 'تسويق وإعلانات ودعاية',
            'shipping'    => 'شحن ونولون وتوصيل خارجي',
        ];

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses->through(fn($e) => [
                'id' => $e->id,
                'expense_number' => $e->expense_number,
                'title' => $e->title,
                'category' => $e->category,
                'cost_center' => $e->cost_center,
                'cost_center_label' => $e->cost_center_label,
                'amount' => (float)$e->amount,
                'expense_date' => $e->expense_date ? $e->expense_date->toDateString() : $e->created_at->toDateString(),
                'payment_method' => $e->payment_method,
                'user_name' => $e->user?->name,
                'store_name' => $e->store?->name,
                'notes' => $e->notes,
            ]),
            'metrics' => [
                'total_month' => $totalMonthExpenses,
                'total_cash' => $totalCashExpenses,
                'total_filtered' => $totalFilteredExpenses,
            ],
            'cost_centers' => $costCentersList,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'cost_center' => $costCenter,
                'payment_method' => $paymentMethod,
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $storeId = $request->session()->get('active_store_id') ?: Store::first()?->id;

        DB::transaction(function () use ($validated, $storeId) {
            $expCount = Expense::whereDate('created_at', now()->toDateString())->count() + 1;

            Expense::create([
                'expense_number' => 'EXP-' . date('ymd') . '-' . str_pad((string)$expCount, 3, '0', STR_PAD_LEFT),
                'title'          => $validated['title'],
                'category'       => $validated['category'],
                'cost_center'    => $validated['cost_center'],
                'amount'         => $validated['amount'],
                'expense_date'   => $validated['expense_date'],
                'payment_method' => $validated['payment_method'],
                'created_by'     => auth()->id(),
                'store_id'       => $storeId,
                'notes'          => $validated['notes'] ?? null,
            ]);
        });

        return redirect()->back()->with('success', __('expenses.recorded_success'));
    }

    public function update(UpdateExpenseRequest $request, int $id): RedirectResponse
    {
        $expense = Expense::findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($expense, $validated) {
            $expense->update($validated);
        });

        return redirect()->back()->with('success', __('expenses.updated_success'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();

        return redirect()->back()->with('success', __('expenses.deleted_success'));
    }
}