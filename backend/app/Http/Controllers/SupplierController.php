<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PaySupplierRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\PaymentService;
use App\Services\SupplierBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string)$request->input('search', ''));
        $debtStatus = (string)$request->input('debt_status', 'all');

        $query = Supplier::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($debtStatus === 'creditor') {
            $query->where('current_balance', '>', 0);
        } elseif ($debtStatus === 'zero') {
            $query->where('current_balance', '=', 0);
        }

        $suppliers = $query->latest('id')->paginate(20)->withQueryString();

        $totalPayable = (float)Supplier::where('current_balance', '>', 0)->sum('current_balance');
        $creditorsCount = Supplier::where('current_balance', '>', 0)->count();
        $totalSuppliersCount = Supplier::count();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers->through(fn($s) => [
                'id'                => $s->id,
                'name'              => $s->name,
                'company_name'      => $s->company_name,
                'phone'             => $s->phone,
                'address'           => $s->address,
                'current_balance'   => (float)$s->current_balance,
                'is_active'         => (bool)$s->is_active,
                'notes'             => $s->notes,
                'can_be_deleted'    => $s->canBeDeleted(),
                'deletion_blockers' => $s->getDeletionBlockers(),
            ]),
            'metrics' => [
                'total_payable'   => $totalPayable,
                'creditors_count' => $creditorsCount,
                'total_suppliers' => $totalSuppliersCount,
            ],
            'filters' => [
                'search'      => $search,
                'debt_status' => $debtStatus,
            ],
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            Supplier::create([
                'name'            => $validated['name'],
                'company_name'    => $validated['company_name'] ?? null,
                'phone'           => $validated['phone'] ?? null,
                'address'         => $validated['address'] ?? null,
                'current_balance' => $validated['opening_balance'] ?? '0.000',
                'is_active'       => true,
                'notes'           => $validated['notes'] ?? null,
            ]);
        });

        return redirect()->back()->with('success', __('contacts.supplier_added'));
    }

    public function update(UpdateSupplierRequest $request, int $id): RedirectResponse
    {
        $supplier = Supplier::findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($supplier, $validated) {
            $supplier->update($validated);
        });

        return redirect()->back()->with('success', __('contacts.supplier_updated'));
    }

    public function pay(PaySupplierRequest $request, int $id, PaymentService $paymentService): RedirectResponse
    {
        $supplier = Supplier::findOrFail($id);
        $validated = $request->validated();

        $paymentService->recordSupplierPayment([
            'supplier_id'    => $supplier->id,
            'amount'         => (string)$validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_date'   => $validated['payment_date'],
            'notes'          => $validated['notes'] ?? __('contacts.payment_voucher'),
        ]);

        return redirect()->back()->with('success', __('contacts.supplier_payment_recorded'));
    }

    public function toggleActive(int $id): RedirectResponse
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->is_active = !$supplier->is_active;
        $supplier->save();

        return redirect()->back()->with('success', __('contacts.supplier_status_updated'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $supplier = Supplier::findOrFail($id);

        if (!$supplier->canBeDeleted()) {
            return redirect()->back()->with('error', __('contacts.cannot_delete_has_balance'));
        }

        DB::transaction(function () use ($supplier) {
            $supplier->delete();
        });

        return redirect()->back()->with('success', __('contacts.supplier_deleted'));
    }

    public function statement(int $id, Request $request, SupplierBalanceService $balanceService): Response
    {
        $supplier = Supplier::findOrFail($id);
        $dateFrom = $request->input('from');
        $dateTo = $request->input('to');

        return Inertia::render('Suppliers/Statement', [
            'supplier' => [
                'id'              => $supplier->id,
                'name'            => $supplier->name,
                'company_name'    => $supplier->company_name,
                'phone'           => $supplier->phone,
                'address'         => $supplier->address,
                'current_balance' => (float)$supplier->current_balance,
                'initial_balance' => (float)$supplier->initial_balance,
            ],
            'filters' => [
                'from' => $dateFrom,
                'to'   => $dateTo,
            ],
            'ledger'  => Inertia::defer(fn() => $balanceService->getSupplierLedger($supplier, $dateFrom, $dateTo)['ledger'], 'supplierStatement'),
            'summary' => Inertia::defer(fn() => $balanceService->getSupplierLedger($supplier, $dateFrom, $dateTo)['summary'], 'supplierStatement'),
        ]);
    }
}