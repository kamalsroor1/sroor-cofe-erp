<?php

declare(strict_types=1);

namespace App\Actions\StockCounts;

use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StoreStock;

/**
 * SHOP-02: variance report of a session. For an open session the expected quantity is the
 * CURRENT store balance (exactly what posting will compare against); for a posted session it is
 * the balance recorded at posting. Only counted lines with a non-zero difference are listed;
 * the totals count every line. Quantities and values are bcmath strings; without $withCost
 * (`items.view_cost`) the unit cost and every value are null.
 */
final class GetStockCountVarianceAction
{
    /**
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     totals: array<string, int|string|null>
     * }
     */
    public function execute(StockCount $count, bool $withCost = true): array
    {
        $count->loadMissing('lines.item');
        $isPosted = ! $count->isOpen();

        $live = $isPosted ? collect() : StoreStock::query()
            ->where('store_id', $count->store_id)
            ->whereIn('item_id', $count->lines->pluck('item_id'))
            ->pluck('quantity', 'item_id');

        $rows = [];
        $totals = [
            'lines_count' => $count->lines->count(),
            'counted_count' => 0,
            'uncounted_count' => 0,
            'matched_count' => 0,
            'surplus_count' => 0,
            'shortage_count' => 0,
            'surplus_value' => '0.000',
            'shortage_value' => '0.000',
            'net_value' => '0.000',
        ];

        /** @var StockCountLine $line */
        foreach ($count->lines as $line) {
            if ($line->counted_qty === null) {
                $totals['uncounted_count']++;

                continue;
            }

            $totals['counted_count']++;

            $expected = $isPosted
                ? (string) ($line->system_qty_at_post ?? $line->system_qty)
                : bcadd((string) ($live->get($line->item_id) ?? '0'), '0', 3);
            $difference = $isPosted && $line->difference !== null
                ? (string) $line->difference
                : bcsub((string) $line->counted_qty, $expected, 3);
            $value = bcmul($difference, (string) $line->unit_cost, 3);

            $sign = bccomp($difference, '0', 3);
            if ($sign === 0) {
                $totals['matched_count']++;

                continue;
            }

            if ($sign > 0) {
                $totals['surplus_count']++;
                $totals['surplus_value'] = bcadd((string) $totals['surplus_value'], $value, 3);
            } else {
                $totals['shortage_count']++;
                $totals['shortage_value'] = bcadd((string) $totals['shortage_value'], bcmul($value, '-1', 3), 3);
            }
            $totals['net_value'] = bcadd((string) $totals['net_value'], $value, 3);

            $rows[] = [
                'item_id' => (int) $line->item_id,
                'item_name' => $line->item?->name,
                'item_code' => $line->item?->code,
                'unit' => $line->item?->unit,
                'expected_qty' => $expected,
                'counted_qty' => (string) $line->counted_qty,
                'difference' => $difference,
                'unit_cost' => (string) $line->unit_cost,
                'difference_value' => $value,
            ];
        }

        if (! $withCost) {
            $rows = array_map(fn (array $row): array => array_merge($row, ['unit_cost' => null, 'difference_value' => null]), $rows);
            $totals = array_merge($totals, ['surplus_value' => null, 'shortage_value' => null, 'net_value' => null]);
        }

        return ['lines' => $rows, 'totals' => $totals];
    }
}
