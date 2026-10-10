<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Actions\AppVersions\CreateAppVersionAction;
use App\DTOs\AppVersions\StoreAppVersionDTO;
use App\Enums\CentralAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppVersions\StoreAppVersionRequest;
use App\Models\AppVersion;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SuperAdminAppVersionController extends Controller
{
    public function __construct(private readonly CentralAuditLogger $auditLogger) {}

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

        $this->audit($request, CentralAuditEvent::AppVersionCreated, $version);

        return response()->json([
            'message' => __('super.release_published_success'),
            'version' => $version,
        ], 201);
    }

    /**
     * Toggle release status
     */
    public function toggleActive(Request $request, AppVersion $appVersion): JsonResponse
    {
        $appVersion->update(['is_active' => ! $appVersion->is_active]);

        $this->audit($request, CentralAuditEvent::AppVersionToggled, $appVersion);

        return response()->json([
            'message' => __('super.version_status_toggled'),
            'is_active' => $appVersion->is_active,
        ]);
    }

    /**
     * Delete release
     */
    public function destroy(Request $request, AppVersion $appVersion): JsonResponse
    {
        // Audited before the delete so the subject id and version are still readable.
        $this->audit($request, CentralAuditEvent::AppVersionDeleted, $appVersion);

        if ($appVersion->apk_path && Storage::disk('public')->exists($appVersion->apk_path)) {
            Storage::disk('public')->delete($appVersion->apk_path);
        }

        $appVersion->delete();

        return response()->json([
            'message' => __('super.version_deleted_success'),
        ]);
    }

    /** Central audit row for a release change (actor = the authenticated CentralUser). */
    private function audit(Request $request, CentralAuditEvent $event, AppVersion $version): void
    {
        $user = $request->user();

        $this->auditLogger->record(
            $event,
            [
                'version_name' => $version->version_name,
                'version_code' => $version->version_code,
                'is_active' => (bool) $version->is_active,
            ],
            actor: $user instanceof CentralUser ? $user : null,
            subject: $version,
        );
    }
}
