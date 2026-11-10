<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Landing;

use App\Actions\Landing\GetPublicPlansAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * PRD-07: GET /api/v1/public/plans. No authentication, no tenancy, central DB only.
 * Throttled by `public-api`. The language follows X-Locale (SetRequestLocale, ar|en).
 */
final class PublicPlanController extends Controller
{
    public function __construct(
        private readonly GetPublicPlansAction $getPublicPlans,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->getPublicPlans->execute(app()->getLocale()),
        ]);
    }
}
