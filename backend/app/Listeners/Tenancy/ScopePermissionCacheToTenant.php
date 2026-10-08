<?php

declare(strict_types=1);

namespace App\Listeners\Tenancy;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\TenancyBootstrapped;

/**
 * Scopes spatie's permission cache key to the active tenant so one tenant's
 * role/permission map is never served to another tenant.
 */
final class ScopePermissionCacheToTenant
{
    public function handle(TenancyBootstrapped $event): void
    {
        $registrar = app(PermissionRegistrar::class);

        $registrar->cacheKey = config('permission.cache.key').'.tenant.'.$event->tenancy->tenant->getTenantKey();

        // Drop any permissions already loaded in memory from central or another tenant.
        $registrar->clearPermissionsCollection();
    }
}
