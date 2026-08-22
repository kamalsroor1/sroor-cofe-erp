<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Returns\CreateReturnAction;
use App\Actions\Returns\DeleteReturnAction;
use App\DTOs\Returns\ReturnDocumentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReturnRequest;
use App\Http\Resources\ReturnResource;
use App\Models\ReturnDocument;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReturnController extends Controller
{
    public function __construct(
        private readonly CreateReturnAction $createReturnAction,
        private readonly DeleteReturnAction $deleteReturnAction
    ) {}

    /**
     * List returns with filters and totals
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string)$request->input('search', ''));
        $type = (string)$request->input('type', 'all');
        $fromDate = $request->input('from_date') ?: $request->input('from');
        $toDate = $request->input('to_date') ?: $request->input('to');
        $perPage = (int)$request->input('per_page', 15);

        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: auth()->user()?->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        $query = ReturnDocument::with(['customer', 'supplier', 'user', 'store', 'items.item']);

        if ($storeId) {
            $query->where('store_id', (int)$storeId);
        }

        if ($type !== 'all' && $type !== '') {
            $query->where('return_type', $type);
        }

        if ($fromDate) {
            $query->whereDate('return_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('return_date', '<=', $toDate);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('supplier', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $totalReturnsValue = (float)(clone $query)->sum('total_amount');
        $salesReturnsCount = (int)(clone $query)->where('return_type', 'sales_return')->count();
        $purchaseReturnsCount = (int)(clone $query)->where('return_type', 'purchase_return')->count();

        $returns = $query->latest('return_date')->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => ReturnResource::collection($returns->items())->resolve(),
            'meta'    => [
                'current_page' => $returns->currentPage(),
                'last_page'    => $returns->lastPage(),
                'per_page'     => $returns->perPage(),
                'total'        => $returns->total(),
            ],
            'summary' => [
                'total_value'    => $totalReturnsValue,
                'sales_count'    => $salesReturnsCount,
                'purchase_count' => $purchaseReturnsCount,
                'total_count'    => $salesReturnsCount + $purchaseReturnsCount,
            ],
        ], 200);
    }

    /**
     * Show single return document with item lines
     */
    public function show(int $id): JsonResponse
    {
        $returnDoc = ReturnDocument::with(['customer', 'supplier', 'user', 'store', 'items.item'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => (new ReturnResource($returnDoc))->resolve(),
        ], 200);
    }

    /**
     * Create and confirm return document via Form Request
     */
    public function store(StoreReturnRequest $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: auth()->user()?->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        $dto = ReturnDocumentDTO::fromArray($request->validated(), $storeId ? (int)$storeId : null);
        $returnDoc = $this->createReturnAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => "تم تسجيل مستند المرتجع رقم {$returnDoc->return_number} بنجاح ✓",
            'data'    => (new ReturnResource($returnDoc))->resolve(),
        ], 201);
    }

    /**
     * Delete/archive return document
     */
    public function destroy(int $id): JsonResponse
    {
        $this->deleteReturnAction->execute($id);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف مستند المرتجع بنجاح ✓',
        ], 200);
    }
}
