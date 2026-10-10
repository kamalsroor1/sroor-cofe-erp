<?php

declare(strict_types=1);

namespace App\Console\Tenancy;

use Stancl\Tenancy\Commands\Seed;

/** `tenants:seed` limited to provisioned tenants by default (see DefaultsToProvisionedTenants). */
final class SeedProvisionedTenants extends Seed
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
