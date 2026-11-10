<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\RejectPlanChangeRequestDTO;
use App\Enums\Billing\PlanChangeRequestStatus;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\CentralUser;
use App\Models\PlanChangeRequest;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Console: reject a pending request with a reason (3-500 chars, shown to the shop). Only a
 * `pending` request can be rejected (409 `billing.request_not_pending`). Central transaction,
 * request row locked.
 */
final class RejectPlanChangeRequestAction
{
    public function __construct(private readonly CentralAuditLogger $audit) {}

    public function execute(RejectPlanChangeRequestDTO $dto, CentralUser $operator): PlanChangeRequest
    {
        $connection = DB::connection((string) (new PlanChangeRequest)->getConnectionName());

        return $connection->transaction(function () use ($dto, $operator): PlanChangeRequest {
            $request = PlanChangeRequest::query()->whereKey($dto->requestId)->lockForUpdate()->firstOrFail();

            if (! $request->isPending()) {
                throw BillingException::requestNotPending();
            }

            $request->status = PlanChangeRequestStatus::Rejected;
            $request->rejection_reason = $dto->reason;
            $request->reviewed_by = (int) $operator->getKey();
            $request->reviewed_at = now();
            $request->save();

            $this->audit->record(
                CentralAuditEvent::PlanChangeRejected,
                ['request_id' => (int) $request->getKey(), 'kind' => $request->kind->value, 'reason' => $dto->reason],
                $operator,
                $request,
                (string) $request->tenant_id,
            );

            return $request;
        });
    }
}
