<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Plans\ListPlanChangesAction;
use App\Actions\Plans\ListShopsNearLimitAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\NearLimitShopsRequest;
use App\Http\Resources\Central\NearLimitShopResource;
use App\Http\Resources\Central\PlanChangeLogResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;

/**
 * PLAN-14 read-only insights of the plan console (routes/central.php):
 *   GET /api/v1/super-admin/plans/{id}/changes   super_admin.audit.view   last console changes of a plan
 *   GET /api/v1/super-admin/near-limit-shops     super_admin.plans.manage  shops at >= 80% of a limit (upsell)
 */
final class PlanInsightsController extends Controller
{
    public function __construct(
        private readonly ListPlanChangesAction $listChanges,
        private readonly ListShopsNearLimitAction $listNearLimit,
    ) {}

    public function changes(int $id): JsonResponse
    {
        $plan = Plan::query()->find($id);
        abort_if($plan === null, 404, (string) __('super.plan_not_found'));

        return response()->json([
            'success' => true,
            'data' => PlanChangeLogResource::collection($this->listChanges->execute($plan))->resolve(),
        ]);
    }

    public function nearLimitShops(NearLimitShopsRequest $request): JsonResponse
    {
        $threshold = (int) $request->integer('threshold', ListShopsNearLimitAction::DEFAULT_THRESHOLD);
        $perPage = (int) $request->integer('per_page', 25);
        $page = (int) $request->integer('page', 1);

        // A refresh rescans every shop database: at most 2 a minute per operator (plain reads keep the route throttle).
        if ($request->boolean('refresh')) {
            abort_unless(
                RateLimiter::attempt('near-limit-refresh:'.$request->user()?->getAuthIdentifier(), 2, static fn (): bool => true, 60),
                429,
                (string) __('auth.too_many_requests'),
            );
        }

        $result = $this->listNearLimit->execute($threshold, $request->boolean('refresh'));

        $paginator = new LengthAwarePaginator(
            array_slice($result['rows'], ($page - 1) * $perPage, $perPage),
            count($result['rows']),
            $perPage,
            $page,
        );

        return response()->json([
            'success' => true,
            'data' => NearLimitShopResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'threshold' => $threshold,
                'scanned' => $result['scanned'],
                'capped' => $result['capped'],
                'failed' => $result['failed'],
                'generated_at' => $result['generated_at'],
            ],
        ]);
    }
}
