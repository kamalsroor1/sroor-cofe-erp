<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Stores\AssignStoreUsersAction;
use App\Actions\Stores\CreateStoreAction;
use App\Actions\Stores\DeleteStoreAction;
use App\Actions\Stores\GetStoreStocksAction;
use App\Actions\Stores\ToggleStoreActiveAction;
use App\Actions\Stores\UpdateStoreAction;
use App\DTOs\Stores\StoreDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignStoreUsersRequest;
use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\SwitchStoreRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Http\Resources\StoreStockResource;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StoreController extends Controller
{
    public function __construct(
        private readonly CreateStoreAction $createStoreAction,
        private readonly UpdateStoreAction $updateStoreAction,
        private readonly DeleteStoreAction $deleteStoreAction,
        private readonly ToggleStoreActiveAction $toggleStoreActiveAction,
        private readonly AssignStoreUsersAction $assignStoreUsersAction,
        private readonly GetStoreStocksAction $getStoreStocksAction
    ) {}

    /**
     * Get list of stores/branches with statistics and user assignments
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $isGlobalAdmin = $user->id === 1
            || $user->hasRole('super-admin')
            || $user->hasRole('admin')
            || $user->can('stores.manage');

        $query = Store::with(['users' => fn($q) => $q->select('users.id', 'users.name', 'users.email')])
            ->withCount(['stocks', 'invoices', 'purchases'])
            ->orderBy('is_main', 'desc')
            ->orderBy('id', 'asc');

        if (!$isGlobalAdmin) {
            $query->where('is_active', true)
                ->where(function ($q) use ($user) {
                    $q->whereHas('users', fn($uq) => $uq->where('users.id', $user->id))
                      ->orWhere('id', $user->default_store_id);
                });
        }

        $stores = $query->get();

        // Fallback for non-admin if no specific store assigned
        if ($stores->isEmpty()) {
            $mainStore = Store::getMainStore();
            if ($mainStore) {
                $stores = collect([$mainStore]);
            }
        }

        $activeStoreId = $request->header('X-Store-Id') ?: session('current_store_id') ?: $user->default_store_id ?: ($stores->first()?->id);
        $activeStore = $stores->firstWhere('id', (int)$activeStoreId) ?: $stores->first();

        $allUsers = [];
        if ($isGlobalAdmin) {
            $allUsers = User::where('is_active', true)->select('id', 'name', 'email')->get();
        }

        return response()->json([
            'success'      => true,
            'active_store' => $activeStore ? (new StoreResource($activeStore))->resolve() : null,
            'stores'       => StoreResource::collection($stores)->resolve(),
            'all_users'    => $allUsers,
        ], 200);
    }

    /**
     * Store a newly created store/branch
     */
    public function store(StoreStoreRequest $request): JsonResponse
    {
        $dto = StoreDTO::fromArray($request->validated());
        $store = $this->createStoreAction->execute($dto, $request->user());

        return response()->json([
            'success' => true,
            'message' => __('inventory.store_added') ?: 'تم إضافة الفرع / المخزن بنجاح',
            'data'    => (new StoreResource($store))->resolve(),
        ], 201);
    }

    /**
     * Display the specified store
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $store = Store::with(['users' => fn($q) => $q->select('users.id', 'users.name', 'users.email')])
            ->withCount(['stocks', 'invoices', 'purchases'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => (new StoreResource($store))->resolve(),
        ], 200);
    }

    /**
     * Update the specified store
     */
    public function update(UpdateStoreRequest $request, int $id): JsonResponse
    {
        $store = Store::findOrFail($id);
        $dto = StoreDTO::fromArray($request->validated());
        $updatedStore = $this->updateStoreAction->execute($store, $dto);

        return response()->json([
            'success' => true,
            'message' => __('inventory.store_updated') ?: 'تم تعديل بيانات الفرع بنجاح',
            'data'    => (new StoreResource($updatedStore))->resolve(),
        ], 200);
    }

    /**
     * Toggle active status of the store
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('stores.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $store = Store::findOrFail($id);
        $toggledStore = $this->toggleStoreActiveAction->execute($store);

        return response()->json([
            'success' => true,
            'message' => __('inventory.store_status_updated') ?: "تم تحديث حالة الفرع ({$store->name}) بنجاح",
            'data'    => (new StoreResource($toggledStore))->resolve(),
        ], 200);
    }

    /**
     * Synchronize assigned users to the store
     */
    public function assignUsers(AssignStoreUsersRequest $request, int $id): JsonResponse
    {
        $store = Store::findOrFail($id);
        $validated = $request->validated();
        $updatedStore = $this->assignStoreUsersAction->execute($store, $validated['user_ids'] ?? []);

        return response()->json([
            'success' => true,
            'message' => __('inventory.store_users_updated') ?: "تم تحديث تعيينات الموظفين لفرع ({$store->name}) بنجاح",
            'data'    => (new StoreResource($updatedStore))->resolve(),
        ], 200);
    }

    /**
     * Delete the specified store
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('stores.manage')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $store = Store::findOrFail($id);
        $this->deleteStoreAction->execute($store);

        return response()->json([
            'success' => true,
            'message' => __('inventory.store_deleted') ?: "تم حذف الفرع ({$store->name}) بنجاح",
        ], 200);
    }

    /**
     * Get stocks and item quantities for a store
     */
    public function stocks(Request $request): JsonResponse
    {
        $storeId = (int)($request->input('store_id') ?: $request->header('X-Store-Id') ?: 1);
        $search = trim((string)$request->input('search', ''));
        $stockStatus = (string)$request->input('stock_status', 'all');
        $perPage = max(1, min(200, (int)$request->input('per_page', 20)));

        $stocksPaginator = $this->getStoreStocksAction->execute($storeId, $search, $stockStatus, $perPage);

        return response()->json([
            'success' => true,
            'data'    => StoreStockResource::collection($stocksPaginator->items())->resolve(),
            'meta'    => [
                'current_page' => $stocksPaginator->currentPage(),
                'last_page'    => $stocksPaginator->lastPage(),
                'per_page'     => $stocksPaginator->perPage(),
                'total'        => $stocksPaginator->total(),
            ],
        ], 200);
    }

    /**
     * Switch Active Store/Branch
     */
    public function switchStore(SwitchStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();
        $targetStoreId = (int)$validated['store_id'];

        $isGlobalAdmin = $user->id === 1
            || $user->hasRole('super-admin')
            || $user->hasRole('admin')
            || $user->can('stores.manage');

        $isAssigned = $user->stores()->where('stores.id', $targetStoreId)->exists()
            || $user->default_store_id === $targetStoreId;

        if (!$isGlobalAdmin && !$isAssigned) {
            return response()->json([
                'success' => false,
                'message' => __('inventory.unauthorized_store_access') ?: 'عفواً، ليس لديك صلاحية للوصول إلى هذا الفرع.',
            ], 403);
        }

        $store = Store::findOrFail($targetStoreId);

        session(['current_store_id' => $store->id]);
        $user->update(['default_store_id' => $store->id]);

        return response()->json([
            'success'      => true,
            'message'      => __('inventory.switched_to_store', ['store' => $store->name]) ?: 'تم التبديل إلى فرع: ' . $store->name,
            'active_store' => (new StoreResource($store))->resolve(),
        ], 200);
    }
}
