<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Actions\AppVersions\CreateAppVersionAction;
use App\DTOs\AppVersions\StoreAppVersionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppVersions\StoreAppVersionRequest;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SuperAdminAppVersionController extends Controller
{
    /**
     * List all releases
     */
    public function index(): JsonResponse
    {
        $versions = AppVersion::orderByDesc('version_code')->paginate(20);

        return response()->json([
            'versions' => $versions,
            'summary' => [
                'total_releases' => AppVersion::count(),
                'total_downloads' => (int) AppVersion::sum('download_count'),
                'active_version' => AppVersion::where('is_active', true)->orderByDesc('version_code')->value('version_name') ?? '1.0.0',
            ],
        ]);
    }

    /**
     * Store new APK release
     */
    public function store(StoreAppVersionRequest $request, CreateAppVersionAction $action): JsonResponse
    {
        $dto = StoreAppVersionDTO::fromRequest(
            $request->validated(),
            $request->file('apk_file')
        );

        $version = $action->execute($dto);

        return response()->json([
            'message' => 'تم نشر إصدار التطبيق الجديد بنجاح ✓',
            'version' => $version,
        ], 201);
    }

    /**
     * Toggle release status
     */
    public function toggleActive(AppVersion $appVersion): JsonResponse
    {
        $appVersion->update(['is_active' => !$appVersion->is_active]);

        return response()->json([
            'message' => 'تم تغيير حالة الإصدار بنجاح',
            'is_active' => $appVersion->is_active,
        ]);
    }

    /**
     * Delete release
     */
    public function destroy(AppVersion $appVersion): JsonResponse
    {
        if ($appVersion->apk_path && Storage::disk('public')->exists($appVersion->apk_path)) {
            Storage::disk('public')->delete($appVersion->apk_path);
        }

        $appVersion->delete();

        return response()->json([
            'message' => 'تم حذف الإصدار بنجاح',
        ]);
    }
}
