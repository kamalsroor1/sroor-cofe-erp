<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * @deprecated OPS-11 (Q-B13): tenant deletion is disabled. Nothing reachable may call this;
 *             DELETE /api/v1/super-admin/tenants/{id} returns 403 and super admins suspend instead.
 *             The TenantDeleted pipeline no longer runs Jobs\DeleteDatabase.
 */
class DeleteTenantAction
{
    public function execute(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            $tenant->domains()->delete();
            $tenant->delete();
        });
    }
}
