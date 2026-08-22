<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

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
