<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Shifts\CloseShiftAction;
use App\Actions\Shifts\GetActiveShiftAction;
use App\Actions\Shifts\GetShiftZReportAction;
use App\Actions\Shifts\OpenShiftAction;
use App\DTOs\Shifts\CloseShiftDTO;
use App\DTOs\Shifts\OpenShiftDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseShiftRequest;
use App\Http\Requests\OpenShiftRequest;
use App\Http\Resources\CashShiftResource;
use App\Models\CashShift;
use App\Models\User;
use App\Support\ActiveStore;
use App\Support\ClientStoreGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ShiftController extends Controller
{
    public function __construct(
        private readonly GetActiveShiftAction $getActiveShiftAction,
        private readonly OpenShiftAction $openShiftAction,
        private readonly CloseShiftAction $closeShiftAction,
        private readonly GetShiftZReportAction $getShiftZReportAction
    ) {}

    /**
     * Get list of historical shifts
     */
    public function index(Request $request, ActiveStore $activeStore): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('daily_journal.view') && ! $user->can('pos.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        // STOR-2: active store (or an access-checked store_id filter); every store only for a verified `all`.
        $storeId = $activeStore->readFilter($request);

        $perPage = max(1, min(200, (int) $request->input('per_page', 20)));

        $query = CashShift::with(['user', 'store']);

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        $shifts = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => CashShiftResource::collection($shifts->items())->resolve(),
            'meta' => [
                'current_page' => $shifts->currentPage(),
                'last_page' => $shifts->lastPage(),
                'per_page' => $shifts->perPage(),
                'total' => $shifts->total(),
            ],
        ], 200);
    }

    /**
     * Get Current Active Shift & Live Shift Metrics
     */
    public function current(Request $request, ActiveStore $activeStore): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('daily_journal.view') && ! $user->can('pos.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $result = $this->getActiveShiftAction->execute($activeStore->singleStoreId($request));

        if (! $result) {
            return response()->json([
                'success' => true,
                'has_active' => false,
                'active_shift' => null,
                'metrics' => null,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'has_active' => true,
            'active_shift' => (new CashShiftResource($result['shift']))->resolve(),
            'metrics' => $result['metrics'],
        ], 200);
    }

    /**
     * Open a new cashier shift
     */
    public function open(OpenShiftRequest $request, ActiveStore $activeStore): JsonResponse
    {
        // STOR-2: the shift opens in the active store (store.active:concrete); a body store_id is
        // access-checked (403) but never chooses the store.
        ClientStoreGuard::verified($request);

        $dto = OpenShiftDTO::fromArray($request->validated(), (int) $activeStore->requireStore()->id);
        $userId = (int) auth()->id();

        $shift = $this->openShiftAction->execute($dto, $userId);

        return response()->json([
            'success' => true,
            'message' => "تم فتح وردية العمل رقم {$shift->shift_number} بنجاح ✓",
            'data' => (new CashShiftResource($shift))->resolve(),
        ], 201);
    }

    /**
     * Close the active cashier shift and generate Z-Report
     */
    public function close(CloseShiftRequest $request, ActiveStore $activeStore): JsonResponse
    {
        // STOR-2: only a shift of the active store (store.active:concrete) can be closed; a shift
        // of any other store is answered as not found (CTO Q13).
        ClientStoreGuard::verified($request);
        $storeId = (int) $activeStore->requireStore()->id;

        $shiftId = (int) ($request->input('shift_id') ?: $request->route('id'));
        if ($shiftId) {
            $this->findShiftOr404($request, $shiftId, 'close', $storeId);
        } else {
            $shiftId = (int) CashShift::where('status', 'open')->where('store_id', $storeId)->value('id');
        }

        $dto = CloseShiftDTO::fromArray($shiftId, $request->validated());
        $closedShift = $this->closeShiftAction->execute($dto);

        $diff = (float) $closedShift->cash_difference;
        $diffStatus = $diff == 0 ? 'مطابقة للدرج' : ($diff > 0 ? 'زيادة بمقدار '.number_format($diff, 2).' ج.م' : 'عجز بمقدار '.number_format(abs($diff), 2).' ج.م');

        return response()->json([
            'success' => true,
            'message' => "تم إغلاق وتقفيل الوردية رقم {$closedShift->shift_number} بنجاح ({$diffStatus}) ✓",
            'data' => (new CashShiftResource($closedShift))->resolve(),
            'diff_status' => $diffStatus,
        ], 200);
    }

    /**
     * Get Z-Report data for thermal print
     */
    public function zReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('daily_journal.view') && ! $user->can('pos.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $this->findShiftOr404($request, $id, 'zReport');

        $report = $this->getShiftZReportAction->execute($id);

        return response()->json([
            'success' => true,
            'report' => $report,
        ], 200);
    }

    /**
     * STOR-2 (CTO Q13): the permission was already checked (403), so a denial here can only be the
     * shift's store (or, with $storeId, a shift outside the active store): answered exactly like a
     * missing shift (404).
     */
    private function findShiftOr404(Request $request, int $id, string $ability, ?int $storeId = null): void
    {
        $user = $request->user();
        $shift = CashShift::query()->select(['id', 'store_id'])->find($id);

        $visible = $shift instanceof CashShift
            && $user instanceof User
            && ($storeId === null || (int) $shift->store_id === $storeId)
            && Gate::forUser($user)->allows($ability, $shift);

        if (! $visible) {
            throw (new ModelNotFoundException)->setModel(CashShift::class, [$id]);
        }
    }
}
