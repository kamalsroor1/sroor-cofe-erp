<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Tenant;

class UpdateTenantDatabaseConfigAction
{
    public function execute(Tenant , array ): Tenant
    {
         = [];
        if (array_key_exists('tenancy_db_name', )) {
            ['tenancy_db_name'] = ['tenancy_db_name'];
        }
        if (array_key_exists('tenancy_db_username', )) {
            ['tenancy_db_username'] = ['tenancy_db_username'];
        }
        if (array_key_exists('tenancy_db_password', )) {
            ['tenancy_db_password'] = ['tenancy_db_password'];
        }

        ->update();

        return ;
    }
}
