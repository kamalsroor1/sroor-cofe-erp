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
use App\Models\User;
use App\Support\ActiveStore;
use App\Support\ClientStoreGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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
    public function index(Request $request, ActiveStore $activeStore): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('purchases.view')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'all');
        $supplierId = $request->input('supplier_id');
        $fromDate = $request->input('from_date') ?: $request->input('from');
        $toDate = $request->input('to_date') ?: $request->input('to');
        $perPage = max(1, min(200, (int) $request->input('per_page', 15)));

        // STOR-2: the active store (X-Store-Id); a query store_id is an access-checked branch
        // filter (403). Null (every store) only through a verified `all`.
        $storeId = $activeStore->readFilter($request);

        $query = Purchase::with(['supplier', 'user', 'store', 'items.item']);

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('purchase_number', 'like', "%{$search}%")
                    ->orWhere('supplier_invoice_ref', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"));
            });
        }

        if ($status !== 'all' && $status !== '') {
            $query->where('status', $status);
        }

        if ($supplierId && $supplierId !== 'all') {
            $query->where('supplier_id', (int) $supplierId);
        }

        if ($fromDate) {
            $query->whereDate('purchase_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('purchase_date', '<=', $toDate);
        }

        $totalPurchases = (float) (clone $query)->where('status', 'confirmed')->sum('net_total');
        $unpaidTotal = (float) (clone $query)->where('status', 'confirmed')->sum('remaining_amount');
        $confirmedCount = (int) (clone $query)->where('status', 'confirmed')->count();

        $purchases = $query->latest('purchase_date')->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => PurchaseResource::collection($purchases->items())->resolve(),
            'meta' => [
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'per_page' => $purchases->perPage(),
                'total' => $purchases->total(),
            ],
            'summary' => [
                'total_purchases' => $totalPurchases,
                'unpaid_total' => $unpaidTotal,
                'confirmed_count' => $confirmedCount,
            ],
        ], 200);
    }

    /**
     * Show single purchase with items
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('purchases.view')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        if ($user instanceof User) {
            $this->findPurchaseOr404($user, $id, 'view');
        }

        $purchase = Purchase::with(['supplier', 'user', 'store', 'items.item', 'additionalExpenses'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => (new PurchaseResource($purchase))->resolve(),
        ], 200);
    }

    /**
     * Store new purchase invoice
     */
    public function store(StorePurchaseRequest $request, ActiveStore $activeStore): JsonResponse
    {
        // STOR-2 (CTO Q14): goods are received into the active store only (store.active:concrete).
        // A body store_id is still access-checked (403) but never chooses the store.
        ClientStoreGuard::verified($request);

        $dto = PurchaseDTO::fromArray($request->validated(), (int) $activeStore->requireStore()->id);
        $purchase = $this->createPurchaseAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('purchases.created_success', ['number' => $purchase->purchase_number]),
            'data' => (new PurchaseResource($purchase))->resolve(),
        ], 201);
    }

    /**
     * Cancel / Void purchase invoice
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('purchases.delete')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        if ($user instanceof User) {
            $this->findPurchaseOr404($user, $id, 'cancel');
        }

        $dto = CancelPurchaseDTO::fromArray($id, [
            'reason' => $request->input('reason', __('purchases.cancel_reason_default')),
        ]);

        $purchase = $this->cancelPurchaseAction->execute($dto);

        return response()->json([
            'success' => true,
            'message' => __('purchases.cancelled_success', ['number' => $purchase->purchase_number]),
            'data' => (new PurchaseResource($purchase))->resolve(),
        ], 200);
    }

    /**
     * Smart Reorder AI Suggestions
     */
    public function smartReorder(Request $request, ActiveStore $activeStore): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('purchases.view')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        // STOR-2: the active store, an access-checked ?store_id, or every store via a verified `all`.
        $storeFilter = $activeStore->readFilter($request);

        $analysisDays = (int) $request->input('analysis_days', 14);
        $targetCoverDays = (int) $request->input('target_cover_days', 15);
        $urgency = (string) $request->input('urgency', 'all');
        $search = trim((string) $request->input('search', ''));

        $result = $this->getSmartReorderSuggestionsAction->execute(
            storeId: $storeFilter,
            analysisDays: $analysisDays,
            targetCoverDays: $targetCoverDays,
            urgency: $urgency,
            search: $search
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 200);
    }

    /**
     * STOR-2 (CTO Q13): the permission was already checked (403), so a policy denial here can only
     * be the purchase's store: answered exactly like a missing purchase (404), never revealing it.
     */
    private function findPurchaseOr404(User $user, int $id, string $ability): void
    {
        $purchase = Purchase::query()->select(['id', 'store_id'])->find($id);

        if (! $purchase instanceof Purchase || ! Gate::forUser($user)->allows($ability, $purchase)) {
            throw (new ModelNotFoundException)->setModel(Purchase::class, [$id]);
        }
    }
}
