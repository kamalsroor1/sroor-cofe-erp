<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Models\Item;
use App\Models\StoreStock;
use App\Services\InventoryAnalyticsService;

final class GetInventoryValuationReportAction
{
    public function __construct(
        private readonly InventoryAnalyticsService $inventoryAnalyticsService
    ) {}

    /**
     * Compute Inventory items valuation at cost and retail, plus ABC categorization
     */
    public function execute(ReportFilterDTO $dto): array
    {
        $stockCostValuation = '0.000';
        $stockSellingValuation = '0.000';
        $inventoryItems = [];

        if ($dto->store_id) {
            $storeStocks = StoreStock::with('item')
                ->where('store_id', $dto->store_id)
                ->whereHas('item', fn($q) => $q->where('is_active', true))
                ->get();

            foreach ($storeStocks as $stk) {
                $item = $stk->item;
                if (!$item) continue;

                $qty = (string)($stk->quantity ?? '0.000');
                $costPrice = (string)($item->cost_price ?? '0.000');
                $sellingPrice = (string)($stk->custom_selling_price ?: $item->selling_price);

                $costVal = bcmul($qty, $costPrice, 3);
                $sellVal = bcmul($qty, $sellingPrice, 3);
                $profit = bcsub($sellVal, $costVal, 3);

                $stockCostValuation = bcadd($stockCostValuation, $costVal, 3);
                $stockSellingValuation = bcadd($stockSellingValuation, $sellVal, 3);

                $inventoryItems[] = [
                    'id'            => $item->id,
                    'name'          => $item->name,
                    'code'          => $item->code,
                    'category'      => $item->category,
                    'unit'          => $item->unit,
                    'current_stock' => (float)$qty,
                    'cost_price'    => (float)$costPrice,
                    'selling_price' => (float)$sellingPrice,
                    'cost_val'      => (float)$costVal,
                    'sell_val'      => (float)$sellVal,
                    'profit'        => (float)$profit,
                ];
            }
        } else {
            $allItems = Item::where('is_active', true)->get();
            foreach ($allItems as $itm) {
                $qty = (string)($itm->current_stock ?? '0.000');
                $costPrice = (string)($itm->cost_price ?? '0.000');
                $sellingPrice = (string)($itm->selling_price ?? '0.000');

                $costVal = bcmul($qty, $costPrice, 3);
                $sellVal = bcmul($qty, $sellingPrice, 3);
                $profit = bcsub($sellVal, $costVal, 3);

                $stockCostValuation = bcadd($stockCostValuation, $costVal, 3);
                $stockSellingValuation = bcadd($stockSellingValuation, $sellVal, 3);

                $inventoryItems[] = [
                    'id'            => $itm->id,
                    'name'          => $itm->name,
                    'code'          => $itm->code,
                    'category'      => $itm->category,
                    'unit'          => $itm->unit,
                    'current_stock' => (float)$qty,
                    'cost_price'    => (float)$costPrice,
                    'selling_price' => (float)$sellingPrice,
                    'cost_val'      => (float)$costVal,
                    'sell_val'      => (float)$sellVal,
                    'profit'        => (float)$profit,
                ];
            }
        }

        if ($dto->stock_filter === 'in_stock') {
            $inventoryItems = array_values(array_filter($inventoryItems, fn($i) => $i['current_stock'] > 0));
        } elseif ($dto->stock_filter === 'zero_stock') {
            $inventoryItems = array_values(array_filter($inventoryItems, fn($i) => $i['current_stock'] <= 0));
        }

        $expectedProfit = bcsub($stockSellingValuation, $stockCostValuation, 3);
        $abcData = $this->inventoryAnalyticsService->getAbcAnalysis($dto->from_date, $dto->to_date, $dto->store_id);

        return [
            'stock_cost_valuation'    => (float)$stockCostValuation,
            'stock_selling_valuation' => (float)$stockSellingValuation,
            'expected_stock_profit'   => (float)$expectedProfit,
            'items'                   => $inventoryItems,
            'abc_data'                => $abcData,
        ];
    }
}
