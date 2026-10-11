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
use App\Models\User;
use App\Support\ActiveStore;
use App\Support\TenantClock;
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
        private readonly GetTreasuryReportAction $getTreasuryReportAction,
        private readonly TenantClock $tenantClock,
    ) {}

    /**
     * STOR-2: the report's store is the active store, or an access-checked query/body store_id
     * filter (403); every store (null) only through a verified `all` (header or store_id).
     */
    private function buildDTO(FilterReportRequest|Request $request, ActiveStore $activeStore): ReportFilterDTO
    {
        return ReportFilterDTO::fromArray(
            $request->except('store_id'),
            $activeStore->readFilter($request),
            $this->tenantClock->businessNow(),
        );
    }

    /**
     * STOR-2: the per-branch comparison lists every active branch. A user who may not see every
     * store only gets the rows of the branches they can access.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function visibleStoreRows(Request $request, array $rows): array
    {
        $user = $request->user();
        if ($user instanceof User && ActiveStore::canViewAll($user)) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $user instanceof User && ActiveStore::canAccess($user, (int) $row['id']),
        ));
    }

    /**
     * Profit & Loss Executive Summary
     */
    public function summary(FilterReportRequest $request, ActiveStore $activeStore): JsonResponse
    {
        $dto = $this->buildDTO($request, $activeStore);
        $result = $this->getProfitLossReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'period' => $result['period'],
            'metrics' => $result['summary'],
            'summary' => $result['summary'],
        ], 200);
    }

    /**
     * Items Profitability & Sales Report
     */
    public function items(FilterReportRequest $request, ActiveStore $activeStore): JsonResponse
    {
        $dto = $this->buildDTO($request, $activeStore);
        $items = $this->getItemsProfitabilityReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'data' => $items,
        ], 200);
    }

    /**
     * Stores Comparative Performance Report
     */
    public function stores(FilterReportRequest $request, ActiveStore $activeStore): JsonResponse
    {
        $dto = $this->buildDTO($request, $activeStore);
        $stores = $this->visibleStoreRows($request, $this->getStoresComparativeReportAction->execute($dto));

        return response()->json([
            'success' => true,
            'data' => $stores,
        ], 200);
    }

    /**
     * Customers Sales & Receivables Report
     */
    public function customers(FilterReportRequest $request, ActiveStore $activeStore): JsonResponse
    {
        $dto = $this->buildDTO($request, $activeStore);
        $customers = $this->getCustomersSalesReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ], 200);
    }

    /**
     * Operational Expenses Breakdown Report
     */
    public function expenses(FilterReportRequest $request, ActiveStore $activeStore): JsonResponse
    {
        $dto = $this->buildDTO($request, $activeStore);
        $expenses = $this->getExpensesBreakdownReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'data' => $expenses,
        ], 200);
    }

    /**
     * Inventory Valuation & ABC Analysis Report
     */
    public function inventory(FilterReportRequest $request, ActiveStore $activeStore): JsonResponse
    {
        $dto = $this->buildDTO($request, $activeStore);
        $inventory = $this->getInventoryValuationReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'data' => $inventory,
        ], 200);
    }

    /**
     * Treasury & Inflows/Outflows Report
     */
    public function treasury(FilterReportRequest $request, ActiveStore $activeStore): JsonResponse
    {
        $dto = $this->buildDTO($request, $activeStore);
        $treasury = $this->getTreasuryReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'data' => $treasury,
        ], 200);
    }

    /**
     * Comprehensive Multi-Dimension Report Bundle
     */
    public function comprehensive(FilterReportRequest $request, ActiveStore $activeStore): JsonResponse
    {
        $dto = $this->buildDTO($request, $activeStore);

        $summary = $this->getProfitLossReportAction->execute($dto);
        $items = $this->getItemsProfitabilityReportAction->execute($dto);
        $stores = $this->visibleStoreRows($request, $this->getStoresComparativeReportAction->execute($dto));
        $customers = $this->getCustomersSalesReportAction->execute($dto);
        $expenses = $this->getExpensesBreakdownReportAction->execute($dto);
        $inventory = $this->getInventoryValuationReportAction->execute($dto);
        $treasury = $this->getTreasuryReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'period' => $summary['period'],
            'summary' => $summary['summary'],
            'item_profits' => $items,
            'store_breakdown' => $stores,
            'customer_sales' => $customers,
            'expenses_breakdown' => $expenses,
            'inventory_data' => $inventory,
            'treasury_data' => $treasury,
        ], 200);
    }

    /**
     * Top Selling & Most Profitable Coffee Items
     */
    public function topItems(Request $request, ActiveStore $activeStore): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('reports.view')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $dto = $this->buildDTO($request, $activeStore);
        $items = $this->getItemsProfitabilityReportAction->execute($dto);

        return response()->json([
            'success' => true,
            'top_items' => array_slice($items, 0, 20),
        ], 200);
    }

    /**
     * Item Movement Card
     */
    public function itemCard(Request $request, ActiveStore $activeStore, int|string $itemId): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasRole('admin') && ! $user->can('reports.view')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $item = Item::findOrFail((int) $itemId);
        $storeId = $activeStore->readFilter($request);

        $movements = StockMovement::where('item_id', $itemId)
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'item' => $item,
            'movements' => $movements,
        ], 200);
    }
}
