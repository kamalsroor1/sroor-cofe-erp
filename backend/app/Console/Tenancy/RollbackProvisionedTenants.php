<?php

declare(strict_types=1);

namespace App\Console\Tenancy;

use Stancl\Tenancy\Commands\Rollback;

/** `tenants:rollback` limited to provisioned tenants by default (see DefaultsToProvisionedTenants). */
final class RollbackProvisionedTenants extends Rollback
{
    use DefaultsToProvisionedTenants;

    public function handle()
    {
        if (! $this->restrictToProvisionedTenants()) {
            return self::SUCCESS;
        }

        return parent::handle();
    }
}
