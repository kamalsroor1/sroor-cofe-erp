<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Services\Reports\SalesReturnAggregates;
use App\Support\Money\Decimal;
use Illuminate\Support\Facades\DB;

final class GetItemsProfitabilityReportAction
{
    public function __construct(
        private readonly SalesReturnAggregates $salesReturns,
    ) {}

    /**
     * Compute Item-level sales, revenues, COGS, gross profits, and profit margins.
     *
     * BUG-01: quantities and values are NET of the period's sales returns (by return date):
     * total_qty / total_revenue / total_cogs / profit / margin are net; gross_* and returned_*
     * expose the two parts. An item returned in the period but sold earlier still gets a row.
     */
    public function execute(ReportFilterDTO $dto): array
    {
        $sold = InvoiceItem::whereHas('invoice', function ($q) use ($dto) {
            $q->where('status', 'confirmed')
                ->whereDate('invoice_date', '>=', $dto->from_date)
                ->whereDate('invoice_date', '<=', $dto->to_date)
                ->when($dto->store_id, fn ($sub) => $sub->where('store_id', $dto->store_id));
        })
            ->select(
                'item_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(total_price) as total_revenue'),
                DB::raw('SUM(quantity * cost_price) as total_cogs')
            )
            ->groupBy('item_id')
            ->with('item')
            ->get();

        /** @var array<int, InvoiceItem> $soldRows */
        $soldRows = [];
        foreach ($sold as $row) {
            $soldRows[(int) $row->item_id] = $row;
        }

        $returned = $this->salesReturns->byItem($dto->from_date, $dto->to_date, $dto->store_id);

        $returnOnlyIds = array_values(array_diff(array_keys($returned), array_keys($soldRows)));
        $returnOnlyItems = $returnOnlyIds === []
            ? collect()
            : Item::withTrashed()->whereIn('id', $returnOnlyIds)->get()->keyBy('id');

        $zero = ['quantity' => '0.000', 'value' => '0.000', 'cost' => '0.000'];
        $rows = [];

        foreach (array_unique(array_merge(array_keys($soldRows), array_keys($returned))) as $itemId) {
            $soldRow = $soldRows[$itemId] ?? null;
            $item = $soldRow !== null ? $soldRow->item : $returnOnlyItems->get($itemId);
            $ret = $returned[$itemId] ?? $zero;

            $grossQty = Decimal::normalize((string) $soldRow?->getAttribute('total_qty'));
            $grossRevenue = Decimal::normalize((string) $soldRow?->getAttribute('total_revenue'));
            $grossCogs = Decimal::normalize((string) $soldRow?->getAttribute('total_cogs'));

            $netQty = bcsub($grossQty, $ret['quantity'], 3);
            $netRevenue = bcsub($grossRevenue, $ret['value'], 3);
            $netCogs = bcsub($grossCogs, $ret['cost'], 3);
            $profit = bcsub($netRevenue, $netCogs, 3);

            $margin = '0.0';
            if (bccomp($netRevenue, '0.000', 3) > 0) {
                $margin = bcmul(bcdiv($profit, $netRevenue, 4), '100', 1);
            }

            $rows[] = [
                'item_id' => $itemId,
                'name' => $item?->name ?? __('inventory.deleted_item'),
                'code' => $item?->code,
                'category' => $item?->category,
                'unit' => $item?->unit,
                'total_qty' => $netQty,
                'total_revenue' => $netRevenue,
                'total_cogs' => $netCogs,
                'profit' => $profit,
                'margin' => Decimal::normalize($margin, 1),
                'gross_qty' => $grossQty,
                'gross_revenue' => $grossRevenue,
                'gross_cogs' => $grossCogs,
                'returned_qty' => $ret['quantity'],
                'returned_value' => $ret['value'],
                'returned_cogs' => $ret['cost'],
            ];
        }

        usort($rows, fn (array $a, array $b): int => bccomp($b['total_revenue'], $a['total_revenue'], 3));

        return $rows;
    }
}
