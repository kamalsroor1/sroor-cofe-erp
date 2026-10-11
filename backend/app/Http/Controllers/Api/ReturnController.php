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
use App\Models\User;
use App\Support\ActiveStore;
use App\Support\ClientStoreGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ReturnController extends Controller
{
    public function __construct(
        private readonly CreateReturnAction $createReturnAction,
        private readonly DeleteReturnAction $deleteReturnAction
    ) {}

    /**
     * List returns with filters and totals
     */
    public function index(Request $request, ActiveStore $activeStore): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('returns.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $search = trim((string) $request->input('search', ''));
        $type = (string) $request->input('type', 'all');
        $fromDate = $request->input('from_date') ?: $request->input('from');
        $toDate = $request->input('to_date') ?: $request->input('to');
        $perPage = max(1, min(200, (int) $request->input('per_page', 15)));

        // STOR-2: the active store (X-Store-Id); a query store_id is an access-checked branch
        // filter (403). Null (every store) only through a verified `all`.
        $storeId = $activeStore->readFilter($request);

        $query = ReturnDocument::with(['customer', 'supplier', 'user', 'store', 'items.item']);

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
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
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $totalReturnsValue = (float) (clone $query)->sum('total_amount');
        $salesReturnsCount = (int) (clone $query)->where('return_type', 'sales_return')->count();
        $purchaseReturnsCount = (int) (clone $query)->where('return_type', 'purchase_return')->count();

        $returns = $query->latest('return_date')->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ReturnResource::collection($returns->items())->resolve(),
            'meta' => [
                'current_page' => $returns->currentPage(),
                'last_page' => $returns->lastPage(),
                'per_page' => $returns->perPage(),
                'total' => $returns->total(),
            ],
            'summary' => [
                'total_value' => $totalReturnsValue,
                'sales_count' => $salesReturnsCount,
                'purchase_count' => $purchaseReturnsCount,
                'total_count' => $salesReturnsCount + $purchaseReturnsCount,
            ],
        ], 200);
    }

    /**
     * Show single return document with item lines
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('returns.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        if ($user instanceof User) {
            $this->findReturnOr404($user, $id, 'view');
        }

        $returnDoc = ReturnDocument::with(['customer', 'supplier', 'user', 'store', 'items.item'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => (new ReturnResource($returnDoc))->resolve(),
        ], 200);
    }

    /**
     * Create and confirm return document via Form Request
     */
    public function store(StoreReturnRequest $request, ActiveStore $activeStore): JsonResponse
    {
        // STOR-2 (CTO Q14): the return is booked on the active store only (store.active:concrete).
        // A body store_id is still access-checked (403) but never chooses the store.
        ClientStoreGuard::verified($request);

        $dto = ReturnDocumentDTO::fromArray($request->validated(), (int) $activeStore->requireStore()->id);
        $returnDoc = $this->createReturnAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('returns.created_success', ['number' => $returnDoc->return_number]),
            'data' => (new ReturnResource($returnDoc))->resolve(),
        ], 201);
    }

    /**
     * Delete/archive return document
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('returns.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        if ($user instanceof User) {
            $this->findReturnOr404($user, $id, 'delete');
        }

        $this->deleteReturnAction->execute($id);

        return response()->json([
            'success' => true,
            'message' => __('returns.deleted_success'),
        ], 200);
    }

    /**
     * STOR-2 (CTO Q13): the permission was already checked (403), so a policy denial here can only
     * be the document's store: answered exactly like a missing document (404), never revealing it.
     */
    private function findReturnOr404(User $user, int $id, string $ability): void
    {
        $returnDoc = ReturnDocument::query()->select(['id', 'store_id'])->find($id);

        if (! $returnDoc instanceof ReturnDocument || ! Gate::forUser($user)->allows($ability, $returnDoc)) {
            throw (new ModelNotFoundException)->setModel(ReturnDocument::class, [$id]);
        }
    }
}
