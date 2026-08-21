<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Purchases\CancelPurchaseAction;
use App\Actions\Purchases\CreatePurchaseAction;
use App\Actions\Purchases\GetSmartReorderSuggestionsAction;
use App\DTOs\Purchases\CancelPurchaseDTO;
use App\DTOs\Purchases\PurchaseDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PurchaseController extends Controller
{
    public function __construct(
        private readonly CreatePurchaseAction $createPurchaseAction,
        private readonly CancelPurchaseAction $cancelPurchaseAction,
        private readonly GetSmartReorderSuggestionsAction $getSmartReorderSuggestionsAction
    ) {}

    /**
     * List Purchases with filters and totals
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string)$request->input('search', ''));
        $status = (string)$request->input('status', 'all');
        $supplierId = $request->input('supplier_id');
        $fromDate = $request->input('from_date') ?: $request->input('from');
        $toDate = $request->input('to_date') ?: $request->input('to');
        $perPage = (int)$request->input('per_page', 15);

        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: auth()->user()?->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        $query = Purchase::with(['supplier', 'user', 'store', 'items.item']);

        if ($storeId) {
            $query->where('store_id', (int)$storeId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('purchase_number', 'like', "%{$search}%")
                  ->orWhere('supplier_invoice_ref', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('supplier', fn($sq) => $sq->where('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"));
            });
        }

        if ($status !== 'all' && $status !== '') {
            $query->where('status', $status);
        }

        if ($supplierId && $supplierId !== 'all') {
            $query->where('supplier_id', (int)$supplierId);
        }

        if ($fromDate) {
            $query->whereDate('purchase_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('purchase_date', '<=', $toDate);
        }

        $totalPurchases = (float)(clone $query)->where('status', 'confirmed')->sum('net_total');
        $unpaidTotal = (float)(clone $query)->where('status', 'confirmed')->sum('remaining_amount');
        $confirmedCount = (int)(clone $query)->where('status', 'confirmed')->count();

        $purchases = $query->latest('purchase_date')->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => PurchaseResource::collection($purchases->items())->resolve(),
            'meta'    => [
                'current_page' => $purchases->currentPage(),
                'last_page'    => $purchases->lastPage(),
                'per_page'     => $purchases->perPage(),
                'total'        => $purchases->total(),
            ],
            'summary' => [
                'total_purchases' => $totalPurchases,
                'unpaid_total'    => $unpaidTotal,
                'confirmed_count' => $confirmedCount,
            ],
        ], 200);
    }

    /**
     * Show single purchase with items
     */
    public function show(int $id): JsonResponse
    {
        $purchase = Purchase::with(['supplier', 'user', 'store', 'items.item', 'additionalExpenses'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => (new PurchaseResource($purchase))->resolve(),
        ], 200);
    }

    /**
     * Store new purchase invoice
     */
    public function store(StorePurchaseRequest $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: auth()->user()?->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        $dto = PurchaseDTO::fromArray($request->validated(), $storeId ? (int)$storeId : null);
        $purchase = $this->createPurchaseAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('purchases.created_success', ['number' => $purchase->purchase_number]) ?: "تم تسجيل وتأكيد فاتورة المشتريات رقم {$purchase->purchase_number} وتوريد الخامات للمخزن بنجاح ✓",
            'data'    => (new PurchaseResource($purchase))->resolve(),
        ], 201);
    }

    /**
     * Cancel / Void purchase invoice
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $dto = CancelPurchaseDTO::fromArray($id, [
            'reason' => $request->input('reason', 'إلغاء من النظام'),
        ]);

        $purchase = $this->cancelPurchaseAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('purchases.cancelled_success', ['number' => $purchase->purchase_number]) ?: "تم إلغاء فاتورة المشتريات رقم {$purchase->purchase_number} وعكس المخزن والمديونية بنجاح ✓",
            'data'    => (new PurchaseResource($purchase))->resolve(),
        ], 200);
    }

    /**
     * Smart Reorder AI Suggestions
     */
    public function smartReorder(Request $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id');
        $storeFilter = ($storeId && $storeId !== 'all') ? (int)$storeId : null;

        $analysisDays = (int)$request->input('analysis_days', 14);
        $targetCoverDays = (int)$request->input('target_cover_days', 15);
        $urgency = (string)$request->input('urgency', 'all');
        $search = trim((string)$request->input('search', ''));

        $result = $this->getSmartReorderSuggestionsAction->execute(
            storeId: $storeFilter,
            analysisDays: $analysisDays,
            targetCoverDays: $targetCoverDays,
            urgency: $urgency,
            search: $search
        );

        return response()->json([
            'success' => true,
            'data'    => $result,
        ], 200);
    }
}
