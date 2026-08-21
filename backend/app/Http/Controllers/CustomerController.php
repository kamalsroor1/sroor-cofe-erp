<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CollectCustomerPaymentRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerBalanceService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string)$request->input('search', ''));
        $debtStatus = (string)$request->input('debt_status', 'all');

        $query = Customer::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('tax_number', 'like', "%{$search}%");
            });
        }

        if ($debtStatus === 'debtor') {
            $query->where('current_balance', '>', 0);
        } elseif ($debtStatus === 'zero') {
            $query->where('current_balance', '=', 0);
        } elseif ($debtStatus === 'creditor') {
            $query->where('current_balance', '<', 0);
        }

        $customers = $query->latest('id')->paginate(20)->withQueryString();

        $totalDebt = (float)Customer::where('current_balance', '>', 0)->sum('current_balance');
        $debtorsCount = Customer::where('current_balance', '>', 0)->count();
        $totalCustomersCount = Customer::count();

        return Inertia::render('Customers/Index', [
            'customers' => $customers->through(fn($c) => [
                'id'                => $c->id,
                'name'              => $c->name,
                'phone'             => $c->phone,
                'address'           => $c->address,
                'tax_number'        => $c->tax_number,
                'current_balance'   => (float)$c->current_balance,
                'is_active'         => (bool)$c->is_active,
                'notes'             => $c->notes,
                'can_be_deleted'    => $c->canBeDeleted(),
                'deletion_blockers' => $c->getDeletionBlockers(),
            ]),
            'metrics' => [
                'total_debt'      => $totalDebt,
                'debtors_count'   => $debtorsCount,
                'total_customers' => $totalCustomersCount,
            ],
            'filters' => [
                'search'      => $search,
                'debt_status' => $debtStatus,
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            Customer::create([
                'name'            => $validated['name'],
                'phone'           => $validated['phone'] ?? null,
                'address'         => $validated['address'] ?? null,
                'tax_number'      => $validated['tax_number'] ?? null,
                'current_balance' => $validated['opening_balance'] ?? '0.000',
                'is_active'       => true,
                'notes'           => $validated['notes'] ?? null,
            ]);
        });

        return redirect()->back()->with('success', __('contacts.customer_added'));
    }

    public function update(UpdateCustomerRequest $request, int $id): RedirectResponse
    {
        $customer = Customer::findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($customer, $validated) {
            $customer->update($validated);
        });

        return redirect()->back()->with('success', __('contacts.customer_updated'));
    }

    public function collectPayment(CollectCustomerPaymentRequest $request, int $id, PaymentService $paymentService): RedirectResponse
    {
        $customer = Customer::findOrFail($id);
        $validated = $request->validated();

        $paymentService->recordCustomerPayment([
            'customer_id'    => $customer->id,
            'amount'         => (string)$validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_date'   => $validated['payment_date'],
            'notes'          => $validated['notes'] ?? __('contacts.receipt_voucher'),
        ]);

        return redirect()->back()->with('success', __('contacts.payment_recorded'));
    }

    public function statement(Request $request, int $id, CustomerBalanceService $balanceService): Response
    {
        $customer = Customer::findOrFail($id);
        $dateFrom = $request->input('from');
        $dateTo = $request->input('to');

        return Inertia::render('Customers/Statement', [
            'customer' => [
                'id'              => $customer->id,
                'name'            => $customer->name,
                'phone'           => $customer->phone,
                'address'         => $customer->address,
                'tax_number'      => $customer->tax_number,
                'current_balance' => (float)$customer->current_balance,
            ],
            'filters' => [
                'from' => $dateFrom,
                'to'   => $dateTo,
            ],
            'ledger' => Inertia::defer(function () use ($balanceService, $customer, $dateFrom, $dateTo) {
                $ledgerData = $balanceService->getCustomerLedger($customer, $dateFrom, $dateTo);
                return array_map(function ($row) {
                    return [
                        'date'          => $row['date'],
                        'type'          => $row['type'],
                        'ref_number'    => $row['ref_number'],
                        'debit'         => (float)$row['debit'],
                        'credit'        => (float)$row['credit'],
                        'notes'         => $row['notes'],
                        'balance_after' => (float)$row['balance_after'],
                    ];
                }, $ledgerData['entries']);
            }, 'customerStatement'),
            'summary' => Inertia::defer(function () use ($balanceService, $customer, $dateFrom, $dateTo) {
                $ledgerData = $balanceService->getCustomerLedger($customer, $dateFrom, $dateTo);
                return [
                    'total_debit'     => (float)collect($ledgerData['entries'])->sum(fn($r) => (float)$r['debit']),
                    'total_credit'    => (float)collect($ledgerData['entries'])->sum(fn($r) => (float)$r['credit']),
                    'current_balance' => (float)$customer->current_balance,
                ];
            }, 'customerStatement'),
        ]);
    }

    public function toggleActive(int $id): RedirectResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->is_active = !$customer->is_active;
        $customer->save();

        return redirect()->back()->with('success', __('contacts.customer_status_updated'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $customer = Customer::findOrFail($id);

        if (!$customer->canBeDeleted()) {
            return redirect()->back()->with('error', __('contacts.cannot_delete_has_balance'));
        }

        DB::transaction(function () use ($customer) {
            $customer->delete();
        });

        return redirect()->back()->with('success', __('contacts.customer_deleted'));
    }
}
