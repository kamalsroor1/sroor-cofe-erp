<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Shifts\GetDailyJournalPrintDataAction;
use App\Http\Requests\PrintDailyJournalRequest;
use App\Support\ReportStoreFilter;
use App\Support\TenantClock;
use Illuminate\Contracts\View\View;

/**
 * Daily journal A4 print page (Blade). Guarded by `auth` + `can:daily_journal.view` +
 * `store.access` at the route; the store filter is resolved and access-checked on the exact
 * value used (non-admins can never get "all").
 */
final class DailyJournalPrintController extends Controller
{
    public function __construct(
        private readonly GetDailyJournalPrintDataAction $getDailyJournalPrintDataAction,
        private readonly TenantClock $tenantClock,
    ) {}

    public function __invoke(PrintDailyJournalRequest $request): View
    {
        $storeId = ReportStoreFilter::resolve($request, $request->user());
        $date = (string) ($request->validated('date') ?? $this->tenantClock->today());

        return view('layouts.print-daily-journal-a4', $this->getDailyJournalPrintDataAction->execute($date, $storeId));
    }
}
