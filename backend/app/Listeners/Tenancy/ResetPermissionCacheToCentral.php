<?php

declare(strict_types=1);

namespace App\Listeners\Tenancy;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\RevertedToCentralContext;

/**
 * Restores spatie's default (central) permission cache key when tenancy ends.
 */
final class ResetPermissionCacheToCentral
{
    public function handle(RevertedToCentralContext $event): void
    {
        $registrar = app(PermissionRegistrar::class);

        $registrar->cacheKey = config('permission.cache.key');

        // Drop the tenant's permissions loaded in memory.
        $registrar->clearPermissionsCollection();
    }
}
