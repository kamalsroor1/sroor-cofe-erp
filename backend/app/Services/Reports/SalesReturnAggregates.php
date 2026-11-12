<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\ReturnDocument;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * BUG-01: the sales-returns side of every sales / profit report, as grouped SQL aggregates.
 *
 * A sales return reduces the period of its RETURN date (not the invoice's period) by:
 * - value: return_items.total_price, the refund actually booked for the line (already net of
 *   the line discount and the prorated invoice discount, see ReturnSourceService). This is the
 *   basis of every invoice-level figure (totals, byStore, byCustomer, valueByDate), which net
 *   it against invoices.net_total (also after the invoice discount).
 *   The per-item figures (byItem / byItemSubquery) are netted against invoice_items.total_price,
 *   which is BEFORE the invoice discount, so they deduct on that same line basis instead:
 *   returned qty x line total / line qty (see byItemSubquery()).
 * - cost: the returned quantity x the unit cost the sale was costed at, rounded half-up per
 *   line like InvoiceService. The first one found of:
 *   1. the linked invoice line's cost_price (return_items.invoice_item_id, BE-01);
 *   2. legacy return (returns.invoice_id set, no line link): MAX(cost_price) of that invoice's
 *      lines for the same item. Every line of an item in one invoice is costed in the same
 *      transaction at the same weighted average, so MAX is just a deterministic pick;
 *   3. genuinely invoice-less return (DECISION): the unit_cost on the return's own
 *      `sales_return_in` stock movement, i.e. the cost the goods re-entered stock at, fixed on
 *      the return date (there is no sale to take a cost from);
 *   4. only if that movement is missing: the item's current cost_price.
 *
 * Soft-deleted returns (and soft-deleted return lines) are never counted. Purchase returns are
 * never counted: they affect stock and suppliers, not sales.
 *
 * Every method filters `returns.return_date` with a half-open range on the column itself
 * ([from, to + 1 day)), so the date index is usable and it matches both a DATE column (MySQL)
 * and a 'Y-m-d H:i:s' string (sqlite). Results are scale-3 strings (Decimal::normalize).
 *
 * The *Subquery() methods return grouped query builders meant for leftJoinSub(), so an
 * invoice-side aggregate (PERF-01) can net the returns in the same SQL statement.
 *
 * Portability (PERF-01 / ONLY_FULL_GROUP_BY): the per-line cost uses correlated subqueries
 * that read returns.invoice_id and return_items.return_id. They are evaluated in costedLines(),
 * a NON-grouped derived table (one row per return line), and every GROUP BY runs outside it on
 * its plain columns. Putting the correlated subquery inside SUM() of a grouped statement is
 * valid SQL and accepted by MySQL 5.7/8.0/8.4 and MariaDB 11.8.9+, but MariaDB 11.8.8 rejects
 * it with 1055 "'returns.invoice_id' isn't in GROUP BY" (the developer's local server).
 */
final class SalesReturnAggregates
{
    /**
     * Base query over the counted sales-return lines. No select, no grouping.
     */
    public function lines(?string $fromDate, ?string $toDate, ?int $storeId = null): Builder
    {
        $query = DB::table('return_items')
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->leftJoin('invoice_items', 'invoice_items.id', '=', 'return_items.invoice_item_id')
            ->leftJoin('items', 'items.id', '=', 'return_items.item_id')
            ->where('returns.return_type', 'sales_return')
            ->whereNull('returns.deleted_at')
            ->whereNull('return_items.deleted_at');

        if ($fromDate !== null && $fromDate !== '') {
            $query->where('returns.return_date', '>=', CarbonImmutable::parse($fromDate)->toDateString());
        }
        if ($toDate !== null && $toDate !== '') {
            $query->where('returns.return_date', '<', CarbonImmutable::parse($toDate)->addDay()->toDateString());
        }
        if ($storeId !== null) {
            $query->where('returns.store_id', $storeId);
        }

        return $query;
    }

    /**
     * One row per counted return line with its cost already computed (no grouping), for the
     * aggregates to group OUTSIDE it: return_id, store_id, customer_id, item_id,
     * invoice_item_id, quantity, total_price, line_cost, and the linked invoice line's
     * quantity / total (invoice_line_qty, invoice_line_total; NULL when not linked).
     */
    public function costedLines(?string $fromDate, ?string $toDate, ?int $storeId = null): Builder
    {
        return $this->lines($fromDate, $toDate, $storeId)
            ->select([
                'returns.id as return_id',
                'returns.store_id as store_id',
                'returns.customer_id as customer_id',
                'return_items.item_id as item_id',
                'return_items.invoice_item_id as invoice_item_id',
                'return_items.quantity as quantity',
                'return_items.total_price as total_price',
                'invoice_items.quantity as invoice_line_qty',
                'invoice_items.total_price as invoice_line_total',
            ])
            ->selectRaw($this->lineCostSql().' as line_cost', $this->lineCostBindings());
    }

    /**
     * Totals of the period: value, cost, quantity and number of return documents.
     *
     * @return array{value: string, cost: string, quantity: string, count: int}
     */
    public function totals(?string $fromDate, ?string $toDate, ?int $storeId = null): array
    {
        $row = DB::query()
            ->fromSub($this->costedLines($fromDate, $toDate, $storeId), 'costed')
            ->selectRaw('COUNT(DISTINCT costed.return_id) as returns_count')
            ->selectRaw('COALESCE(SUM(costed.quantity), 0) as returned_qty')
            ->selectRaw('COALESCE(SUM(costed.total_price), 0) as returned_value')
            ->selectRaw('COALESCE(SUM(costed.line_cost), 0) as returned_cost')
            ->first();

        // An aggregate without GROUP BY always returns exactly one row.
        return [
            'value' => Decimal::normalize($this->scalar($row->returned_value)),
            'cost' => Decimal::normalize($this->scalar($row->returned_cost)),
            'quantity' => Decimal::normalize($this->scalar($row->returned_qty)),
            'count' => (int) $row->returns_count,
        ];
    }

    /**
     * Per store (every store; the caller decides which rows it shows).
     *
     * @return array<int, array{value: string, cost: string, count: int}>
     */
    public function byStore(?string $fromDate, ?string $toDate): array
    {
        $rows = DB::query()
            ->fromSub($this->costedLines($fromDate, $toDate)->whereNotNull('returns.store_id'), 'costed')
            ->groupBy('costed.store_id')
            ->select('costed.store_id')
            ->selectRaw('COUNT(DISTINCT costed.return_id) as returns_count')
            ->selectRaw('COALESCE(SUM(costed.total_price), 0) as returned_value')
            ->selectRaw('COALESCE(SUM(costed.line_cost), 0) as returned_cost')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->store_id] = [
                'value' => Decimal::normalize($this->scalar($row->returned_value)),
                'cost' => Decimal::normalize($this->scalar($row->returned_cost)),
                'count' => (int) $row->returns_count,
            ];
        }

        return $out;
    }

    /**
     * Per item: returned quantity, value and cost.
     *
     * @return array<int, array{quantity: string, value: string, cost: string}>
     */
    public function byItem(?string $fromDate, ?string $toDate, ?int $storeId = null): array
    {
        $out = [];
        foreach ($this->byItemSubquery($fromDate, $toDate, $storeId)->get() as $row) {
            $out[(int) $row->returned_item_id] = [
                'quantity' => Decimal::normalize($this->scalar($row->returned_qty)),
                'value' => Decimal::normalize($this->scalar($row->returned_value)),
                'cost' => Decimal::normalize($this->scalar($row->returned_cost)),
            ];
        }

        return $out;
    }

    /**
     * Per return day ('Y-m-d' => value), for daily trends.
     *
     * @return array<string, string>
     */
    public function valueByDate(?string $fromDate, ?string $toDate, ?int $storeId = null): array
    {
        $rows = $this->lines($fromDate, $toDate, $storeId)
            ->groupBy('returns.return_date')
            ->select('returns.return_date')
            ->selectRaw('COALESCE(SUM(return_items.total_price), 0) as returned_value')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $day = substr((string) $row->return_date, 0, 10);
            $out[$day] = Decimal::add($out[$day] ?? '0.000', $this->scalar($row->returned_value));
        }

        return $out;
    }

    /**
     * Grouped per item, for leftJoinSub(): returned_item_id, returned_qty, returned_value,
     * returned_cost.
     *
     * returned_value is on the invoice-LINE basis (before the invoice discount), because item
     * reports net it against SUM(invoice_items.total_price). The lines are first grouped per
     * returned invoice line, then valued as SUM(returned qty) x line total / line qty, rounded
     * half-up to 3 decimals; so returning the whole line (in one or several returns of the
     * period) takes back exactly its line total. A return line with no linked invoice line
     * (invoice-less or legacy) has no line basis and deducts its refunded value.
     * On MySQL the division is exact DECIMAL arithmetic; sqlite (tests only) divides in floats.
     */
    public function byItemSubquery(?string $fromDate, ?string $toDate, ?int $storeId = null): Builder
    {
        $perLine = DB::query()
            ->fromSub($this->costedLines($fromDate, $toDate, $storeId), 'costed')
            ->groupBy('costed.item_id', 'costed.invoice_item_id')
            ->select('costed.item_id as line_item_id')
            ->selectRaw('SUM(costed.quantity) as line_returned_qty')
            ->selectRaw(
                'CASE WHEN costed.invoice_item_id IS NULL'
                .' OR COALESCE(MAX(costed.invoice_line_qty), 0) = 0'
                .' THEN SUM(costed.total_price)'
                .' ELSE ROUND(SUM(costed.quantity) * MAX(costed.invoice_line_total) / MAX(costed.invoice_line_qty), 3)'
                .' END as line_returned_value'
            )
            ->selectRaw('SUM(costed.line_cost) as line_returned_cost');

        return DB::query()
            ->fromSub($perLine, 'returned_lines')
            ->groupBy('returned_lines.line_item_id')
            ->select('returned_lines.line_item_id as returned_item_id')
            ->selectRaw('COALESCE(SUM(returned_lines.line_returned_qty), 0) as returned_qty')
            ->selectRaw('COALESCE(SUM(returned_lines.line_returned_value), 0) as returned_value')
            ->selectRaw('COALESCE(SUM(returned_lines.line_returned_cost), 0) as returned_cost');
    }

    /**
     * Grouped per customer, for leftJoinSub(): returned_customer_id, returned_value,
     * returns_count.
     */
    public function byCustomerSubquery(?string $fromDate, ?string $toDate, ?int $storeId = null): Builder
    {
        return $this->lines($fromDate, $toDate, $storeId)
            ->whereNotNull('returns.customer_id')
            ->groupBy('returns.customer_id')
            ->select('returns.customer_id as returned_customer_id')
            ->selectRaw('COUNT(DISTINCT returns.id) as returns_count')
            ->selectRaw('COALESCE(SUM(return_items.total_price), 0) as returned_value');
    }

    /**
     * Cost of one return line: quantity x the cost the sale was costed at, half-up at scale 3
     * per line. Fallback order documented on the class. Static SQL; the only binding is the
     * return document's morph class.
     */
    private function lineCostSql(): string
    {
        return 'ROUND(return_items.quantity * COALESCE('
            // 1. the linked invoice line
            .'invoice_items.cost_price, '
            // 2. legacy return: the same item on the return's invoice
            .'(SELECT MAX(legacy_lines.cost_price) FROM invoice_items legacy_lines'
            .' WHERE legacy_lines.invoice_id = returns.invoice_id'
            .' AND legacy_lines.item_id = return_items.item_id), '
            // 3. invoice-less return: the cost the goods re-entered stock at
            .'(SELECT MAX(stock_movements.unit_cost) FROM stock_movements'
            .' WHERE stock_movements.source_type = ?'
            .' AND stock_movements.source_id = return_items.return_id'
            .' AND stock_movements.item_id = return_items.item_id'
            ." AND stock_movements.movement_type = 'sales_return_in'), "
            // 4. last resort: the item's current cost
            .'items.cost_price, 0), 3)';
    }

    /**
     * @return list<string>
     */
    private function lineCostBindings(): array
    {
        return [(new ReturnDocument)->getMorphClass()];
    }

    private function scalar(mixed $value): string
    {
        return $value === null ? '0' : (string) $value;
    }
}
