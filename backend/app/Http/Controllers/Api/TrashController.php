<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Trash\ForceDeleteTrashRecordAction;
use App\Actions\Trash\GetTrashRecordsAction;
use App\Actions\Trash\RestoreTrashRecordAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class TrashController extends Controller
{
    public function __construct(
        private readonly GetTrashRecordsAction $getTrashRecordsAction,
        private readonly RestoreTrashRecordAction $restoreTrashRecordAction,
        private readonly ForceDeleteTrashRecordAction $forceDeleteTrashRecordAction
    ) {}

    /**
     * Get trashed records and counts
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('trash.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $tab = (string)$request->input('tab', 'items');
        $search = trim((string)$request->input('search', ''));
        $perPage = max(1, min(200, (int)$request->input('per_page', 15)));

        $result = $this->getTrashRecordsAction->execute($tab, $search, $perPage);

        return response()->json([
            'success'    => true,
            'tab'        => $result['tab'],
            'data'       => $result['records'],
            'counts'     => $result['counts'],
            'pagination' => $result['pagination'],
        ], 200);
    }

    /**
     * Restore record
     */
    public function restore(Request $request, string $type, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('trash.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        try {
            $this->restoreTrashRecordAction->execute($type, $id);

            return response()->json([
                'success' => true,
                'message' => __('common.restored_success') ?: 'تم استرجاع السجل بنجاح ✓',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Force delete record
     */
    public function forceDelete(Request $request, string $type, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('trash.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        try {
            $this->forceDeleteTrashRecordAction->execute($type, $id);

            return response()->json([
                'success' => true,
                'message' => __('common.force_deleted_success') ?: 'تم الحذف النهائي للسجل بنجاح',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
