<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Invoices\CancelSalesInvoiceAction;
use App\Actions\Invoices\CreateSalesInvoiceAction;
use App\Actions\Invoices\GetInvoiceDetailsAction;
use App\DTOs\Invoices\CancelInvoiceDTO;
use App\DTOs\Invoices\CreateInvoiceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelInvoiceRequest;
use App\Http\Requests\StoreSalesInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\InvoiceSummaryResource;
use App\Models\Invoice;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly CreateSalesInvoiceAction $createSalesInvoiceAction,
        private readonly CancelSalesInvoiceAction $cancelSalesInvoiceAction,
        private readonly GetInvoiceDetailsAction $getInvoiceDetailsAction
    ) {}

    /**
     * List Invoices for active store with filters and summary totals
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('invoices.view') && !$user->can('pos.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $rawStoreId = $request->input('store_id') ?: $request->header('X-Store-Id');
        $storeId = ($rawStoreId && $rawStoreId !== 'all' && is_numeric($rawStoreId) && (int)$rawStoreId > 0)
            ? (int)$rawStoreId
            : null;

        $search = trim((string)$request->input('search', ''));
        $status = (string)$request->input('status', 'all');
        $customerId = $request->input('customer_id');
        $paymentType = $request->input('payment_type');
        $paymentMethod = $request->input('payment_method');
        $fromDate = $request->input('from_date') ?: $request->input('from');
        $toDate = $request->input('to_date') ?: $request->input('to');
        $perPage = max(1, min(200, (int)$request->input('per_page', 15)));

        $query = Invoice::query()->with(['customer:id,name,phone,current_balance', 'user:id,name', 'store:id,name']);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($customerId && $customerId !== 'all') {
            $query->where('customer_id', (int)$customerId);
        }

        if ($status !== 'all' && $status !== '') {
            $query->where('status', $status);
        }

        if ($paymentType && $paymentType !== 'all') {
            $query->where('payment_type', $paymentType);
        }

        if ($paymentMethod && $paymentMethod !== 'all') {
            $query->where('payment_method', $paymentMethod);
        }

        if ($fromDate) {
            $query->whereDate('invoice_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('invoice_date', '<=', $toDate);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $totalSales = (float)(clone $query)->where('status', '!=', 'cancelled')->sum('net_total');
        $totalPaid = (float)(clone $query)->where('status', '!=', 'cancelled')->sum('paid_amount');
        $totalDue = (float)bcsub((string)$totalSales, (string)$totalPaid, 3);
        $totalCount = (int)(clone $query)->count();

        $invoices = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success'  => true,
            'data'     => InvoiceSummaryResource::collection($invoices->items())->resolve(),
            'meta'     => [
                'current_page' => $invoices->currentPage(),
                'last_page'    => $invoices->lastPage(),
                'per_page'     => $invoices->perPage(),
                'total'        => $invoices->total(),
            ],
            'summary'  => [
                'total_count' => $totalCount,
                'total_sales' => $totalSales,
                'total_paid'  => $totalPaid,
                'total_due'   => $totalDue,
            ],
        ], 200);
    }

    /**
     * Show single invoice details with items, payments, and WhatsApp share URL
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('invoices.view') && !$user->can('pos.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $result = $this->getInvoiceDetailsAction->execute($id);

        return response()->json([
            'success'  => true,
            'data'     => (new InvoiceResource($result['invoice']))->resolve(),
            'whatsapp' => $result['whatsapp'],
        ], 200);
    }

    /**
     * Create & confirm new Sales Invoice via Form Request
     */
    public function store(StoreSalesInvoiceRequest $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: auth()->user()?->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        $dto = CreateInvoiceDTO::fromArray($request->validated(), $storeId ? (int)$storeId : null);
        $invoice = $this->createSalesInvoiceAction->execute($dto);

        $details = $this->getInvoiceDetailsAction->execute($invoice->id);

        return response()->json([
            'success'  => true,
            'message'  => __('invoices.invoice_created') ?: "تم حفظ واعتماد الفاتورة رقم: {$invoice->invoice_number} بنجاح ✓",
            'data'     => (new InvoiceResource($details['invoice']))->resolve(),
            'whatsapp' => $details['whatsapp'],
        ], 201);
    }

    /**
     * Cancel Sales Invoice via Form Request
     */
    public function cancel(CancelInvoiceRequest $request, int $id): JsonResponse
    {
        $dto = CancelInvoiceDTO::fromArray($id, $request->validated());
        $cancelled = $this->cancelSalesInvoiceAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('invoices.invoice_cancelled') ?: "تم إلغاء الفاتورة رقم {$cancelled->invoice_number} بنجاح وعكس رصيد المخزن والحساب ✓",
            'data'    => (new InvoiceResource($cancelled))->resolve(),
        ], 200);
    }
}
