<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\StockCounts\CancelStockCountAction;
use App\Actions\StockCounts\GetStockCountVarianceAction;
use App\Actions\StockCounts\ListStockCountsAction;
use App\Actions\StockCounts\PostStockCountAction;
use App\Actions\StockCounts\SaveStockCountLinesAction;
use App\Actions\StockCounts\StartStockCountAction;
use App\DTOs\StockCounts\PostStockCountDTO;
use App\DTOs\StockCounts\SaveStockCountLinesDTO;
use App\DTOs\StockCounts\StartStockCountDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockCounts\ListStockCountsRequest;
use App\Http\Requests\StockCounts\PostStockCountRequest;
use App\Http\Requests\StockCounts\SaveStockCountLinesRequest;
use App\Http\Requests\StockCounts\StartStockCountRequest;
use App\Http\Resources\StockCountResource;
use App\Models\StockCount;
use App\Models\User;
use App\Support\ActiveStore;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * SHOP-02: stocktake sessions. Permission `stock.count` (403, Form Requests); a session of a store
 * the user cannot reach answers 404 like a missing one (CTO Q13).
 */
final class StockCountController extends Controller
{
    public function index(ListStockCountsRequest $request, ListStockCountsAction $action): JsonResponse
    {
        $counts = $action->execute((int) $request->validated('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => StockCountResource::collection($counts->items())->resolve($request),
            'meta' => [
                'current_page' => $counts->currentPage(),
                'last_page' => $counts->lastPage(),
                'per_page' => $counts->perPage(),
                'total' => $counts->total(),
            ],
        ]);
    }

    public function store(StartStockCountRequest $request, ActiveStore $activeStore, StartStockCountAction $action): JsonResponse
    {
        $dto = StartStockCountDTO::fromArray([
            ...$request->validated(),
            'store_id' => (int) $activeStore->requireStore()->id,
        ]);

        $count = $action->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('stock_counts.started', ['number' => $count->count_number]),
            'data' => (new StockCountResource($this->loadForShow($count)))->resolve($request),
        ], 201);
    }

    public function show(ListStockCountsRequest $request, int $id): JsonResponse
    {
        $count = $this->findOr404($request, $id, 'view');

        return response()->json([
            'success' => true,
            'data' => (new StockCountResource($this->loadForShow($count)))->resolve($request),
        ]);
    }

    public function variance(ListStockCountsRequest $request, int $id, GetStockCountVarianceAction $action): JsonResponse
    {
        $count = $this->findOr404($request, $id, 'view');
        $report = $action->execute($count, (bool) $request->user()?->can('items.view_cost'));

        return response()->json([
            'success' => true,
            'data' => [
                'count' => (new StockCountResource($count->load(['store', 'category', 'creator', 'poster'])))->resolve($request),
                'lines' => $report['lines'],
                'totals' => $report['totals'],
            ],
        ]);
    }

    public function saveLines(SaveStockCountLinesRequest $request, int $id, SaveStockCountLinesAction $action): JsonResponse
    {
        $this->findOr404($request, $id, 'update');

        $count = $action->execute(SaveStockCountLinesDTO::fromArray($id, $request->validated()));

        return response()->json([
            'success' => true,
            'message' => __('stock_counts.saved'),
            'data' => (new StockCountResource($this->loadForShow($count)))->resolve($request),
        ]);
    }

    public function post(PostStockCountRequest $request, int $id, PostStockCountAction $action): JsonResponse
    {
        $this->findOr404($request, $id, 'update');

        $count = $action->execute(PostStockCountDTO::fromArray($id, $request->validated()));

        return response()->json([
            'success' => true,
            'message' => __('stock_counts.posted', ['number' => $count->count_number]),
            'data' => (new StockCountResource($this->loadForShow($count)))->resolve($request),
        ]);
    }

    public function cancel(PostStockCountRequest $request, int $id, CancelStockCountAction $action): JsonResponse
    {
        $this->findOr404($request, $id, 'update');

        $count = $action->execute($id);

        return response()->json([
            'success' => true,
            'message' => __('stock_counts.cancelled', ['number' => $count->count_number]),
            'data' => (new StockCountResource($count->load(['store', 'category', 'creator', 'poster'])))->resolve($request),
        ]);
    }

    private function loadForShow(StockCount $count): StockCount
    {
        return $count->load([
            'store',
            'category',
            'creator',
            'poster',
            'lines' => fn ($q) => $q->orderBy('id'),
            'lines.item',
        ]);
    }

    private function findOr404(Request $request, int $id, string $ability): StockCount
    {
        $user = $request->user();
        $count = StockCount::query()->find($id);

        if (! $count instanceof StockCount || ! $user instanceof User || ! Gate::forUser($user)->allows($ability, $count)) {
            throw (new ModelNotFoundException)->setModel(StockCount::class, [$id]);
        }

        return $count;
    }
}
