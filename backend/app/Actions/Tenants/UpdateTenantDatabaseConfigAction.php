<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Tenant;

class UpdateTenantDatabaseConfigAction
{
    public function execute(Tenant $tenant, array $data): Tenant
    {
        $update = [];
        if (array_key_exists('tenancy_db_name', $data)) {
            $update['tenancy_db_name'] = $data['tenancy_db_name'];
        }
        if (array_key_exists('tenancy_db_username', $data)) {
            $update['tenancy_db_username'] = $data['tenancy_db_username'];
        }
        if (array_key_exists('tenancy_db_password', $data)) {
            $update['tenancy_db_password'] = $data['tenancy_db_password'];
        }

        $tenant->update($update);

        return $tenant;
    }
}
