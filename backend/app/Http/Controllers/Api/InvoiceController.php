<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Invoices\BuildInvoiceListQueryAction;
use App\Actions\Invoices\CancelSalesInvoiceAction;
use App\Actions\Invoices\CreateSalesInvoiceAction;
use App\Actions\Invoices\ExportInvoicesCsvAction;
use App\Actions\Invoices\GetInvoiceDetailsAction;
use App\DTOs\Invoices\CancelInvoiceDTO;
use App\DTOs\Invoices\CreateInvoiceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelInvoiceRequest;
use App\Http\Requests\ExportInvoicesRequest;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly CreateSalesInvoiceAction $createSalesInvoiceAction,
        private readonly CancelSalesInvoiceAction $cancelSalesInvoiceAction,
        private readonly GetInvoiceDetailsAction $getInvoiceDetailsAction,
        private readonly BuildInvoiceListQueryAction $buildInvoiceListQueryAction,
        private readonly ExportInvoicesCsvAction $exportInvoicesCsvAction,
    ) {}

    /**
     * List Invoices for active store with filters and summary totals
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('invoices.view') && ! $user->can('pos.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $perPage = max(1, min(200, (int) $request->input('per_page', 15)));

        $query = $this->buildInvoiceListQueryAction->execute();

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
     * Stream the invoices matching the index filters (or the given ids) as a CSV download.
     */
    public function export(ExportInvoicesRequest $request): StreamedResponse
    {
        $query = $this->buildInvoiceListQueryAction->execute();

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            $this->exportInvoicesCsvAction->execute($query, $out);
            fclose($out);
        }, 'invoices_'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
        $cancelled = $this->cancelSalesInvoiceAction->execute($dto, $user);

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
