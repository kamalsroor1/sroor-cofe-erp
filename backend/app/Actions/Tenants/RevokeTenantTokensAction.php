<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Support\Tenancy\EndsTenantImpersonationSessions;
use Illuminate\Contracts\Container\Container;
use Laravel\Sanctum\Sanctum;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;

/**
 * Log every user of a tenant out (IDEN-3.3): deletes ALL Sanctum personal access tokens
 * in the tenant's own database (staff, devices and super-admin impersonation PATs alike),
 * then ends the tenant's impersonation sessions through the W3 hook when it is bound.
 *
 * Called after a tenant becomes blocked (suspended / cancelled / archived). Central
 * operator tokens (`central_personal_access_tokens`) are never touched.
 *
 * Tenancy is initialized by hand instead of $tenant->run(): run() does not restore the
 * previous context when the callback throws. Here the previous context (central, or
 * whichever tenant was active) is always restored in `finally`.
 */
final class RevokeTenantTokensAction
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return int number of tenant tokens deleted
     */
    public function execute(Tenant $tenant): int
    {
        $deleted = $this->deleteTenantTokens($tenant);

        if ($this->container->bound(EndsTenantImpersonationSessions::class)) {
            /** @var EndsTenantImpersonationSessions $sessions */
            $sessions = $this->container->make(EndsTenantImpersonationSessions::class);
            $sessions->endAllForTenant($tenant);
        }

        return $deleted;
    }

    private function deleteTenantTokens(Tenant $tenant): int
    {
        $tenancy = tenancy();
        $previous = $tenancy->initialized ? $tenancy->tenant : null;

        $tenancy->initialize($tenant);

        try {
            $model = Sanctum::personalAccessTokenModel();

            return (int) $model::query()->delete();
        } finally {
            if ($previous instanceof TenantContract && $previous->getTenantKey() !== $tenant->getTenantKey()) {
                $tenancy->initialize($previous);
            } elseif (! $previous instanceof TenantContract) {
                $tenancy->end();
            }
        }
    }
}
