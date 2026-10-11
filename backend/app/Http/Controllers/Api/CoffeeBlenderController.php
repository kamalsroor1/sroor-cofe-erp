<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Blends\CalculateBlendCostAction;
use App\Actions\Blends\CreateBlenderInvoiceAction;
use App\DTOs\Blends\CreateBlenderInvoiceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateBlendCostRequest;
use App\Http\Requests\CreateBlenderInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Support\ActiveStore;
use App\Support\ClientStoreGuard;
use Illuminate\Http\JsonResponse;

final class CoffeeBlenderController extends Controller
{
    public function __construct(
        private readonly CalculateBlendCostAction $calculateBlendCostAction,
        private readonly CreateBlenderInvoiceAction $createBlenderInvoiceAction
    ) {}

    /**
     * Real-time calculation of coffee blend formulation costs and profit margins
     */
    public function calculate(CalculateBlendCostRequest $request): JsonResponse
    {
        $result = $this->calculateBlendCostAction->execute($request->validated());

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 200);
    }

    /**
     * Create and confirm Sales Invoice for custom blend
     */
    public function createInvoice(CreateBlenderInvoiceRequest $request, ActiveStore $activeStore): JsonResponse
    {
        // SEC-1 (L3): sold from the active store only (store.active:concrete resolved and
        // access-checked X-Store-Id). A body store_id is access-checked (403) and then ignored.
        if (ClientStoreGuard::verified($request) === ActiveStore::ALL) {
            ClientStoreGuard::deny();
        }
        $storeId = (int) $activeStore->requireStore()->id;

        $dto = CreateBlenderInvoiceDTO::fromArray(array_merge($request->validated(), ['store_id' => $storeId]), $storeId);
        $invoice = $this->createBlenderInvoiceAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('inventory.blend_invoice_success'),
            'data' => (new InvoiceResource($invoice->load(['customer', 'store', 'user', 'items.item'])))->resolve(),
        ], 201);
    }
}
