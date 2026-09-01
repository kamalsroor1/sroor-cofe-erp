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
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('daily_journal.view') && !$user->can('pos.access') && !$user->can('pos.sell')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: $user?->getCurrentStore()?->id;

        $perPage = max(1, min(200, (int)$request->input('per_page', 20)));

        $query = CashShift::with(['user', 'store']);

        if ($storeId && $storeId !== 'all') {
            $query->where('store_id', (int)$storeId);
        }

        $shifts = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => CashShiftResource::collection($shifts->items())->resolve(),
            'meta'    => [
                'current_page' => $shifts->currentPage(),
                'last_page'    => $shifts->lastPage(),
                'per_page'     => $shifts->perPage(),
                'total'        => $shifts->total(),
            ],
        ], 200);
    }

    /**
     * Get Current Active Shift & Live Shift Metrics
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('daily_journal.view') && !$user->can('pos.access') && !$user->can('pos.sell')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: $user?->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        $result = $this->getActiveShiftAction->execute($storeId ? (int)$storeId : null);

        if (!$result) {
            return response()->json([
                'success'      => true,
                'has_active'   => false,
                'active_shift' => null,
                'metrics'      => null,
            ], 200);
        }

        return response()->json([
            'success'      => true,
            'has_active'   => true,
            'active_shift' => (new CashShiftResource($result['shift']))->resolve(),
            'metrics'      => $result['metrics'],
        ], 200);
    }

    /**
     * Open a new cashier shift
     */
    public function open(OpenShiftRequest $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: auth()->user()?->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        $dto = OpenShiftDTO::fromArray($request->validated(), $storeId ? (int)$storeId : null);
        $userId = (int)auth()->id();

        $shift = $this->openShiftAction->execute($dto, $userId);

        return response()->json([
            'success' => true,
            'message' => "تم فتح وردية العمل رقم {$shift->shift_number} بنجاح ✓",
            'data'    => (new CashShiftResource($shift))->resolve(),
        ], 201);
    }

    /**
     * Close the active cashier shift and generate Z-Report
     */
    public function close(CloseShiftRequest $request): JsonResponse
    {
        $shiftId = (int)($request->input('shift_id') ?: $request->route('id'));
        if (!$shiftId) {
            $storeId = $request->header('X-Store-Id')
                ?: $request->input('store_id')
                ?: auth()->user()?->getCurrentStore()?->id;
            $shiftId = (int)CashShift::where('status', 'open')->when($storeId, fn($q) => $q->where('store_id', $storeId))->value('id');
        }

        $dto = CloseShiftDTO::fromArray($shiftId, $request->validated());
        $closedShift = $this->closeShiftAction->execute($dto);

        $diff = (float)$closedShift->cash_difference;
        $diffStatus = $diff == 0 ? 'مطابقة للدرج' : ($diff > 0 ? "زيادة بمقدار " . number_format($diff, 2) . " ج.م" : "عجز بمقدار " . number_format(abs($diff), 2) . " ج.م");

        return response()->json([
            'success'     => true,
            'message'     => "تم إغلاق وتقفيل الوردية رقم {$closedShift->shift_number} بنجاح ({$diffStatus}) ✓",
            'data'        => (new CashShiftResource($closedShift))->resolve(),
            'diff_status' => $diffStatus,
        ], 200);
    }

    /**
     * Get Z-Report data for thermal print
     */
    public function zReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('daily_journal.view') && !$user->can('pos.access') && !$user->can('pos.sell')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $report = $this->getShiftZReportAction->execute($id);

        return response()->json([
            'success' => true,
            'report'  => $report,
        ], 200);
    }
}
