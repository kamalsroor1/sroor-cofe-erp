<?php

declare(strict_types=1);

namespace App\Actions\Branding;

use App\Enums\TenantLogoVariant;
use App\Models\Tenant;
use App\Services\Branding\TenantBranding;
use Throwable;

/**
 * BRND-5: the stored logo of a host-resolved tenant (public route, before login).
 *
 * The tenant comes from TenantHostResolver (host only). Tenancy is initialized for the
 * rest of the request, like ResolveApiTenancy does for the API group, so the tenant DB and
 * the tenant-suffixed disk are read, and ThrottleTenantMisses never counts a known shop
 * without a logo as an unknown-workspace probe.
 */
final class GetTenantLogoAction
{
    public function __construct(
        private readonly TenantBranding $tenantBranding,
    ) {}

    /**
     * @return array{binary: string, mime: string, sha256: string}|null
     */
    public function execute(Tenant $tenant, TenantLogoVariant $variant): ?array
    {
        try {
            tenancy()->initialize($tenant);
        } catch (Throwable $e) {
            // e.g. the tenant database is missing: answer 404, never leak the cause.
            report($e);

            if (tenancy()->initialized) {
                tenancy()->end();
            }

            return null;
        }

        return $this->tenantBranding->logoFile($variant);
    }
}
