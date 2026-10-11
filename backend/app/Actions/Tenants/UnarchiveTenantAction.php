<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\CentralUser;
use App\Models\Tenant;
use App\Support\Tenancy\TenantStatusTransition;

/**
 * POST /api/v1/super-admin/tenants/{id}/unarchive (OPS-9, CTO W1 Q2): an archived tenant goes
 * back to the status it had before (suspended / cancelled), until it is purged.
 *
 * Everything is enforced by the single status writer under the row lock: not archived →
 * 409 subscription.not_archived; purged / purging → 409 subscription.tenant_purged. The
 * archive columns (archived_at, final_backup_id, archive_state) are cleared there, the
 * final backup itself stays in the ledger (never pruned).
 */
final class UnarchiveTenantAction
{
    public function __construct(
        private readonly TransitionTenantStatusAction $transition,
    ) {}

    public function execute(string $tenantId, CentralUser $operator, ?string $note): Tenant
    {
        return $this->transition->execute(TenantStatusTransition::unarchive($tenantId, $operator, $note))->refresh();
    }
}
