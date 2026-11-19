<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Reports\GetCustomerAgingReportAction;
use App\Actions\Reports\GetSalesByCashierReportAction;
use App\Actions\Reports\GetSalesByCategoryReportAction;
use App\Actions\Reports\GetSupplierPayablesReportAction;
use App\DTOs\Reports\ReportFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterReportRequest;
use App\Http\Resources\ReportTableResource;
use App\Services\ExportService;
use App\Support\ActiveStore;
use App\Support\TenantClock;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SHOP-05: sales by cashier, sales by category, customer debt aging and supplier payables, each
 * as JSON and as CSV (ExportService). FilterReportRequest: admin or reports.view, period at most
 * MAX_PERIOD_DAYS; the store comes from ActiveStore::readFilter (an inaccessible store_id is 403).
 */
final class ReportBreakdownController extends Controller
{
    public function __construct(
        private readonly GetSalesByCashierReportAction $salesByCashier,
        private readonly GetSalesByCategoryReportAction $salesByCategory,
        private readonly GetCustomerAgingReportAction $customerAging,
        private readonly GetSupplierPayablesReportAction $supplierPayables,
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

    public function salesByCashier(FilterReportRequest $request, ActiveStore $activeStore): JsonResponse
    {
        return response()->json((new ReportTableResource($this->salesByCashier->execute($this->dto($request, $activeStore))))->resolve($request));
    }

    public function salesByCategory(FilterReportRequest $request, ActiveStore $activeStore): JsonResponse
    {
        return response()->json((new ReportTableResource($this->salesByCategory->execute($this->dto($request, $activeStore))))->resolve($request));
    }

    public function customerAging(FilterReportRequest $request, ActiveStore $activeStore): JsonResponse
    {
        $dto = $this->dto($request, $activeStore);

        return response()->json((new ReportTableResource($this->customerAging->execute($dto), $dto->page, $dto->per_page))->resolve($request));
    }

    public function supplierPayables(FilterReportRequest $request, ActiveStore $activeStore): JsonResponse
    {
        $dto = $this->dto($request, $activeStore);

        return response()->json((new ReportTableResource($this->supplierPayables->execute($dto), $dto->page, $dto->per_page))->resolve($request));
    }

    public function exportSalesByCashier(FilterReportRequest $request, ActiveStore $activeStore): StreamedResponse
    {
        return $this->exportService->exportSalesByCashier($this->salesByCashier->execute($this->dto($request, $activeStore)));
    }

    public function exportSalesByCategory(FilterReportRequest $request, ActiveStore $activeStore): StreamedResponse
    {
        return $this->exportService->exportSalesByCategory($this->salesByCategory->execute($this->dto($request, $activeStore)));
    }

    public function exportCustomerAging(FilterReportRequest $request, ActiveStore $activeStore): StreamedResponse
    {
        return $this->exportService->exportCustomerAging($this->customerAging->execute($this->dto($request, $activeStore), paginate: false));
    }

    public function exportSupplierPayables(FilterReportRequest $request, ActiveStore $activeStore): StreamedResponse
    {
        return $this->exportService->exportSupplierPayables($this->supplierPayables->execute($this->dto($request, $activeStore), paginate: false));
    }
}
