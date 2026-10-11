<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Shifts\GetDailyJournalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetDailyJournalRequest;
use App\Support\ActiveStore;
use App\Support\TenantClock;
use Illuminate\Http\JsonResponse;

final class DailyJournalController extends Controller
{
    public function __construct(
        private readonly GetDailyJournalAction $getDailyJournalAction,
        private readonly TenantClock $tenantClock,
    ) {}

    /**
     * Get Daily Journal ledger and cash metrics
     */
    public function index(GetDailyJournalRequest $request, ActiveStore $activeStore): JsonResponse
    {
        $date = (string) $request->input('date', $this->tenantClock->today());

        // STOR-2: one branch's journal: the active store (a store_id is access-checked, 403;
        // `all` without a concrete store_id is 422).
        $journal = $this->getDailyJournalAction->execute($date, $activeStore->singleStoreId($request));

        return response()->json([
            'success' => true,
            'data' => $journal,
        ], 200);
    }
}
