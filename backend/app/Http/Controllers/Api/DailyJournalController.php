<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Shifts\GetDailyJournalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetDailyJournalRequest;
use App\Models\Store;
use Illuminate\Http\JsonResponse;

final class DailyJournalController extends Controller
{
    public function __construct(
        private readonly GetDailyJournalAction $getDailyJournalAction
    ) {}

    /**
     * Get Daily Journal ledger and cash metrics
     */
    public function index(GetDailyJournalRequest $request): JsonResponse
    {
        $date = (string)$request->input('date', now()->toDateString());
        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: auth()->user()?->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        $journal = $this->getDailyJournalAction->execute($date, $storeId ? (int)$storeId : null);

        return response()->json([
            'success' => true,
            'data'    => $journal,
        ], 200);
    }
}
