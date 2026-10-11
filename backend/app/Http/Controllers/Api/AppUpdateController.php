<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AppVersions\CheckAppUpdateAction;
use App\Actions\AppVersions\DownloadLatestApkAction;
use App\DTOs\AppVersions\CheckUpdateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppVersions\CheckUpdateRequest;
use App\Http\Resources\AppUpdateCheckResource;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public in-app update endpoints (central app_versions, any host). APP-3 / APP-7.
 */
final class AppUpdateController extends Controller
{
    public function __construct(
        private readonly CheckAppUpdateAction $checkAppUpdateAction,
        private readonly DownloadLatestApkAction $downloadLatestApkAction,
    ) {}

    /** GET /api/v1/app/check-update (alias /app/version). */
    public function checkVersion(CheckUpdateRequest $request): AppUpdateCheckResource
    {
        return new AppUpdateCheckResource(
            $this->checkAppUpdateAction->execute(CheckUpdateDTO::fromArray($request->validated())),
        );
    }

    /** GET /api/v1/app/download-apk?platform=&version_code= (alias /app/download-latest-apk). */
    public function downloadApk(CheckUpdateRequest $request): BinaryFileResponse
    {
        return $this->downloadLatestApkAction->execute(CheckUpdateDTO::fromArray($request->validated()));
    }
}
