<?php

declare(strict_types=1);

namespace App\Console\Tenancy;

use Stancl\Tenancy\Commands\Migrate;

/** `tenants:migrate` limited to provisioned tenants by default (see DefaultsToProvisionedTenants). */
final class MigrateProvisionedTenants extends Migrate
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
