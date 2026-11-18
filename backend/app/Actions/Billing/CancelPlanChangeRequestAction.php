<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\PlanChangeRequestStatus;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\PlanChangeRequest;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Tenant context: the shop withdraws its own PENDING request, or (PLAN-10 review) its SCHEDULED
 * downgrade to the free plan before the sweep applies it. Another shop's request is a 404 (the
 * lookup is scoped to the resolved tenant); a request that is no longer open is a 409. The request
 * row is locked, and MoveTenantToFreePlanAction locks it too, so a cancel racing the sweep either
 * wins (the move then finds it not due) or gets the 409.
 */
final class CancelPlanChangeRequestAction
{
    public function __construct(private readonly CentralAuditLogger $audit) {}

    public function execute(Tenant $tenant, int $requestId): PlanChangeRequest
    {
        $connection = DB::connection((string) (new PlanChangeRequest)->getConnectionName());

        return $connection->transaction(function () use ($tenant, $requestId): PlanChangeRequest {
            $request = PlanChangeRequest::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($requestId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $request->status->isCancellable()) {
                throw BillingException::requestNotPending();
            }

            $previous = $request->status;
            $request->status = PlanChangeRequestStatus::Cancelled;
            $request->save();

            $this->audit->record(
                CentralAuditEvent::PlanChangeCancelled,
                ['request_id' => (int) $request->getKey(), 'kind' => $request->kind->value, 'was' => $previous->value],
                null,
                $request,
                (string) $tenant->getKey(),
            );

            return $request;
        });
    }
}
