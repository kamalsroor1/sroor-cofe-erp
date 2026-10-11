<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerPaymentReceiptRequest;
use App\Http\Requests\StoreSupplierPaymentVoucherRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Supplier;
use App\Services\PaymentService;
use App\Support\ActiveStore;
use App\Support\SafeJsonError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    /**
     * List payment vouchers with filters and financial summary
     */
    public function index(Request $request, ActiveStore $activeStore): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('daily_journal.view') && ! $user->can('customers.manage') && ! $user->can('suppliers.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $type = (string) $request->input('type', 'all'); // customer, supplier, all
        $customerId = $request->input('customer_id');
        $supplierId = $request->input('supplier_id');
        $fromDate = $request->input('from_date') ?: $request->input('from');
        $toDate = $request->input('to_date') ?: $request->input('to');
        $perPage = max(1, min(200, (int) $request->input('per_page', 20)));

        // STOR-2: the active store (X-Store-Id); a query store_id is an access-checked branch
        // filter (403). Null (every store) only through a verified `all`. A voucher belongs to
        // payments.store_id, legacy rows without it to their invoice / purchase store.
        $storeId = $activeStore->readFilter($request);

        $query = Payment::query()->with(['customer:id,name,phone', 'supplier:id,name,phone', 'user:id,name'])
            ->when($storeId !== null, fn ($q) => $q->forStore((int) $storeId));

        if ($type === 'customer') {
            $query->whereNotNull('customer_id');
        } elseif ($type === 'supplier') {
            $query->whereNotNull('supplier_id');
        }

        if ($customerId && $customerId !== 'all') {
            $query->where('customer_id', (int) $customerId);
        }
        if ($supplierId && $supplierId !== 'all') {
            $query->where('supplier_id', (int) $supplierId);
        }

        if ($fromDate) {
            $query->whereDate('payment_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('payment_date', '<=', $toDate);
        }

        $payments = $query->latest('id')->paginate($perPage);

        $totalCollections = (string) (Payment::whereNotNull('customer_id')->when($storeId !== null, fn ($q) => $q->forStore((int) $storeId))->sum('amount') ?: '0.000');
        $totalDisbursements = (string) (Payment::whereNotNull('supplier_id')->when($storeId !== null, fn ($q) => $q->forStore((int) $storeId))->sum('amount') ?: '0.000');

        return response()->json([
            'success' => true,
            'summary' => [
                'total_collections' => (float) $totalCollections,
                'total_disbursements' => (float) $totalDisbursements,
            ],
            'data' => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ], 200);
    }

    /**
     * Create Customer Receipt Voucher (سند قبض / تحصيل)
     */
    public function customerReceipt(StoreCustomerPaymentReceiptRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $payment = $this->paymentService->recordCustomerPayment($validated);
            $customer = Customer::find($validated['customer_id']);

            return response()->json([
                'success' => true,
                'message' => __('contacts.receipt_recorded_success', [
                    'amount' => number_format((float) $validated['amount'], 2),
                    'currency' => __('common.currency'),
                ]),
                'data' => $payment,
                'customer_current_balance' => (float) ($customer?->current_balance ?? 0),
            ], 201);
        } catch (Throwable $e) {
            // SEC-1 (L4): 404/422/403 pass through; anything else is logged, never echoed.
            return SafeJsonError::respond($e, 'contacts.receipt_record_failed_generic');
        }
    }

    /**
     * Create Supplier Disbursement Voucher (سند صرف / سداد مورد)
     */
    public function supplierVoucher(StoreSupplierPaymentVoucherRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $payment = $this->paymentService->recordSupplierPayment($validated);
            $supplier = Supplier::find($validated['supplier_id']);

            return response()->json([
                'success' => true,
                'message' => __('contacts.disbursement_recorded_success', [
                    'amount' => number_format((float) $validated['amount'], 2),
                    'currency' => __('common.currency'),
                ]),
                'data' => $payment,
                'supplier_current_balance' => (float) ($supplier?->current_balance ?? 0),
            ], 201);
        } catch (Throwable $e) {
            // SEC-1 (L4): 404/422/403 pass through; anything else is logged, never echoed.
            return SafeJsonError::respond($e, 'contacts.disbursement_record_failed_generic');
        }
    }
}
