<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Models\Category;
use App\Services\Reports\ReportDateRange;
use App\Services\Reports\SalesReturnAggregates;
use App\Support\Money\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * SHOP-05: sales per item category in the period and store, on the invoice-LINE basis
 * (invoice_items.total_price, before the invoice-level discount), net of the sales returns of the
 * period valued on that same line basis (SalesReturnAggregates::byItemSubquery). Items without a
 * category are one "uncategorized" row. Three statements whatever the volume.
 */
final class GetSalesByCategoryReportAction
{
    public function __construct(
        private readonly SalesReturnAggregates $salesReturns,
    ) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>}
     */
    public function execute(ReportFilterDTO $dto): array
    {
        $lines = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('items', 'items.id', '=', 'invoice_items.item_id')
            ->where('invoices.status', 'confirmed')
            ->whereNull('invoices.deleted_at')
            ->when($dto->store_id !== null, fn ($q) => $q->where('invoices.store_id', $dto->store_id));
        ReportDateRange::apply($lines, 'invoices.invoice_date', $dto->from_date, $dto->to_date);

        $sales = $lines
            ->groupBy('items.category_id')
            ->select('items.category_id')
            ->selectRaw('COUNT(DISTINCT invoices.id) as invoices_count')
            ->selectRaw('COALESCE(SUM(invoice_items.quantity), 0) as quantity')
            ->selectRaw('COALESCE(SUM(invoice_items.total_price), 0) as gross_sales')
            ->get()
            ->keyBy(fn ($row) => (int) $row->category_id);

        $returns = DB::query()
            ->fromSub($this->salesReturns->byItemSubquery($dto->from_date, $dto->to_date, $dto->store_id), 'returned')
            ->join('items', 'items.id', '=', 'returned.returned_item_id')
            ->groupBy('items.category_id')
            ->select('items.category_id')
            ->selectRaw('COALESCE(SUM(returned.returned_qty), 0) as returned_qty')
            ->selectRaw('COALESCE(SUM(returned.returned_value), 0) as returned_value')
            ->get()
            ->keyBy(fn ($row) => (int) $row->category_id);

        $categoryIds = $sales->keys()->merge($returns->keys())->unique()->values();
        $categories = Category::withTrashed()->whereIn('id', $categoryIds->filter())->get(['id', 'name', 'color'])->keyBy('id');

        $rows = [];
        $netTotal = '0.000';
        foreach ($categoryIds as $categoryId) {
            $sale = $sales->get($categoryId);
            $return = $returns->get($categoryId);
            $category = $categoryId === 0 ? null : $categories->get($categoryId);
            $gross = Decimal::normalize((string) ($sale->gross_sales ?? '0'));
            $returnsValue = Decimal::normalize((string) ($return->returned_value ?? '0'));
            $net = Decimal::sub($gross, $returnsValue);
            $netTotal = Decimal::add($netTotal, $net);

            $rows[] = [
                'category_id' => $categoryId === 0 ? null : $categoryId,
                'name' => $category->name ?? __('reports.uncategorized'),
                'color' => $category?->color,
                'invoices_count' => (int) ($sale->invoices_count ?? 0),
                'quantity' => Decimal::normalize((string) ($sale->quantity ?? '0')),
                'gross_sales' => $gross,
                'returns_qty' => Decimal::normalize((string) ($return->returned_qty ?? '0')),
                'returns_value' => $returnsValue,
                'net_sales' => $net,
            ];
        }

        foreach ($rows as &$row) {
            $row['share_percent'] = Decimal::cmp($netTotal, '0') > 0
                ? Decimal::round(bcdiv(bcmul($row['net_sales'], '100', 6), $netTotal, 6), 2)
                : '0.00';
        }
        unset($row);

        usort($rows, fn (array $a, array $b): int => Decimal::cmp($b['net_sales'], $a['net_sales']) ?: strcmp((string) $a['name'], (string) $b['name']));

        return ['rows' => $rows, 'totals' => $this->totals($rows)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totals(array $rows): array
    {
        $keys = ['quantity', 'gross_sales', 'returns_qty', 'returns_value', 'net_sales'];
        $totals = array_fill_keys($keys, '0.000');
        foreach ($rows as $row) {
            foreach ($keys as $key) {
                $totals[$key] = Decimal::add($totals[$key], $row[$key]);
            }
        }
        $totals['share_percent'] = $rows === [] ? '0.00' : '100.00';

        return $totals;
    }
}
