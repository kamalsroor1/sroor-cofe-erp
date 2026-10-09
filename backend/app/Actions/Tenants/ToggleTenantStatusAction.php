<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use Carbon\CarbonInterface;

/**
 * Legacy super-admin status toggle, kept until IDEN-1.4/3.4 route it through
 * TransitionTenantStatusAction. Interim rules so it stays consistent with the
 * IDEN-3.2 lifecycle columns:
 * - `trial` extends `trial_ends_at` (a trial has no paid period).
 * - `active` always ends with a paid period: extended by `extend_days`, or one month
 *   from now when none is set yet.
 * - every status change stamps `status_changed_at`.
 */
class ToggleTenantStatusAction
{
    public function execute(Tenant $tenant, string $status, int $extendDays = 0): Tenant
    {
        $updateData = ['status' => $status];

        if ($status !== $tenant->status) {
            $updateData['status_changed_at'] = now();
        }

        if ($status === 'trial') {
            if ($extendDays > 0) {
                $updateData['trial_ends_at'] = $this->extend($tenant->trial_ends_at, $extendDays);
            }
        } elseif ($extendDays > 0) {
            $updateData['subscription_ends_at'] = $this->extend($tenant->subscription_ends_at, $extendDays);
        } elseif ($status === 'active' && $tenant->subscription_ends_at === null) {
            $updateData['subscription_ends_at'] = now()->addMonth();
        }

        $tenant->update($updateData);

        return $tenant;
    }

    private function extend(?CarbonInterface $currentEnd, int $days): CarbonInterface
    {
        $base = $currentEnd !== null && $currentEnd->isFuture() ? $currentEnd->copy() : now();

        return $base->addDays($days);
    }
}
