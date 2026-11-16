<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Plans\CreatePlanAction;
use App\Actions\Plans\GetSuperAdminPlansDataAction;
use App\Actions\Plans\UpdatePlanAction;
use App\DTOs\Plans\PlanDTO;
use App\DTOs\Plans\PlanWriteResult;
use App\Http\Controllers\Api\Central\Concerns\ResolvesCentralOperator;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

/**
 * Plan editor of the console (moved out of SuperAdminApiController, PLAN-EDIT), routes/central.php:
 *   GET  /api/v1/super-admin/plans        super_admin.plans.view
 *   POST /api/v1/super-admin/plans        super_admin.plans.manage + step-up
 *   PUT  /api/v1/super-admin/plans/{id}   super_admin.plans.manage + step-up
 */
final class PlanController extends Controller
{
    use ResolvesCentralOperator;

    public function __construct(
        private readonly GetSuperAdminPlansDataAction $getPlans,
        private readonly CreatePlanAction $createPlan,
        private readonly UpdatePlanAction $updatePlan,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->getPlans->execute(),
        ]);
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $result = $this->createPlan->execute(PlanDTO::fromArray($request->validated()), $this->operator($request));

        return $this->written($result, (string) __('super.plan_created_success', ['name' => $result->plan->name]), 201);
    }

    public function update(UpdatePlanRequest $request, int $id): JsonResponse
    {
        abort_unless(Plan::query()->whereKey($id)->exists(), 404, __('super.plan_not_found'));

        $result = $this->updatePlan->execute($id, PlanDTO::fromArray($request->validated()), $this->operator($request));

        return $this->written($result, (string) __('super.plan_updated_success', ['name' => $result->plan->name]), 200);
    }

    private function written(PlanWriteResult $result, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => (new PlanResource($result->plan))->resolve(),
            // Hidden feature keys the request sent: accepted, never written (PRD-10).
            'ignored_features' => $result->ignoredFeatures,
        ], $status);
    }
}
