<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Contracts\TenantProvisionerInterface;
use App\DTOs\CreateTenantDTO;
use App\Models\Tenant;

/**
 * Register a tenant and queue its workspace (OPS-2). The central part (tenant row in
 * `pending`, domains, subscription, audit) is one transaction; the database is created,
 * migrated and seeded by App\Jobs\ProvisionTenantJob after the commit.
 * The returned tenant carries its `provisioning_status`.
 */
class ProvisionTenantAction
{
    public function __construct(
        protected TenantProvisionerInterface $provisioner
    ) {}

    public function execute(CreateTenantDTO $dto): Tenant
    {
        return $this->provisioner->provision($dto);
    }
}
