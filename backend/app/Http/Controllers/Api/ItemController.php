<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Items\AdjustItemStockAction;
use App\Actions\Items\CreateItemAction;
use App\Actions\Items\DeleteItemAction;
use App\Actions\Items\GetItemMovementsAction;
use App\Actions\Items\ToggleItemActiveAction;
use App\Actions\Items\UpdateItemAction;
use App\DTOs\Items\AdjustStockDTO;
use App\DTOs\Items\ItemDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ItemController extends Controller
{
    public function __construct(
        private readonly CreateItemAction $createItemAction,
        private readonly UpdateItemAction $updateItemAction,
        private readonly DeleteItemAction $deleteItemAction,
        private readonly ToggleItemActiveAction $toggleItemActiveAction,
        private readonly AdjustItemStockAction $adjustItemStockAction,
        private readonly GetItemMovementsAction $getItemMovementsAction
    ) {}

    /**
     * List items with stock, filters & valuation metrics
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('items.view') && !$user->can('items.manage') && !$user->can('pos.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $search = trim((string)$request->input('search', ''));
        $category = (string)$request->input('category', 'all');
        $stockStatus = (string)$request->input('stock_status', 'all');
        $status = (string)$request->input('status', 'all');
        $perPage = max(1, min(200, (int)$request->input('per_page', 20)));

        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: $user?->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        $query = Item::with(['storeStocks.store']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($category !== 'all' && $category !== '') {
            $query->where('category', $category);
        }

        if ($stockStatus === 'low') {
            $query->whereColumn('current_stock', '<=', 'min_stock_level')->where('current_stock', '>', 0);
        } elseif ($stockStatus === 'out') {
            $query->where('current_stock', '<=', 0);
        } elseif ($stockStatus === 'in_stock') {
            $query->where('current_stock', '>', 0);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $items = $query->latest('id')->paginate($perPage);

        $categories = Item::whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category')->values();

        $totalItemsCount = Item::count();
        $lowStockCount = Item::whereColumn('current_stock', '<=', 'min_stock_level')->where('is_active', true)->count();
        $totalStockValue = (float)Item::selectRaw('SUM(current_stock * cost_price) as total_val')->value('total_val');

        return response()->json([
            'success'    => true,
            'data'       => ItemResource::collection($items->items())->resolve(),
            'meta'       => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'per_page'     => $items->perPage(),
                'total'        => $items->total(),
            ],
            'summary'    => [
                'total_items'       => $totalItemsCount,
                'low_stock_count'   => $lowStockCount,
                'total_stock_value' => $totalStockValue,
            ],
            'categories' => $categories,
            'store_id'   => $storeId,
        ], 200);
    }

    /**
     * Store a newly created item
     */
    public function store(StoreItemRequest $request): JsonResponse
    {
        $dto = ItemDTO::fromArray($request->validated());
        $item = $this->createItemAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('inventory.item_added') ?: 'تم إضافة الصنف بنجاح',
            'data'    => (new ItemResource($item))->resolve(),
        ], 201);
    }

    /**
     * Show single item details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('items.view') && !$user->can('items.manage') && !$user->can('pos.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $item = Item::with(['storeStocks.store'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => (new ItemResource($item))->resolve(),
        ], 200);
    }

    /**
     * Update existing item
     */
    public function update(UpdateItemRequest $request, int $id): JsonResponse
    {
        $item = Item::findOrFail($id);
        $dto = ItemDTO::fromArray($request->validated());
        $updated = $this->updateItemAction->execute($item, $dto);

        return response()->json([
            'success' => true,
            'message' => __('inventory.item_updated') ?: 'تم تعديل بيانات الصنف بنجاح',
            'data'    => (new ItemResource($updated))->resolve(),
        ], 200);
    }

    /**
     * Delete an item
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('items.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $item = Item::findOrFail($id);
        $this->deleteItemAction->execute($item);

        return response()->json([
            'success' => true,
            'message' => __('inventory.item_deleted') ?: 'تم حذف الصنف بنجاح',
        ], 200);
    }

    /**
     * Toggle Item Active Status
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('items.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $item = Item::findOrFail($id);
        $toggled = $this->toggleItemActiveAction->execute($item);

        return response()->json([
            'success' => true,
            'message' => __('inventory.status_updated') ?: 'تم تحديث حالة الصنف بنجاح',
            'data'    => (new ItemResource($toggled))->resolve(),
        ], 200);
    }

    /**
     * Adjust Item Stock
     */
    public function adjustStock(AdjustStockRequest $request, int $id): JsonResponse
    {
        $dto = AdjustStockDTO::fromArray($id, $request->validated());
        $userId = (int)auth()->id();

        $movement = $this->adjustItemStockAction->execute($dto, $userId);

        return response()->json([
            'success'  => true,
            'message'  => __('inventory.stock_adjusted') ?: 'تم تسجيل تسوية المخزون بنجاح',
            'movement' => $movement,
        ], 200);
    }

    /**
     * Item stock movements ledger
     */
    public function movements(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('items.view') && !$user->can('items.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $item = Item::withTrashed()->findOrFail($id);
        $fromDate = $request->input('from_date') ?: $request->input('from');
        $toDate = $request->input('to_date') ?: $request->input('to');
        $storeId = $request->input('store_id') && $request->input('store_id') !== 'all' ? (int)$request->input('store_id') : null;
        $type = $request->input('type');
        $perPage = max(1, min(200, (int)$request->input('per_page', 20)));

        $result = $this->getItemMovementsAction->execute(
            $item,
            $fromDate ? (string)$fromDate : null,
            $toDate ? (string)$toDate : null,
            $storeId,
            $type ? (string)$type : null,
            $perPage
        );

        return response()->json([
            'success' => true,
            'data'    => $result,
        ], 200);
    }

    /**
     * Low stock radar
     */
    public function lowStock(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('items.view') && !$user->can('items.manage') && !$user->can('pos.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $storeId = (int)($request->header('X-Store-Id') ?: $request->input('store_id') ?: session('current_store_id') ?: 1);

        $items = Item::query()
            ->active()
            ->lowStock()
            ->orderBy('current_stock', 'asc')
            ->get()
            ->map(function (Item $item) use ($storeId) {
                $stock = (string)$item->getStockInStore($storeId);
                $min = (string)$item->min_stock_level;
                $deficit = bcsub($min, $stock, 3);
                if (bccomp($deficit, '0.000', 3) < 0) {
                    $deficit = '0.000';
                }

                return [
                    'id'                    => $item->id,
                    'code'                  => $item->code,
                    'name'                  => $item->name,
                    'category'              => $item->category,
                    'unit'                  => $item->unit,
                    'cost_price'            => (string)$item->cost_price,
                    'selling_price'         => (string)$item->selling_price,
                    'current_stock'         => $stock,
                    'min_stock_level'       => $min,
                    'deficit'               => $deficit,
                    'suggested_reorder_qty' => bccomp($deficit, '0.000', 3) > 0 ? $deficit : '10.000',
                ];
            });

        return response()->json([
            'success'   => true,
            'count'     => $items->count(),
            'low_items' => $items,
        ], 200);
    }
}
