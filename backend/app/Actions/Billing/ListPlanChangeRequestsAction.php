<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\PlanChangeRequestStatus;
use App\Filters\Billing\PlanChangeStatusFilter;
use App\Filters\Billing\PlanChangeTenantFilter;
use App\Models\PlanChangeRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;

/**
 * Super-admin queue of plan-change / add-on requests (central DB). Filters: status, tenant_id.
 * The pending queue is oldest-first (answer in request order), every other view newest-first.
 */
final class ListPlanChangeRequestsAction
{
    public function execute(Request $request): LengthAwarePaginator
    {
        $query = PlanChangeRequest::query()->with(['tenant', 'currentPlan', 'requestedPlan', 'addon', 'invoice', 'reviewer']);

        $filtered = app(Pipeline::class)
            ->send($query)
            ->through([PlanChangeStatusFilter::class, PlanChangeTenantFilter::class])
            ->thenReturn();

        $pendingOnly = $request->query('status') === PlanChangeRequestStatus::Pending->value;
        $filtered->orderBy('id', $pendingOnly ? 'asc' : 'desc');

        return $filtered->paginate(max(1, min(100, (int) $request->query('per_page', 20))));
    }
}
