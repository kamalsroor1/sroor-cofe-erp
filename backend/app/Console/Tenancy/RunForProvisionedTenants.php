<?php

declare(strict_types=1);

namespace App\Console\Tenancy;

use Stancl\Tenancy\Commands\Run;

/** `tenants:run` limited to provisioned tenants by default (see DefaultsToProvisionedTenants). */
final class RunForProvisionedTenants extends Run
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
