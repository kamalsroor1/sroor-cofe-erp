<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\DataExport\GetLatestDataExportAction;
use App\Actions\DataExport\RequestDataExportAction;
use App\Actions\DataExport\ResolveDataExportDownloadAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\DataExport\DataExportRequest;
use App\Http\Resources\DataExportResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PRD-17: the shop's self-service full data export (admin only, DataExportRequest).
 * GET  /api/v1/settings/data-export                       latest export (or null)
 * POST /api/v1/settings/data-export                       queue a new export (202)
 * GET  /api/v1/settings/data-export/{dataExport}/download signed + expiring ZIP download
 */
final class DataExportController extends Controller
{
    public function show(DataExportRequest $request, GetLatestDataExportAction $action): JsonResponse
    {
        $export = $action->execute();

        return response()->json([
            'success' => true,
            'data' => $export === null ? null : DataExportResource::make($export)->resolve($request),
        ]);
    }

    public function store(DataExportRequest $request, RequestDataExportAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $export = $action->execute($user);

        return response()->json([
            'success' => true,
            'message' => __('settings.data_export.requested'),
            'data' => DataExportResource::make($export)->resolve($request),
        ], 202);
    }

    public function download(DataExportRequest $request, int $dataExport, ResolveDataExportDownloadAction $action): StreamedResponse
    {
        return $action->execute($dataExport);
    }
}
