<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Tenant;

/**
 * Hook used by App\Actions\Tenants\RevokeTenantTokensAction to end every open
 * super-admin impersonation session of a tenant when it is blocked (IDEN-3.3).
 *
 * Not bound yet: the impersonation sessions arrive in W3 (IDEN-2.10
 * EndImpersonationSessionAction). Binding an implementation in the container is all it
 * takes; the revoke action calls it only when bound. The impersonation PATs themselves
 * live in the tenant DB and are already deleted by the revoke action.
 */
interface EndsTenantImpersonationSessions
{
    /**
     * End every open impersonation session of $tenant (central records + audit).
     *
     * @return int number of sessions ended
     */
    public function endAllForTenant(Tenant $tenant): int;
}
