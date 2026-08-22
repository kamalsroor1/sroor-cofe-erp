<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Customers\CollectCustomerPaymentAction;
use App\Actions\Customers\CreateCustomerAction;
use App\Actions\Customers\DeleteCustomerAction;
use App\Actions\Customers\GetCustomerStatementAction;
use App\Actions\Customers\ToggleCustomerActiveAction;
use App\Actions\Customers\UpdateCustomerAction;
use App\DTOs\Customers\CollectCustomerPaymentDTO;
use App\DTOs\Customers\CustomerDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\CollectCustomerPaymentRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerController extends Controller
{
    public function __construct(
        private readonly CreateCustomerAction $createCustomerAction,
        private readonly UpdateCustomerAction $updateCustomerAction,
        private readonly DeleteCustomerAction $deleteCustomerAction,
        private readonly ToggleCustomerActiveAction $toggleCustomerActiveAction,
        private readonly CollectCustomerPaymentAction $collectCustomerPaymentAction,
        private readonly GetCustomerStatementAction $getCustomerStatementAction
    ) {}

    /**
     * List / Search Customers with filters & metrics
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string)$request->input('search', ''));
        $debtStatus = (string)$request->input('debt_status', 'all');
        $status = (string)$request->input('status', 'all');
        $perPage = (int)$request->input('per_page', 20);

        $query = Customer::withCount(['invoices', 'payments']);

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

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $customers = $query->latest('id')->paginate($perPage);

        $totalDebt = (float)Customer::where('current_balance', '>', 0)->sum('current_balance');
        $debtorsCount = Customer::where('current_balance', '>', 0)->count();
        $totalCustomersCount = Customer::count();

        return response()->json([
            'success' => true,
            'data'    => CustomerResource::collection($customers->items())->resolve(),
            'meta'    => [
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
                'per_page'     => $customers->perPage(),
                'total'        => $customers->total(),
            ],
            'summary' => [
                'total_debt'          => $totalDebt,
                'debtors_count'       => $debtorsCount,
                'total_customers'     => $totalCustomersCount,
            ],
        ], 200);
    }

    /**
     * Store a newly created customer
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $dto = CustomerDTO::fromArray($request->validated());
        $customer = $this->createCustomerAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('contacts.customer_added') ?: 'تم إضافة العميل بنجاح',
            'data'    => (new CustomerResource($customer))->resolve(),
        ], 201);
    }

    /**
     * Display the specified customer
     */
    public function show(int $id): JsonResponse
    {
        $customer = Customer::withCount(['invoices', 'payments'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => (new CustomerResource($customer))->resolve(),
        ], 200);
    }

    /**
     * Update the specified customer
     */
    public function update(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $dto = CustomerDTO::fromArray($request->validated());
        $updatedCustomer = $this->updateCustomerAction->execute($customer, $dto);

        return response()->json([
            'success' => true,
            'message' => __('contacts.customer_updated') ?: 'تم تعديل بيانات العميل بنجاح',
            'data'    => (new CustomerResource($updatedCustomer))->resolve(),
        ], 200);
    }

    /**
     * Collect / Record Customer Payment
     */
    public function collectPayment(CollectCustomerPaymentRequest $request, int $id): JsonResponse
    {
        $dto = CollectCustomerPaymentDTO::fromArray($id, $request->validated());
        $result = $this->collectCustomerPaymentAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('contacts.payment_recorded') ?: 'تم تسجيل سند التحصيل بنجاح',
            'data'    => [
                'customer' => (new CustomerResource($result['customer']))->resolve(),
                'payment'  => $result['payment'],
            ],
        ], 200);
    }

    /**
     * Get Customer Statement of Account (Ledger)
     */
    public function statement(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $fromDate = $request->query('from_date') ?: $request->query('from');
        $toDate = $request->query('to_date') ?: $request->query('to');

        $data = $this->getCustomerStatementAction->execute($customer, $fromDate, $toDate);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ], 200);
    }

    /**
     * Toggle Customer Active Status
     */
    public function toggleActive(int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $toggled = $this->toggleCustomerActiveAction->execute($customer);

        return response()->json([
            'success' => true,
            'message' => __('contacts.customer_status_updated') ?: 'تم تحديث حالة العميل بنجاح',
            'data'    => (new CustomerResource($toggled))->resolve(),
        ], 200);
    }

    /**
     * Delete the specified customer
     */
    public function destroy(int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $this->deleteCustomerAction->execute($customer);

        return response()->json([
            'success' => true,
            'message' => __('contacts.customer_deleted') ?: 'تم حذف العميل بنجاح',
        ], 200);
    }
}
