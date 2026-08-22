<?php

declare(strict_types=1);

namespace App\Actions\Blends;

use App\Models\Item;

final class CalculateBlendCostAction
{
    /**
     * Calculate cost, price, and margins for a coffee formulation
     */
    public function execute(array $data): array
    {
        $targetWeightGrams = (float)($data['target_weight_grams'] ?? 250);
        $cardamomGrams = (float)($data['cardamom_grams'] ?? 0);
        $components = (array)($data['components'] ?? []);

        $calculatedComponents = [];
        $totalPercentage = 0.0;
        $totalCost = '0.000';
        $totalPrice = '0.000';

        foreach ($components as $comp) {
            $item = Item::find($comp['item_id']);
            if (!$item) {
                continue;
            }

            $percentage = (float)($comp['percentage'] ?? 0);
            $totalPercentage += $percentage;

            $grams = round(($targetWeightGrams * $percentage) / 100, 2);
            $kg = bcdiv((string)$grams, '1000', 4);

            $costPrice = (string)$item->cost_price;
            $sellingPrice = (string)($comp['unit_price'] ?? $item->price_retail ?? $item->selling_price);

            $lineCost = bcmul($kg, $costPrice, 3);
            $linePrice = bcmul($kg, $sellingPrice, 3);

            $totalCost = bcadd($totalCost, $lineCost, 3);
            $totalPrice = bcadd($totalPrice, $linePrice, 3);

            $calculatedComponents[] = [
                'item_id'       => $item->id,
                'item_name'     => $item->name,
                'item_code'     => $item->code,
                'percentage'    => $percentage,
                'grams'         => $grams,
                'kg'            => (float)$kg,
                'cost_price'    => (float)$costPrice,
                'selling_price' => (float)$sellingPrice,
                'line_cost'     => (float)$lineCost,
                'line_price'    => (float)$linePrice,
                'current_stock' => (float)$item->current_stock,
            ];
        }

        // Add Cardamom cost if any (1.5 EGP cost, 2.5 EGP price per gram)
        if ($cardamomGrams > 0) {
            $cardamomCost = (string)($cardamomGrams * 1.5);
            $cardamomPrice = (string)($cardamomGrams * 2.5);
            $totalCost = bcadd($totalCost, $cardamomCost, 3);
            $totalPrice = bcadd($totalPrice, $cardamomPrice, 3);
        }

        $profitAmount = bcsub($totalPrice, $totalCost, 3);
        $profitMargin = bccomp($totalPrice, '0.000', 3) > 0
            ? round(((float)$profitAmount / (float)$totalPrice) * 100, 1)
            : 0.0;

        return [
            'target_weight_grams' => $targetWeightGrams,
            'cardamom_grams'      => $cardamomGrams,
            'total_percentage'    => $totalPercentage,
            'total_cost'          => (float)$totalCost,
            'total_price'         => (float)$totalPrice,
            'profit_amount'       => (float)$profitAmount,
            'profit_margin'       => $profitMargin,
            'components'          => $calculatedComponents,
        ];
    }
}
