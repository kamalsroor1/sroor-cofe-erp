<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Reports\GetCustomersSalesReportAction;
use App\Actions\Reports\GetExpensesBreakdownReportAction;
use App\Actions\Reports\GetInventoryValuationReportAction;
use App\Actions\Reports\GetItemsProfitabilityReportAction;
use App\Actions\Reports\GetProfitLossReportAction;
use App\Actions\Reports\GetStockDriftReportAction;
use App\Actions\Reports\GetStoresComparativeReportAction;
use App\Actions\Reports\GetTreasuryReportAction;
use App\DTOs\Reports\ReportFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterReportRequest;
use App\Http\Requests\StockDriftReportRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\ActiveStore;
use App\Support\CostVisibility;
use App\Support\ProfitVisibility;
use App\Support\TenantClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportController extends Controller
{
    /**
     * PERF-01: rows per embedded list of the comprehensive bundle (item_profits, inventory
     * items, ABC lists, treasury ledger). The full lists are paged on their own endpoints.
     */
    public const COMPREHENSIVE_LIST_LIMIT = 100;

    /** Rows of /reports/top-items. */
    public const TOP_ITEMS_LIMIT = 20;

    public function __construct(
        private readonly GetProfitLossReportAction $getProfitLossReportAction,
        private readonly GetItemsProfitabilityReportAction $getItemsProfitabilityReportAction,
        private readonly GetStoresComparativeReportAction $getStoresComparativeReportAction,
        private readonly GetCustomersSalesReportAction $getCustomersSalesReportAction,
        private readonly GetExpensesBreakdownReportAction $getExpensesBreakdownReportAction,
        private readonly GetInventoryValuationReportAction $getInventoryValuationReportAction,
        private readonly GetTreasuryReportAction $getTreasuryReportAction,
        private readonly GetStockDriftReportAction $getStockDriftReportAction,
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
            return ProfitVisibility::storeRows($rows);
        }

        return ProfitVisibility::storeRows(array_values(array_filter(
            $rows,
            fn (array $row): bool => $user instanceof User && ActiveStore::canAccess($user, (int) $row['id']),
        )));
    }

    /**
     * @return array{current_page: int, per_page: int, total: int, last_page: int}
     */
    private function pageMeta(int $page, int $perPage, int $total): array
    {
        return [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
        ];
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
            'metrics' => ProfitVisibility::summary($result['summary']),
            'summary' => ProfitVisibility::summary($result['summary']),
        ], 200);
    }

    /**
     * Items Profitability & Sales Report
     */
    public function items(FilterReportRequest $request, ActiveStore $activeStore): JsonResponse
    {
        $dto = $this->buildDTO($request, $activeStore);
        $items = $this->getItemsProfitabilityReportAction->execute($dto, $dto->page, $dto->per_page);

        return response()->json([
            'success' => true,
            'data' => ProfitVisibility::itemRows($items['rows']),
            'totals' => ProfitVisibility::itemRow($items['totals']),
            'meta' => $this->pageMeta($dto->page, $dto->per_page, $items['total']),
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
        $inventory = ProfitVisibility::storeRows($this->getInventoryValuationReportAction->execute($dto, showUnitCost: CostVisibility::allows($request->user())));

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
        $treasury = $this->getTreasuryReportAction->execute($dto, $dto->per_page, $dto->page);
        $treasury['meta'] = $this->pageMeta($dto->page, $dto->per_page, $treasury['ledger_total']);

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
        // PERF-01: every embedded list is capped (first page of COMPREHENSIVE_LIST_LIMIT rows);
        // totals are always over the whole period. `meta` says how many rows each list has.
        $dto = $this->buildDTO($request, $activeStore)->withPage(1, self::COMPREHENSIVE_LIST_LIMIT);

        $summary = $this->getProfitLossReportAction->execute($dto);
        $items = $this->getItemsProfitabilityReportAction->execute($dto, 1, self::COMPREHENSIVE_LIST_LIMIT);
        $stores = $this->visibleStoreRows($request, $this->getStoresComparativeReportAction->execute($dto));
        $customers = $this->getCustomersSalesReportAction->execute($dto);
        $expenses = $this->getExpensesBreakdownReportAction->execute($dto);
        // The ABC analysis reuses the per-item sales the items report has just grouped.
        $inventory = ProfitVisibility::storeRows($this->getInventoryValuationReportAction->execute($dto, $items['sold_by_item'], CostVisibility::allows($request->user())));
        $treasury = $this->getTreasuryReportAction->execute($dto, self::COMPREHENSIVE_LIST_LIMIT, 1);
        $treasury['meta'] = $this->pageMeta(1, self::COMPREHENSIVE_LIST_LIMIT, $treasury['ledger_total']);

        return response()->json([
            'success' => true,
            'period' => $summary['period'],
            'summary' => ProfitVisibility::summary($summary['summary']),
            'item_profits' => ProfitVisibility::itemRows($items['rows']),
            'item_profits_totals' => ProfitVisibility::itemRow($items['totals']),
            'store_breakdown' => $stores,
            'customer_sales' => $customers,
            'expenses_breakdown' => $expenses,
            'inventory_data' => $inventory,
            'treasury_data' => $treasury,
            'meta' => [
                'list_limit' => self::COMPREHENSIVE_LIST_LIMIT,
                'item_profits' => ['total' => $items['total'], 'shown' => count($items['rows'])],
                'inventory_items' => ['total' => $inventory['meta']['total'], 'shown' => count($inventory['items'])],
                'treasury_ledger' => ['total' => $treasury['ledger_total'], 'shown' => count($treasury['ledger_entries'])],
            ],
        ], 200);
    }

    /**
     * Top Selling & Most Profitable Coffee Items
     */
    public function topItems(FilterReportRequest $request, ActiveStore $activeStore): JsonResponse
    {
        // BUG-01 review: validated like every other report (an unparseable date is a 422, not a
        // 500); FilterReportRequest::authorize() is the same admin-or-reports.view check.
        $dto = $this->buildDTO($request, $activeStore);
        $items = $this->getItemsProfitabilityReportAction->execute($dto, 1, self::TOP_ITEMS_LIMIT);

        return response()->json([
            'success' => true,
            'top_items' => ProfitVisibility::itemRows($items['rows']),
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
        if (! CostVisibility::allows($user)) {
            $movements->each(fn (StockMovement $movement) => $movement->makeHidden('unit_cost'));
        }

        return response()->json([
            'success' => true,
            // SEC-1 (M6): shaped by ItemResource (cost only with items.view_cost), never the raw model.
            'item' => (new ItemResource($item))->resolve(),
            'movements' => $movements,
        ], 200);
    }

    /**
     * Stock Drift Report (SUM(store_stocks.quantity) vs items.current_stock)
     */
    public function stockDrift(StockDriftReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 25);
        $search = isset($validated['search']) ? (string) $validated['search'] : null;

        $result = $this->getStockDriftReportAction->execute($page, $perPage, $search);

        return response()->json([
            'success' => true,
            'data' => $result['items'],
            'meta' => $result['meta'],
        ], 200);
    }
}
