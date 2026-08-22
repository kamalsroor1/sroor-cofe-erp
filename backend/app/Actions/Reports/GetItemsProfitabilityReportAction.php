<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;

final class GetItemsProfitabilityReportAction
{
    /**
     * Compute Item-level sales, revenues, COGS, gross profits, and profit margins
     */
    public function execute(ReportFilterDTO $dto): array
    {
        $itemProfits = InvoiceItem::whereHas('invoice', function ($q) use ($dto) {
                $q->where('status', 'confirmed')
                  ->whereDate('invoice_date', '>=', $dto->from_date)
                  ->whereDate('invoice_date', '<=', $dto->to_date)
                  ->when($dto->store_id, fn($sub) => $sub->where('store_id', $dto->store_id));
            })
            ->select(
                'item_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(total_price) as total_revenue'),
                DB::raw('SUM(quantity * cost_price) as total_cogs')
            )
            ->groupBy('item_id')
            ->with('item')
            ->get()
            ->map(function ($row) {
                $profit = bcsub((string)$row->total_revenue, (string)$row->total_cogs, 3);
                $margin = '0.0';
                if (bccomp((string)$row->total_revenue, '0.000', 3) > 0) {
                    $margin = bcmul(bcdiv($profit, (string)$row->total_revenue, 4), '100', 1);
                }
                return [
                    'item_id'       => $row->item_id,
                    'name'          => $row->item?->name ?? 'صنف محذوف',
                    'code'          => $row->item?->code,
                    'category'      => $row->item?->category,
                    'unit'          => $row->item?->unit,
                    'total_qty'     => (float)$row->total_qty,
                    'total_revenue' => (float)$row->total_revenue,
                    'total_cogs'    => (float)$row->total_cogs,
                    'profit'        => (float)$profit,
                    'margin'        => (float)$margin,
                ];
            })
            ->sortByDesc('total_revenue')
            ->values()
            ->all();

        return $itemProfits;
    }
}
