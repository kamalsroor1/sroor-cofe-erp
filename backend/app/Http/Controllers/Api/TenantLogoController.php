<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Branding\GetTenantLogoAction;
use App\Enums\TenantLogoVariant;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantHostResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * BRND-5: GET /api/v1/branding/logo/{light|dark} — the shop logo, public (login screen,
 * manifest), bound to the tenant of the request HOST only (TenantHostResolver: X-Tenant and
 * ?tenant= are ignored; central/admin/unknown/not-ready host or no logo = 404).
 *
 * The bytes were re-encoded at upload; Content-Type is fixed by the stored extension, never
 * sniffed (nosniff), served inline, cacheable 5 minutes with a sha256 ETag (304 on match).
 */
final class TenantLogoController extends Controller
{
    private const MAX_AGE_SECONDS = 300;

    public function __construct(
        private readonly TenantHostResolver $tenantHostResolver,
        private readonly GetTenantLogoAction $getTenantLogoAction,
    ) {}

    public function show(Request $request, TenantLogoVariant $variant): Response|JsonResponse
    {
        $tenant = $this->tenantHostResolver->resolve($request);
        $logo = $tenant !== null ? $this->getTenantLogoAction->execute($tenant, $variant) : null;

        if ($logo === null) {
            return response()->json(['success' => false, 'message' => __('branding.logo_not_found')], 404);
        }

        $response = new Response($logo['binary'], 200, [
            'Content-Type' => $logo['mime'],
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE_SECONDS);
        $response->setEtag($logo['sha256']);
        $response->isNotModified($request);

        return $response;
    }
}
