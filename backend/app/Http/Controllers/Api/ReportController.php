<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Reports\GetCustomersSalesReportAction;
use App\Actions\Reports\GetExpensesBreakdownReportAction;
use App\Actions\Reports\GetInventoryValuationReportAction;
use App\Actions\Reports\GetItemsProfitabilityReportAction;
use App\Actions\Reports\GetProfitLossReportAction;
use App\Actions\Reports\GetStoresComparativeReportAction;
use App\Actions\Reports\GetTreasuryReportAction;
use App\DTOs\Reports\ReportFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterReportRequest;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportController extends Controller
{
    public function __construct(
        private readonly GetProfitLossReportAction $getProfitLossReportAction,
        private readonly GetItemsProfitabilityReportAction $getItemsProfitabilityReportAction,
        private readonly GetStoresComparativeReportAction $getStoresComparativeReportAction,
        private readonly GetCustomersSalesReportAction $getCustomersSalesReportAction,
        private readonly GetExpensesBreakdownReportAction $getExpensesBreakdownReportAction,
        private readonly GetInventoryValuationReportAction $getInventoryValuationReportAction,
        private readonly GetTreasuryReportAction $getTreasuryReportAction
    ) {}

    private function buildDTO(FilterReportRequest|Request $request): ReportFilterDTO
    {
        $headerStoreId = $request->header('X-Store-Id')
            ?: auth()->user()?->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        return ReportFilterDTO::fromArray(
            $request->all(),
            $headerStoreId ? (int)$headerStoreId : null
        );
    }

    /**
     * Profit & Loss Executive Summary
     */
    public function summary(FilterReportRequest $request): JsonResponse
    {
        $dto = $this->buildDTO($request);
        $result = $this->getProfitLossReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'period'  => $result['period'],
            'metrics' => $result['summary'],
            'summary' => $result['summary'],
        ], 200);
    }

    /**
     * Items Profitability & Sales Report
     */
    public function items(FilterReportRequest $request): JsonResponse
    {
        $dto = $this->buildDTO($request);
        $items = $this->getItemsProfitabilityReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'data'    => $items,
        ], 200);
    }

    /**
     * Stores Comparative Performance Report
     */
    public function stores(FilterReportRequest $request): JsonResponse
    {
        $dto = $this->buildDTO($request);
        $stores = $this->getStoresComparativeReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'data'    => $stores,
        ], 200);
    }

    /**
     * Customers Sales & Receivables Report
     */
    public function customers(FilterReportRequest $request): JsonResponse
    {
        $dto = $this->buildDTO($request);
        $customers = $this->getCustomersSalesReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'data'    => $customers,
        ], 200);
    }

    /**
     * Operational Expenses Breakdown Report
     */
    public function expenses(FilterReportRequest $request): JsonResponse
    {
        $dto = $this->buildDTO($request);
        $expenses = $this->getExpensesBreakdownReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'data'    => $expenses,
        ], 200);
    }

    /**
     * Inventory Valuation & ABC Analysis Report
     */
    public function inventory(FilterReportRequest $request): JsonResponse
    {
        $dto = $this->buildDTO($request);
        $inventory = $this->getInventoryValuationReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'data'    => $inventory,
        ], 200);
    }

    /**
     * Treasury & Inflows/Outflows Report
     */
    public function treasury(FilterReportRequest $request): JsonResponse
    {
        $dto = $this->buildDTO($request);
        $treasury = $this->getTreasuryReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'data'    => $treasury,
        ], 200);
    }

    /**
     * Comprehensive Multi-Dimension Report Bundle
     */
    public function comprehensive(FilterReportRequest $request): JsonResponse
    {
        $dto = $this->buildDTO($request);

        $summary = $this->getProfitLossReportAction->execute($dto);
        $items = $this->getItemsProfitabilityReportAction->execute($dto);
        $stores = $this->getStoresComparativeReportAction->execute($dto);
        $customers = $this->getCustomersSalesReportAction->execute($dto);
        $expenses = $this->getExpensesBreakdownReportAction->execute($dto);
        $inventory = $this->getInventoryValuationReportAction->execute($dto);
        $treasury = $this->getTreasuryReportAction->execute($dto);

        return response()->json([
            'success'            => true,
            'period'             => $summary['period'],
            'summary'            => $summary['summary'],
            'item_profits'       => $items,
            'store_breakdown'    => $stores,
            'customer_sales'     => $customers,
            'expenses_breakdown' => $expenses,
            'inventory_data'     => $inventory,
            'treasury_data'      => $treasury,
        ], 200);
    }

    /**
     * Top Selling & Most Profitable Coffee Items
     */
    public function topItems(Request $request): JsonResponse
    {
        $dto = $this->buildDTO($request);
        $items = $this->getItemsProfitabilityReportAction->execute($dto);

        return response()->json([
            'success'   => true,
            'top_items' => array_slice($items, 0, 20),
        ], 200);
    }

    /**
     * Item Movement Card
     */
    public function itemCard(Request $request, int|string $itemId): JsonResponse
    {
        $item = Item::findOrFail($itemId);
        $storeId = $request->input('store_id') 
            ?? auth()->user()?->getCurrentStore()?->id 
            ?? Store::getMainStore()?->id;

        $movements = StockMovement::where('item_id', $itemId)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'success'   => true,
            'item'      => $item,
            'movements' => $movements,
        ], 200);
    }
}
