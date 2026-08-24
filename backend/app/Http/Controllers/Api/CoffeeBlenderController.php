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
use App\Models\Store;
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
            'data'    => $result,
        ], 200);
    }

    /**
     * Create and confirm Sales Invoice for custom blend
     */
    public function createInvoice(CreateBlenderInvoiceRequest $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: auth()->user()?->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        $dto = CreateBlenderInvoiceDTO::fromArray($request->validated(), $storeId ? (int)$storeId : null);
        $invoice = $this->createBlenderInvoiceAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('inventory.blend_invoice_success') ?: "تم إصدار واعتماد فاتورة التوليفة رقم {$invoice->invoice_number} بنجاح ✓",
            'data'    => (new InvoiceResource($invoice->load(['customer', 'store', 'user', 'items.item'])))->resolve(),
        ], 201);
    }
}
