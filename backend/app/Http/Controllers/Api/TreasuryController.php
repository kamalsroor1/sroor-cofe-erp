<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Dashboard\GetOwnShiftDashboardAction;
use App\Actions\Treasury\GetOwnShiftTreasurySummaryAction;
use App\Actions\Treasury\GetTreasurySummaryAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActiveStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TreasuryController extends Controller
{
    public function __construct(
        private readonly GetTreasurySummaryAction $getTreasurySummaryAction,
        private readonly GetOwnShiftTreasurySummaryAction $getOwnShiftTreasurySummaryAction,
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
        $storeId = $activeStore->singleStoreId($request);

        // ROLE-01: balances, receivables and payables need reports.view; without it only the
        // caller's own shift / day movement.
        if ($user instanceof User && ! $user->can('reports.view')) {
            return response()->json(['success' => true, ...$this->getOwnShiftTreasurySummaryAction->execute($user, $storeId)], 200);
        }

        $summary = $this->getTreasurySummaryAction->execute($storeId);

        return response()->json(['success' => true, 'scope' => GetOwnShiftDashboardAction::SCOPE_STORE, ...$summary], 200);
    }
}
