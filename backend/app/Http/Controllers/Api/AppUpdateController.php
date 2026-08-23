<?php

namespace App\Http\Controllers\Api;

use App\Actions\AppVersions\CheckAppUpdateAction;
use App\Actions\AppVersions\DownloadLatestApkAction;
use App\DTOs\AppVersions\CheckUpdateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppVersions\CheckUpdateRequest;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppUpdateController extends Controller
{
    /**
     * Check for new mobile app updates (Database-driven OTA In-App Updater)
     */
    public function checkVersion(Request $request, CheckAppUpdateAction $action): JsonResponse
    {
        $platform = $request->input('platform', 'android');
        $versionCode = (int) $request->input('version_code', 1);
        $versionName = (string) $request->input('current_version', $request->input('version_name', '1.0.0'));

        $dto = new CheckUpdateDTO(
            platform: $platform,
            versionCode: $versionCode,
            versionName: $versionName
        );

        $result = $action->execute($dto);

        // Map response for standard payload compatibility
        $latest = is_array($result['latest_version'] ?? null) ? $result['latest_version'] : [];

        $hasUpdate = (bool) ($result['has_update'] ?? false);
        $isForce = (bool) ($result['is_force_update'] ?? false);

        return response()->json([
            'success' => true,
            'has_update' => $hasUpdate,
            'force_update' => $isForce,
            'current_app_version' => $versionName,
            'latest_version' => $latest['version_name'] ?? $versionName,
            'latest_version_code' => $latest['version_code'] ?? $versionCode,
            'download_url' => $latest['download_url'] ?? url('/api/v1/app/download-apk'),
            'file_size' => $latest['file_size'] ?? '18.5 MB',
            'file_size_bytes' => $latest['file_size_bytes'] ?? 0,
            'release_notes_ar' => $latest['release_notes_ar'] ?? '',
            'release_notes' => !empty($latest['release_notes_ar']) ? explode("\n", $latest['release_notes_ar']) : [],
            'published_at' => $latest['published_at'] ?? now()->toDateTimeString(),
            'title' => $isForce ? 'تحديث إلزامي جديد متاح 🚀' : 'تحديث جديد متاح للتحميل 🚀',
            'message' => $hasUpdate
                ? "يتوفر إصدار جديد (" . ($latest['version_name'] ?? $versionName) . ") من تطبيق ERP."
                : 'أنت تستخدم أحدث إصدار من التطبيق.',
        ]);
    }

    /**
     * Download the latest APK file directly
     */
    public function downloadApk(DownloadLatestApkAction $action)
    {
        $platform = request('platform', 'android');
        return $action->execute($platform);
    }
}
