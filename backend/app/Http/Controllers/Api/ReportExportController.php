<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Reports\GetItemsProfitabilityReportAction;
use App\Actions\Reports\GetProfitLossReportAction;
use App\DTOs\Reports\ReportFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterReportRequest;
use App\Services\ExportService;
use App\Support\ActiveStore;
use App\Support\CostVisibility;
use App\Support\ProfitVisibility;
use App\Support\TenantClock;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SHOP-05: CSV exports of the existing reports (P&L summary, top items, item profitability).
 * Same request, store resolution and actions as ReportController's JSON endpoints; the plan
 * gate (ProfitVisibility) is applied exactly as there, and a user without items.view_cost
 * (CostVisibility) gets no cost / profit column at all.
 */
final class ReportExportController extends Controller
{
    public function __construct(
        private readonly GetProfitLossReportAction $profitLoss,
        private readonly GetItemsProfitabilityReportAction $itemsProfitability,
        private readonly ExportService $exportService,
        private readonly TenantClock $tenantClock,
    ) {}

    private function dto(FilterReportRequest $request, ActiveStore $activeStore): ReportFilterDTO
    {
        return ReportFilterDTO::fromArray(
            $request->safe()->except('store_id'),
            $activeStore->readFilter($request),
            $this->tenantClock->businessNow(),
        );
    }

    public function summary(FilterReportRequest $request, ActiveStore $activeStore): StreamedResponse
    {
        $result = $this->profitLoss->execute($this->dto($request, $activeStore));

        return $this->exportService->exportProfitLossSummary(
            $result['period'],
            ProfitVisibility::summary($result['summary']),
            CostVisibility::allows($request->user()),
        );
    }

    public function topItems(FilterReportRequest $request, ActiveStore $activeStore): StreamedResponse
    {
        $items = $this->itemsProfitability->execute($this->dto($request, $activeStore), 1, ReportController::TOP_ITEMS_LIMIT);

        return $this->exportService->exportTopItems(ProfitVisibility::itemRows($items['rows']), CostVisibility::allows($request->user()));
    }

    public function items(FilterReportRequest $request, ActiveStore $activeStore): StreamedResponse
    {
        // Every row of the period (the JSON endpoint pages them); totals cover every row either way.
        $items = $this->itemsProfitability->execute($this->dto($request, $activeStore), 1, PHP_INT_MAX);

        return $this->exportService->exportItemsProfitability([
            'rows' => ProfitVisibility::itemRows($items['rows']),
            'totals' => ProfitVisibility::itemRow($items['totals']),
        ], CostVisibility::allows($request->user()));
    }
}
