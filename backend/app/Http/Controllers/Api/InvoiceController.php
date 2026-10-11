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
use App\Models\User;
use App\Support\ActiveStore;
use App\Support\ClientStoreGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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
    public function index(Request $request, ActiveStore $activeStore): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('invoices.view') && ! $user->can('pos.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        // STOR-2: the active store (X-Store-Id); a query store_id is an access-checked branch
        // filter (403). Null (every store) only through a verified `all`.
        $storeId = $activeStore->readFilter($request);

        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'all');
        $customerId = $request->input('customer_id');
        $paymentType = $request->input('payment_type');
        $paymentMethod = $request->input('payment_method');
        $fromDate = $request->input('from_date') ?: $request->input('from');
        $toDate = $request->input('to_date') ?: $request->input('to');
        $perPage = max(1, min(200, (int) $request->input('per_page', 15)));

        $query = Invoice::query()->with(['customer:id,name,phone,current_balance', 'user:id,name', 'store:id,name']);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($customerId && $customerId !== 'all') {
            $query->where('customer_id', (int) $customerId);
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

        $totalSales = (float) (clone $query)->where('status', '!=', 'cancelled')->sum('net_total');
        $totalPaid = (float) (clone $query)->where('status', '!=', 'cancelled')->sum('paid_amount');
        $totalDue = (float) bcsub((string) $totalSales, (string) $totalPaid, 3);
        $totalCount = (int) (clone $query)->count();

        $invoices = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => InvoiceSummaryResource::collection($invoices->items())->resolve(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
            'summary' => [
                'total_count' => $totalCount,
                'total_sales' => $totalSales,
                'total_paid' => $totalPaid,
                'total_due' => $totalDue,
            ],
        ], 200);
    }

    /**
     * Show single invoice details with items, payments, and WhatsApp share URL
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('invoices.view') && ! $user->can('pos.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $this->findInvoiceOr404($user, $id, 'view');

        $result = $this->getInvoiceDetailsAction->execute($id);

        return response()->json([
            'success' => true,
            'data' => (new InvoiceResource($result['invoice']))->resolve(),
            'whatsapp' => $result['whatsapp'],
        ], 200);
    }

    /**
     * Create & confirm new Sales Invoice via Form Request
     */
    public function store(StoreSalesInvoiceRequest $request, ActiveStore $activeStore): JsonResponse
    {
        // STOR-2 (CTO Q14): the invoice is sold from the active store only (store.active:concrete).
        // A body store_id is still access-checked (403) but never chooses the store.
        ClientStoreGuard::verified($request);
        $data = $request->validated();
        unset($data['store_id']);

        $dto = CreateInvoiceDTO::fromArray($data, (int) $activeStore->requireStore()->id);
        $invoice = $this->createSalesInvoiceAction->execute($dto);

        $details = $this->getInvoiceDetailsAction->execute($invoice->id);

        $replayed = ! $invoice->wasRecentlyCreated;

        return response()->json([
            'success' => true,
            'message' => __('invoices.invoice_created', ['number' => $invoice->invoice_number]),
            'data' => (new InvoiceResource($details['invoice']))->resolve(),
            'whatsapp' => $details['whatsapp'],
        ], $replayed ? 200 : 201, $replayed ? ['Idempotent-Replayed' => 'true'] : []);
    }

    /**
     * Cancel Sales Invoice via Form Request
     */
    public function cancel(CancelInvoiceRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->findInvoiceOr404($user, $id, 'cancel');
        }

        $dto = CancelInvoiceDTO::fromArray($id, $request->validated());
        $cancelled = $this->cancelSalesInvoiceAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('invoices.cancelled_success', ['number' => $cancelled->invoice_number]),
            'data' => (new InvoiceResource($cancelled))->resolve(),
        ], 200);
    }

    /**
     * STOR-2 (CTO Q13): the permission was already checked (403), so a policy denial here can only
     * be the invoice's store: answered exactly like a missing invoice (404), never revealing it.
     */
    private function findInvoiceOr404(User $user, int $id, string $ability): void
    {
        $invoice = Invoice::query()->select(['id', 'store_id'])->find($id);

        if (! $invoice instanceof Invoice || ! Gate::forUser($user)->allows($ability, $invoice)) {
            throw (new ModelNotFoundException)->setModel(Invoice::class, [$id]);
        }
    }
}
