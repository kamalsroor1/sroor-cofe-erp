<?php

declare(strict_types=1);

namespace Tests\Feature\Stores;

use App\Models\CashShift;
use App\Policies\ShiftPolicy;
use Tests\TenantTestCase;

/**
 * N4: a shift without a store id is never treated as "store 0". Reading follows
 * ActiveStore::canRead (only users who may see every store), closing needs a concrete
 * accessible store, so a branch user can neither read nor close it.
 */
final class ShiftPolicyNullStoreTest extends TenantTestCase
{
    public function test_a_shift_without_a_store_is_neither_readable_nor_closable_by_a_branch_user(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createTenantUser($tenant, 'cashier', ['daily_journal.close_shift', 'daily_journal.view']);
        $viewAll = $this->createTenantUser($tenant, null, ['stores.view_all', 'daily_journal.view']);
        $policy = new ShiftPolicy;

        $this->inTenant($tenant, function () use ($policy, $cashier, $viewAll): void {
            $shift = new CashShift;
            $shift->store_id = null;

            $this->assertFalse($policy->close($cashier, $shift));
            $this->assertFalse($policy->view($cashier, $shift));
            $this->assertFalse($policy->zReport($cashier, $shift));
            $this->assertFalse(ShiftPolicy::storeVisibleTo($cashier, $shift));

            // A user who may see every store can read it, still not close it without the ability.
            $this->assertTrue($policy->view($viewAll, $shift));
            $this->assertFalse($policy->close($viewAll, $shift));

            // The cashier's own store still works.
            $own = new CashShift;
            $own->store_id = (int) $cashier->default_store_id;
            $this->assertTrue($policy->close($cashier, $own));
        });
    }
}
