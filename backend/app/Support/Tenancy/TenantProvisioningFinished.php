<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * SIGN-12: ProvisionTenantJob finished a tenant's workspace: `succeeded` true once the tenant is
 * `ready` (database migrated and seeded), false once it was marked `failed` for good (last attempt,
 * timeout or permanent error). A super-admin retry that later succeeds fires it again with true.
 *
 * Dispatched after the central commit; carries the tenant id only. Listeners must never throw
 * into the job (the provisioning outcome is already stored).
 */
final class TenantProvisioningFinished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $tenantId,
        public readonly bool $succeeded,
    ) {}
}
