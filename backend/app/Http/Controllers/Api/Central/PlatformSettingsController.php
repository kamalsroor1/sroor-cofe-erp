<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Central\Platform\DeletePlatformAssetAction;
use App\Actions\Central\Platform\UpdatePlatformSettingsAction;
use App\Actions\Central\Platform\UploadPlatformAssetAction;
use App\DTOs\Branding\PlatformBrandingDTO;
use App\DTOs\Branding\UpdateLegacyPlatformSettingsDTO;
use App\DTOs\Central\PlatformSettingsDTO;
use App\Enums\PlatformAssetSlot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\UpdatePlatformBrandingRequest;
use App\Http\Requests\Central\UploadPlatformAssetRequest;
use App\Http\Requests\UpdatePlatformSettingsRequest;
use App\Http\Resources\Central\PlatformSettingsResource;
use App\Models\CentralUser;
use App\Services\Branding\PlatformBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BRND-2: platform (SaaS operator) settings and brand assets, routes/central.php:
 *   GET    /api/v1/super-admin/platform-settings                 super_admin.settings.view
 *   PUT    /api/v1/super-admin/platform-settings                 super_admin.settings.manage + step-up
 *   POST   /api/v1/super-admin/platform-settings/assets/{slot}   super_admin.settings.manage + step-up, throttle 10/min
 *   DELETE /api/v1/super-admin/platform-settings/assets/{slot}   super_admin.settings.manage + step-up, throttle 10/min
 * Legacy screen (deprecated, removed in BRND-11), same storage, unchanged contract:
 *   GET  /api/v1/super-admin/settings    POST /api/v1/super-admin/settings (step-up)
 */
final class PlatformSettingsController extends Controller
{
    public function __construct(
        private readonly PlatformBranding $branding,
        private readonly UpdatePlatformSettingsAction $updateAction,
        private readonly UploadPlatformAssetAction $uploadAction,
        private readonly DeletePlatformAssetAction $deleteAction,
    ) {}

    public function show(): JsonResponse
    {
        return $this->respond($this->branding->get());
    }

    public function update(UpdatePlatformBrandingRequest $request): JsonResponse
    {
        $branding = $this->updateAction->execute(PlatformSettingsDTO::fromArray($request->validated()), $this->operator($request));

        return $this->respond($branding, __('super.platform_settings_saved_success'));
    }

    public function storeAsset(UploadPlatformAssetRequest $request): JsonResponse
    {
        $branding = $this->uploadAction->execute($request->slot(), $request->upload(), $this->operator($request));

        return $this->respond($branding, __('super.platform_branding.asset_uploaded'));
    }

    public function destroyAsset(Request $request, string $slot): JsonResponse
    {
        $branding = $this->deleteAction->execute(PlatformAssetSlot::from($slot), $this->operator($request));

        return $this->respond($branding, __('super.platform_branding.asset_deleted'));
    }

    /** Legacy GET /super-admin/settings (unchanged keys). */
    public function legacyShow(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->legacyPayload($this->branding->get()),
        ]);
    }

    /** Legacy POST /super-admin/settings: an empty optional field leaves the value unchanged. */
    public function legacyUpdate(UpdatePlatformSettingsRequest $request): JsonResponse
    {
        $dto = PlatformSettingsDTO::fromLegacy(UpdateLegacyPlatformSettingsDTO::fromArray($request->validated()));
        $branding = $this->updateAction->execute($dto, $this->operator($request));

        return response()->json([
            'success' => true,
            'message' => __('super.platform_settings_saved_success'),
            'data' => $this->legacyPayload($branding),
        ]);
    }

    private function respond(PlatformBrandingDTO $branding, ?string $message = null): JsonResponse
    {
        return (new PlatformSettingsResource($branding))
            ->additional(array_filter(['success' => true, 'message' => $message], static fn ($value): bool => $value !== null))
            ->response();
    }

    /**
     * @return array{platform_name: string, platform_subtitle: string, support_email: string, support_phone: string}
     */
    private function legacyPayload(PlatformBrandingDTO $branding): array
    {
        return [
            'platform_name' => $branding->name,
            'platform_subtitle' => $branding->subtitle,
            'support_email' => $branding->supportEmail,
            'support_phone' => $branding->supportPhone,
        ];
    }

    /** AuthenticateCentral guarantees an active CentralUser on every routes/central.php route. */
    private function operator(Request $request): CentralUser
    {
        $user = $request->user();
        abort_unless($user instanceof CentralUser, 401, __('central_auth.unauthenticated'));

        return $user;
    }
}
