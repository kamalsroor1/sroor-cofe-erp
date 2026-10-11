<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Settings\DeleteTenantBrandAssetAction;
use App\Actions\Settings\UploadTenantBrandAssetAction;
use App\Enums\TenantLogoVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UploadTenantBrandAssetRequest;
use App\Http\Resources\BrandingResource;
use Illuminate\Http\JsonResponse;

/**
 * BRND-5: shop logo management (settings.manage, route middleware + FormRequest).
 * POST   /api/v1/settings/branding/logo/{variant}  multipart `file`
 * DELETE /api/v1/settings/branding/logo/{variant}
 */
final class TenantBrandAssetController extends Controller
{
    public function __construct(
        private readonly UploadTenantBrandAssetAction $uploadAction,
        private readonly DeleteTenantBrandAssetAction $deleteAction,
    ) {}

    public function store(UploadTenantBrandAssetRequest $request): JsonResponse
    {
        $branding = $this->uploadAction->execute($request->variant(), $request->sanitizedImage());

        return response()->json([
            'success' => true,
            'message' => __('branding.logo_uploaded'),
            'data' => BrandingResource::tenantBlock($branding),
        ]);
    }

    public function destroy(TenantLogoVariant $variant): JsonResponse
    {
        $branding = $this->deleteAction->execute($variant);

        return response()->json([
            'success' => true,
            'message' => __('branding.logo_deleted'),
            'data' => BrandingResource::tenantBlock($branding),
        ]);
    }
}
