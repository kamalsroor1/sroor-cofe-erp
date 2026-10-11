<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Treasury\GetTreasurySummaryAction;
use App\Http\Controllers\Controller;
use App\Support\ActiveStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TreasuryController extends Controller
{
    public function __construct(
        private readonly GetTreasurySummaryAction $getTreasurySummaryAction,
    ) {}

    /**
     * Treasury & Financial Summary for active store
     */
    public function summary(Request $request, ActiveStore $activeStore): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('daily_journal.view') && ! $user->can('reports.view')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        // STOR-2: one branch: the active store (a store_id is access-checked, 403; `all` is 422).
        $summary = $this->getTreasurySummaryAction->execute($activeStore->singleStoreId($request));

        return response()->json(['success' => true, ...$summary], 200);
    }
}
