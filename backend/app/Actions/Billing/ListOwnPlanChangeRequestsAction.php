<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\PlanChangeRequest;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Tenant context: the shop's OWN plan-change / add-on requests, newest first. The tenant id comes
 * from the resolved tenant, never from the request.
 */
final class ListOwnPlanChangeRequestsAction
{
    /**
     * @return LengthAwarePaginator<int, PlanChangeRequest>
     */
    public function execute(Tenant $tenant, int $perPage): LengthAwarePaginator
    {
        return PlanChangeRequest::query()
            ->with(['currentPlan', 'requestedPlan', 'addon', 'invoice'])
            ->where('tenant_id', $tenant->getKey())
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $perPage)));
    }
}
