<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AppVersions\CheckAppUpdateAction;
use App\Actions\AppVersions\DownloadLatestApkAction;
use App\DTOs\AppVersions\CheckUpdateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppVersions\CheckUpdateRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppUpdateController extends Controller
{
    /**
     * Check for newer app releases
     */
    public function checkUpdate(CheckUpdateRequest $request, CheckAppUpdateAction $action): JsonResponse
    {
        $dto = CheckUpdateDTO::fromRequest($request->validated());
        $result = $action->execute($dto);

        return response()->json($result);
    }

    /**
     * Alias for checkUpdate
     */
    public function checkVersion(CheckUpdateRequest $request, CheckAppUpdateAction $action): JsonResponse
    {
        return $this->checkUpdate($request, $action);
    }

    /**
     * Download the latest APK binary
     */
    public function downloadLatestApk(DownloadLatestApkAction $action): BinaryFileResponse
    {
        $platform = request('platform', 'android');
        return $action->execute($platform);
    }

    /**
     * Alias for downloadLatestApk
     */
    public function downloadApk(DownloadLatestApkAction $action): BinaryFileResponse
    {
        return $this->downloadLatestApk($action);
    }
}
