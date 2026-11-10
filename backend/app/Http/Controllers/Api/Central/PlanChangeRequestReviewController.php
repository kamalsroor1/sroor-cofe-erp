<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Billing\IssuePlanChangeInvoiceAction;
use App\Actions\Billing\ListPlanChangeRequestsAction;
use App\Actions\Billing\RejectPlanChangeRequestAction;
use App\DTOs\Billing\IssuePlanChangeInvoiceDTO;
use App\DTOs\Billing\RejectPlanChangeRequestDTO;
use App\Http\Controllers\Api\Central\Concerns\ResolvesCentralOperator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\InvoicePlanChangeRequestRequest;
use App\Http\Requests\Central\ListPlanChangeRequestsRequest;
use App\Http\Requests\Central\RejectPlanChangeRequestRequest;
use App\Http\Resources\Billing\PlanChangeRequestResource;
use App\Models\PlanChangeRequest;
use Illuminate\Http\JsonResponse;

/**
 * Plan-change / add-on request queue (routes/central.php):
 *   GET  /api/v1/super-admin/billing/plan-change-requests                    super_admin.billing.view
 *   POST /api/v1/super-admin/billing/plan-change-requests/{id}/invoice       super_admin.billing.manage + step-up
 *   POST /api/v1/super-admin/billing/plan-change-requests/{id}/reject        super_admin.billing.manage + step-up
 */
final class PlanChangeRequestReviewController extends Controller
{
    use ResolvesCentralOperator;

    private const RELATIONS = ['tenant', 'currentPlan', 'requestedPlan', 'addon', 'invoice', 'reviewer'];

    public function __construct(
        private readonly ListPlanChangeRequestsAction $listAction,
        private readonly IssuePlanChangeInvoiceAction $invoiceAction,
        private readonly RejectPlanChangeRequestAction $rejectAction,
    ) {}

    public function index(ListPlanChangeRequestsRequest $request): JsonResponse
    {
        return PlanChangeRequestResource::collection($this->listAction->execute($request))
            ->additional(['success' => true])
            ->response();
    }

    public function invoice(InvoicePlanChangeRequestRequest $request, int $changeRequest): JsonResponse
    {
        $issued = $this->invoiceAction->execute(
            IssuePlanChangeInvoiceDTO::fromArray($request->validated(), $changeRequest),
            $this->operator($request),
        );

        return $this->respond($issued, __('billing.messages.plan_change_invoiced'), 201);
    }

    public function reject(RejectPlanChangeRequestRequest $request, int $changeRequest): JsonResponse
    {
        $rejected = $this->rejectAction->execute(
            RejectPlanChangeRequestDTO::fromArray($request->validated(), $changeRequest),
            $this->operator($request),
        );

        return $this->respond($rejected, __('billing.messages.plan_change_rejected'), 200);
    }

    private function respond(PlanChangeRequest $changeRequest, string $message, int $status): JsonResponse
    {
        return (new PlanChangeRequestResource($changeRequest->load(self::RELATIONS)))
            ->additional(['success' => true, 'message' => $message])
            ->response()
            ->setStatusCode($status);
    }
}
