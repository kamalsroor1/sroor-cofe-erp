<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AppVersions\CheckAppUpdateAction;
use App\Actions\AppVersions\DownloadLatestApkAction;
use App\DTOs\AppVersions\CheckUpdateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppVersions\CheckUpdateRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AppUpdateController extends Controller
{
    public function __construct(
        private readonly CheckAppUpdateAction $checkAppUpdateAction,
        private readonly DownloadLatestApkAction $downloadLatestApkAction
    ) {}

    /**
     * Check for new mobile app updates (Database-driven OTA In-App Updater)
     */
    public function checkVersion(CheckUpdateRequest $request): JsonResponse
    {
        $platform = (string) $request->input('platform', 'android');
        $versionCode = (int) $request->input('version_code', 1);
        $versionName = (string) $request->input('current_version', $request->input('version_name', '1.0.0'));

        $dto = new CheckUpdateDTO(
            platform: $platform,
            versionCode: $versionCode,
            versionName: $versionName
        );

        $result = $this->checkAppUpdateAction->execute($dto);

        $latest = is_array($result['latest_version'] ?? null) ? $result['latest_version'] : [];
        $hasUpdate = (bool) ($result['has_update'] ?? false);
        $isForce = (bool) ($result['is_force_update'] ?? false);

        return response()->json([
            'success'             => true,
            'has_update'          => $hasUpdate,
            'force_update'        => $isForce,
            'current_app_version' => $versionName,
            'latest_version'      => $latest['version_name'] ?? $versionName,
            'latest_version_code' => $latest['version_code'] ?? $versionCode,
            'download_url'        => $latest['download_url'] ?? url('/api/v1/app/download-apk'),
            'file_size'           => $latest['file_size'] ?? '18.5 MB',
            'file_size_bytes'     => $latest['file_size_bytes'] ?? 0,
            'release_notes_ar'    => $latest['release_notes_ar'] ?? '',
            'release_notes'       => !empty($latest['release_notes_ar']) ? explode("\n", (string)$latest['release_notes_ar']) : [],
            'published_at'        => $latest['published_at'] ?? now()->toDateTimeString(),
            'title'               => $isForce ? 'تحديث إلزامي جديد متاح 🚀' : 'تحديث جديد متاح للتحميل 🚀',
            'message'             => $hasUpdate
                ? "يتوفر إصدار جديد (" . ($latest['version_name'] ?? $versionName) . ") من تطبيق ERP."
                : 'أنت تستخدم أحدث إصدار من التطبيق.',
        ]);
    }

    /**
     * Download the latest APK file directly
     */
    public function downloadApk(CheckUpdateRequest $request): BinaryFileResponse
    {
        $platform = (string) $request->input('platform', 'android');
        return $this->downloadLatestApkAction->execute($platform);
    }
}
