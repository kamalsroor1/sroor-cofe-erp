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
        $tab = (string)$request->input('tab', 'items');
        $search = trim((string)$request->input('search', ''));
        $perPage = (int)$request->input('per_page', 15);

        $result = $this->getTrashRecordsAction->execute($tab, $search, $perPage);

        return response()->json([
            'success'    => true,
            'tab'        => $result['tab'],
            'data'       => $result['records'],
            'counts'     => $result['counts'],
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * Restore record
     */
    public function restore(string $type, int $id): JsonResponse
    {
        try {
            $this->restoreTrashRecordAction->execute($type, $id);

            return response()->json([
                'success' => true,
                'message' => __('common.restored_success') ?: 'تم استرجاع السجل بنجاح ✓',
            ]);
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
    public function forceDelete(string $type, int $id): JsonResponse
    {
        try {
            $this->forceDeleteTrashRecordAction->execute($type, $id);

            return response()->json([
                'success' => true,
                'message' => __('common.force_deleted_success') ?: 'تم الحذف النهائي للسجل بنجاح',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
