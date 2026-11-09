<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Customers\GetCustomerLastSoldPriceAction;
use App\Actions\Customers\QuickCreateCustomerAction;
use App\Actions\Invoices\GetInvoiceDetailsAction;
use App\Actions\Invoices\ProcessPOSInvoiceAction;
use App\Actions\POS\GetPOSBootstrapDataAction;
use App\Actions\POS\GetPosItemsAction;
use App\Actions\POS\LookupPosBarcodeAction;
use App\DTOs\POSInvoiceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\ListPosItemsRequest;
use App\Http\Requests\Pos\LookupPosBarcodeRequest;
use App\Http\Requests\StorePOSInvoiceRequest;
use App\Http\Requests\StoreQuickCustomerRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PosGridItemResource;
use App\Http\Resources\PosLookupItemResource;
use App\Services\Pos\InvalidScaleBarcodeException;
use App\Support\ActiveStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PosController extends Controller
{
    public function __construct(
        private readonly GetPOSBootstrapDataAction $getPOSBootstrapDataAction,
        private readonly ProcessPOSInvoiceAction $processPOSInvoiceAction,
        private readonly QuickCreateCustomerAction $quickCreateCustomerAction,
        private readonly GetCustomerLastSoldPriceAction $getCustomerLastSoldPriceAction,
        private readonly GetInvoiceDetailsAction $getInvoiceDetailsAction,
        private readonly LookupPosBarcodeAction $lookupPosBarcodeAction,
        private readonly GetPosItemsAction $getPosItemsAction,
    ) {}

    /**
     * Bootstrap fast POS data (categories, items with stock, customers, active shift)
     */
    public function bootstrap(Request $request, ActiveStore $activeStore): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('pos.access') && ! $user->can('invoices.create')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $data = $this->getPOSBootstrapDataAction->execute(
            $user,
            $activeStore->store(),
            includeItems: $request->boolean('include_items', true),
            includeCustomers: $request->boolean('include_customers', true),
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * POS-04/05: lean paged item grid of the active store with real per-category counters.
     */
    public function items(ListPosItemsRequest $request): JsonResponse
    {
        $result = $this->getPosItemsAction->execute($request->toDto());
        $paginator = $result['paginator'];

        return response()->json([
            'success' => true,
            'data' => PosGridItemResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'counts' => $result['counts'],
            ],
        ]);
    }

    /**
     * POS-01: exact barcode/code lookup (plus scale labels) in the active store.
     */
    public function lookup(LookupPosBarcodeRequest $request): JsonResponse
    {
        $code = $request->code();

        try {
            $result = $this->lookupPosBarcodeAction->execute($code, $request->activeStore());
        } catch (InvalidScaleBarcodeException $e) {
            return response()->json(['success' => false, 'message' => $e->localizedMessage()], 422);
        }

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => __('pos.barcode_not_found', ['code' => $code]),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'item' => (new PosLookupItemResource($result['item']))->resolve($request),
                'quantity' => $result['quantity'],
                'scanned_price' => $result['scanned_price'],
                'source' => $result['source'],
            ],
        ]);
    }

    /**
     * Submit and confirm POS checkout atomically
     */
    public function checkout(StorePOSInvoiceRequest $request): JsonResponse
    {
        $dto = POSInvoiceDTO::fromArray($request->validated());
        $invoice = $this->processPOSInvoiceAction->execute($dto);

        $details = $this->getInvoiceDetailsAction->execute($invoice->id);

        $replayed = ! $invoice->wasRecentlyCreated;

        return response()->json([
            'success' => true,
            'message' => __('pos.invoice_saved_success'),
            'data' => (new InvoiceResource($details['invoice']))->resolve(),
            'whatsapp' => $details['whatsapp'],
        ], $replayed ? 200 : 201, $replayed ? ['Idempotent-Replayed' => 'true'] : []);
    }

    /**
     * Fast Quick Customer Registration from POS
     */
    public function quickCustomer(StoreQuickCustomerRequest $request): JsonResponse
    {
        $customer = $this->quickCreateCustomerAction->execute($request->validated());

        return response()->json([
            'success' => true,
            'message' => __('pos.customer_registered_success'),
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'price_tier' => $customer->price_tier ?? 'retail',
                'current_balance' => 0,
            ],
        ], 201);
    }

    /**
     * Get Last Sold Price for customer on specific item
     */
    public function lastPrice(Request $request, ActiveStore $activeStore): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('pos.access') && ! $user->can('invoices.create')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $customerId = (int) $request->query('customer_id');
        $itemId = (int) $request->query('item_id');
        // STOR-2: active store (or an access-checked store_id filter); null only for a verified `all`.
        $storeId = $activeStore->readFilter($request);

        $lastPrice = $this->getCustomerLastSoldPriceAction->execute(
            customerId: $customerId,
            itemId: $itemId,
            storeId: $storeId
        );

        return response()->json([
            'success' => true,
            'last_price' => $lastPrice,
        ], 200);
    }
}
