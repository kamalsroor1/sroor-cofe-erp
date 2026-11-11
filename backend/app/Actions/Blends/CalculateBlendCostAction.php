<?php

declare(strict_types=1);

namespace App\Actions\Blends;

use App\Models\Item;
use App\Support\Money\Decimal;

final class CalculateBlendCostAction
{
    /**
     * Calculate cost, price, and margins for a coffee formulation
     */
    public function execute(array $data): array
    {
        $targetWeightGrams = Decimal::normalize((string) ($data['target_weight_grams'] ?? '250'));
        $cardamomGrams = Decimal::normalize((string) ($data['cardamom_grams'] ?? '0'));
        $components = (array) ($data['components'] ?? []);

        $calculatedComponents = [];
        $totalPercentage = '0.000';
        $totalCost = '0.000';
        $totalPrice = '0.000';

        foreach ($components as $comp) {
            $item = Item::find($comp['item_id']);
            if (! $item) {
                continue;
            }

            $percentage = Decimal::normalize((string) ($comp['percentage'] ?? '0'));
            $totalPercentage = Decimal::add($totalPercentage, $percentage);

            // grams = target x percentage / 100, half-up at 2 decimals (as before).
            $grams = Decimal::round(bcdiv(bcmul($targetWeightGrams, $percentage, 6), '100', 8), 2);
            $kg = bcdiv($grams, '1000', 4);

            $costPrice = (string) $item->cost_price;
            $sellingPrice = (string) ($comp['unit_price'] ?? $item->price_retail ?? $item->selling_price);

            $lineCost = bcmul($kg, $costPrice, 3);
            $linePrice = bcmul($kg, $sellingPrice, 3);

            $totalCost = bcadd($totalCost, $lineCost, 3);
            $totalPrice = bcadd($totalPrice, $linePrice, 3);

            $calculatedComponents[] = [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'item_code' => $item->code,
                'percentage' => $percentage,
                'grams' => $grams,
                'kg' => $kg,
                'cost_price' => Decimal::normalize($costPrice),
                'selling_price' => Decimal::normalize($sellingPrice),
                'line_cost' => Decimal::normalize($lineCost),
                'line_price' => Decimal::normalize($linePrice),
                'current_stock' => Decimal::normalize((string) $item->current_stock),
            ];
        }

        // Add Cardamom cost if any (1.5 EGP cost, 2.5 EGP price per gram)
        if (Decimal::cmp($cardamomGrams, '0') > 0) {
            $totalCost = bcadd($totalCost, Decimal::mul($cardamomGrams, '1.5'), 3);
            $totalPrice = bcadd($totalPrice, Decimal::mul($cardamomGrams, '2.5'), 3);
        }

        $profitAmount = bcsub($totalPrice, $totalCost, 3);
        $profitMargin = bccomp($totalPrice, '0.000', 3) > 0
            ? Decimal::round(bcdiv(bcmul($profitAmount, '100', 6), $totalPrice, 8), 1)
            : '0.0';

        return [
            'target_weight_grams' => $targetWeightGrams,
            'cardamom_grams' => $cardamomGrams,
            'total_percentage' => $totalPercentage,
            'total_cost' => Decimal::normalize($totalCost),
            'total_price' => Decimal::normalize($totalPrice),
            'profit_amount' => Decimal::normalize($profitAmount),
            'profit_margin' => $profitMargin,
            'components' => $calculatedComponents,
        ];
    }
}
