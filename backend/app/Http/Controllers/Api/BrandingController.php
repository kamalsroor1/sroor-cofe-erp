<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Branding\GetBrandingAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\BrandingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BRND-3: GET /api/v1/branding — public (before login, throttle:public-api). Platform brand
 * plus, when the request resolved a tenant, the shop's public brand. Allowlist only.
 */
final class BrandingController extends Controller
{
    public function __construct(
        private readonly GetBrandingAction $getBrandingAction,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => (new BrandingResource($this->getBrandingAction->execute()))->resolve($request),
        ]);
    }
}
