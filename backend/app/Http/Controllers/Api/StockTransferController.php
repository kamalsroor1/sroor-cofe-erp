<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Transfers\CancelStockTransferAction;
use App\Actions\Transfers\CreateStockTransferAction;
use App\DTOs\Transfers\CancelTransferDTO;
use App\DTOs\Transfers\CreateTransferDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelStockTransferRequest;
use App\Http\Requests\StoreStockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\ActiveStore;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class StockTransferController extends Controller
{
    public function __construct(
        private readonly CreateStockTransferAction $createStockTransferAction,
        private readonly CancelStockTransferAction $cancelStockTransferAction
    ) {}

    /**
     * List Stock Transfers with filters and summary totals
     */
    public function index(Request $request, ActiveStore $activeStore): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('stores.manage') && ! $user->can('transfers.view')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $search = trim((string) $request->input('search', ''));
        $fromStore = $request->input('from_store_id');
        $toStore = $request->input('to_store_id');
        $status = (string) $request->input('status', 'all');
        $fromDate = $request->input('from_date') ?: $request->input('from');
        $toDate = $request->input('to_date') ?: $request->input('to');
        $perPage = max(1, min(200, (int) $request->input('per_page', 15)));

        // STOR-2: the active store (or an access-checked store_id); every store only for a verified `all`.
        $storeId = $activeStore->readFilter($request);

        $query = StockTransfer::query()->with(['fromStore', 'toStore', 'user', 'items.item']);

        // A user who cannot see every store always stays limited to transfers touching the active
        // store, even when filtering by from/to store (else a branch could list other branches' moves).
        $restrictToActiveStore = (! $fromStore && ! $toStore)
            || ($user instanceof User && ! ActiveStore::canViewAll($user));

        if ($storeId !== null && $restrictToActiveStore) {
            $query->where(function ($q) use ($storeId) {
                $q->where('from_store_id', (int) $storeId)
                    ->orWhere('to_store_id', (int) $storeId);
            });
        }

        if ($fromStore && $fromStore !== 'all') {
            $query->where('from_store_id', (int) $fromStore);
        }

        if ($toStore && $toStore !== 'all') {
            $query->where('to_store_id', (int) $toStore);
        }

        if ($status !== 'all' && $status !== '') {
            $query->where('status', $status);
        }

        if ($fromDate) {
            $query->whereDate('transfer_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('transfer_date', '<=', $toDate);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('transfer_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('fromStore', fn ($sq) => $sq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('toStore', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $totalCount = (int) (clone $query)->count();
        $confirmedCount = (int) (clone $query)->where('status', 'confirmed')->count();
        $cancelledCount = (int) (clone $query)->where('status', 'cancelled')->count();

        $transfers = $query->latest('transfer_date')->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => StockTransferResource::collection($transfers->items())->resolve(),
            'meta' => [
                'current_page' => $transfers->currentPage(),
                'last_page' => $transfers->lastPage(),
                'per_page' => $transfers->perPage(),
                'total' => $transfers->total(),
            ],
            'summary' => [
                'total_count' => $totalCount,
                'confirmed_count' => $confirmedCount,
                'cancelled_count' => $cancelledCount,
            ],
        ], 200);
    }

    /**
     * Show single Stock Transfer details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('stores.manage') && ! $user->can('transfers.view')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $this->findTransferOr404($request, $id, 'view');

        $transfer = StockTransfer::with(['fromStore', 'toStore', 'user', 'items.item'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => (new StockTransferResource($transfer))->resolve(),
        ], 200);
    }

    /**
     * Create and execute Stock Transfer via Form Request
     */
    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $dto = CreateTransferDTO::fromArray($request->validated());
        $transfer = $this->createStockTransferAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('inventory.confirm_transfer') ?: "تم تنفيذ إذن التحويل المخزني رقم {$transfer->transfer_number} ونقل البضاعة فوراً بنجاح ✓",
            'data' => (new StockTransferResource($transfer->load(['fromStore', 'toStore', 'items.item'])))->resolve(),
        ], 201);
    }

    /**
     * Cancel / Reverse Stock Transfer via Form Request
     */
    public function cancel(CancelStockTransferRequest $request, int $id): JsonResponse
    {
        $this->findTransferOr404($request, $id, 'cancel');

        $dto = CancelTransferDTO::fromArray($id, $request->validated());
        $cancelled = $this->cancelStockTransferAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('inventory.cancel_transfer') ?: "تم إلغاء إذن التحويل رقم {$cancelled->transfer_number} وعكس حركة الأصناف للفرع المصدر بنجاح ✓",
            'data' => (new StockTransferResource($cancelled->load(['fromStore', 'toStore', 'items.item'])))->resolve(),
        ], 200);
    }

    /**
     * STOR-2 (CTO Q13): the permission was already checked (403), so a policy denial here can only
     * be the transfer's stores: answered exactly like a missing transfer (404).
     */
    private function findTransferOr404(Request $request, int $id, string $ability): void
    {
        $user = $request->user();
        $transfer = StockTransfer::query()->select(['id', 'from_store_id', 'to_store_id'])->find($id);

        if (! $transfer instanceof StockTransfer || ! $user instanceof User || ! Gate::forUser($user)->allows($ability, $transfer)) {
            throw (new ModelNotFoundException)->setModel(StockTransfer::class, [$id]);
        }
    }
}
