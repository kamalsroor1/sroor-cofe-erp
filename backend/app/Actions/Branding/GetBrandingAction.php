<?php

declare(strict_types=1);

namespace App\Actions\Branding;

use App\DTOs\Branding\PlatformBrandingDTO;
use App\DTOs\Branding\TenantBrandingDTO;
use App\Services\Branding\PlatformBranding;
use App\Services\Branding\TenantBranding;

/**
 * BRND-3: the effective branding of the current request: the platform brand (always) and
 * the shop brand when a tenant was resolved for this request (domain, X-Tenant on the API
 * host…), else null. Presentation (and the public allowlist) is BrandingResource's job.
 */
final class GetBrandingAction
{
    public function __construct(
        private readonly PlatformBranding $platformBranding,
        private readonly TenantBranding $tenantBranding,
    ) {}

    /**
     * @return array{platform: PlatformBrandingDTO, tenant: TenantBrandingDTO|null}
     */
    public function execute(): array
    {
        return [
            'platform' => $this->platformBranding->get(),
            'tenant' => tenancy()->initialized ? $this->tenantBranding->get() : null,
        ];
    }
}
