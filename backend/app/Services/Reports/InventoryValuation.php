<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Support\Money\Decimal;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PERF-01: stock valuation (at cost and at selling price) of the active items, shared by the
 * P&L summary and the inventory valuation report.
 *
 * - One branch: store_stocks.quantity x items.cost_price, selling at the branch's
 *   custom_selling_price when set, else items.selling_price.
 * - Every branch (no store): items.current_stock x items.cost_price / selling_price.
 *
 * Each line value is quantity x price rounded half-up to 3 decimals (Decimal::mul, the money
 * rule), and the totals are the exact sum of those line values over EVERY row, streamed from a
 * narrow cursor (3 columns, no models), so they never depend on the page that is shown.
 * The list itself is paged in SQL.
 */
final class InventoryValuation
{
    /**
     * @return array{cost: string, selling: string, profit: string, count: int}
     */
    public function totals(?int $storeId): array
    {
        $cost = '0.000';
        $selling = '0.000';
        $count = 0;

        foreach ($this->base($storeId)->select($this->valueColumns($storeId))->orderBy('id_key')->cursor() as $row) {
            $qty = Decimal::normalize(InvoiceAggregates::scalar($row->qty));
            $cost = bcadd($cost, Decimal::mul($qty, Decimal::normalize(InvoiceAggregates::scalar($row->unit_cost))), 3);
            $selling = bcadd($selling, Decimal::mul($qty, Decimal::normalize(InvoiceAggregates::scalar($row->unit_price))), 3);
            $count++;
        }

        return [
            'cost' => $cost,
            'selling' => $selling,
            'profit' => bcsub($selling, $cost, 3),
            'count' => $count,
        ];
    }

    /**
     * One page of valued rows, the most valuable stock (at cost) first.
     *
     * @param  string  $stockFilter  all | in_stock (quantity > 0) | zero_stock (quantity <= 0)
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function page(?int $storeId, string $stockFilter, int $page, int $perPage): array
    {
        $qtyColumn = $storeId !== null ? 'store_stocks.quantity' : 'items.current_stock';

        $query = $this->base($storeId);
        if ($stockFilter === 'in_stock') {
            $query->where($qtyColumn, '>', 0);
        } elseif ($stockFilter === 'zero_stock') {
            $query->where(fn (Builder $q) => $q->where($qtyColumn, '<=', 0)->orWhereNull($qtyColumn));
        }

        $total = (clone $query)->count();

        $rows = $query
            ->select(array_merge($this->valueColumns($storeId), [
                'items.id', 'items.name', 'items.code', 'items.category', 'items.unit',
            ]))
            ->orderByRaw('(COALESCE('.$qtyColumn.', 0) * COALESCE(items.cost_price, 0)) DESC')
            ->orderBy('items.id')
            ->forPage($page, $perPage)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $qty = Decimal::normalize(InvoiceAggregates::scalar($row->qty));
            $costPrice = Decimal::normalize(InvoiceAggregates::scalar($row->unit_cost));
            $sellingPrice = Decimal::normalize(InvoiceAggregates::scalar($row->unit_price));
            $costVal = Decimal::mul($qty, $costPrice);
            $sellVal = Decimal::mul($qty, $sellingPrice);

            $out[] = [
                'id' => (int) $row->id,
                'name' => $row->name,
                'code' => $row->code,
                'category' => $row->category,
                'unit' => $row->unit,
                'current_stock' => $qty,
                'cost_price' => $costPrice,
                'selling_price' => $sellingPrice,
                'cost_val' => $costVal,
                'sell_val' => $sellVal,
                'profit' => bcsub($sellVal, $costVal, 3),
            ];
        }

        return ['rows' => $out, 'total' => $total];
    }

    private function base(?int $storeId): Builder
    {
        if ($storeId !== null) {
            return DB::table('store_stocks')
                ->join('items', 'items.id', '=', 'store_stocks.item_id')
                ->where('store_stocks.store_id', $storeId)
                ->whereNull('store_stocks.deleted_at')
                ->where('items.is_active', true)
                ->whereNull('items.deleted_at');
        }

        return DB::table('items')
            ->where('items.is_active', true)
            ->whereNull('items.deleted_at');
    }

    /**
     * @return list<Expression|string>
     */
    private function valueColumns(?int $storeId): array
    {
        if ($storeId !== null) {
            return [
                'store_stocks.id as id_key',
                'store_stocks.quantity as qty',
                'items.cost_price as unit_cost',
                DB::raw('COALESCE(store_stocks.custom_selling_price, items.selling_price) as unit_price'),
            ];
        }

        return [
            'items.id as id_key',
            'items.current_stock as qty',
            'items.cost_price as unit_cost',
            'items.selling_price as unit_price',
        ];
    }
}
