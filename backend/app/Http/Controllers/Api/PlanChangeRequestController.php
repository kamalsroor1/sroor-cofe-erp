<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Billing\CancelPlanChangeRequestAction;
use App\Actions\Billing\ListOwnPlanChangeRequestsAction;
use App\Actions\Billing\RequestPlanChangeAction;
use App\DTOs\Billing\RequestPlanChangeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ListOwnPlanChangeRequestsRequest;
use App\Http\Requests\Billing\RequestPlanChangeRequest;
use App\Http\Resources\Billing\PlanChangeRequestResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * The shop asks support for a plan change or an add-on (tenant permission `billing.manage`):
 *   GET  /api/v1/billing/plan-change-requests
 *   POST /api/v1/billing/plan-change-requests              (allowed while read-only / past due)
 *   POST /api/v1/billing/plan-change-requests/{id}/cancel  (allowed while read-only / past due)
 * Every lookup is scoped to the tenant resolved for the request, never to an id from the client.
 */
final class PlanChangeRequestController extends Controller
{
    public function __construct(
        private readonly ListOwnPlanChangeRequestsAction $listAction,
        private readonly RequestPlanChangeAction $requestAction,
        private readonly CancelPlanChangeRequestAction $cancelAction,
    ) {}

    public function index(ListOwnPlanChangeRequestsRequest $request): JsonResponse
    {
        return PlanChangeRequestResource::collection($this->listAction->execute($this->tenant(), $request->perPage()))
            ->additional(['success' => true])
            ->response();
    }

    public function store(RequestPlanChangeRequest $request): JsonResponse
    {
        $user = $request->user();

        $created = $this->requestAction->execute(RequestPlanChangeDTO::fromArray(
            $request->validated(),
            (string) $this->tenant()->getKey(),
            $user === null ? null : (string) $user->getAttribute('name'),
            $user === null ? null : (int) $user->getKey(),
        ));

        return (new PlanChangeRequestResource($created->load(['currentPlan', 'requestedPlan', 'addon', 'invoice'])))
            ->additional(['success' => true, 'message' => __('billing.messages.plan_change_requested')])
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(int $id): JsonResponse
    {
        $cancelled = $this->cancelAction->execute($this->tenant(), $id);

        return (new PlanChangeRequestResource($cancelled->load(['currentPlan', 'requestedPlan', 'addon', 'invoice'])))
            ->additional(['success' => true, 'message' => __('billing.messages.plan_change_cancelled')])
            ->response();
    }

    private function tenant(): Tenant
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
