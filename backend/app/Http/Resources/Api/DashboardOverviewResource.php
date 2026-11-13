<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Http\Resources\InvoiceSummaryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'customers_count' => (int) $this->resource['customers_count'],
            'suppliers_count' => (int) $this->resource['suppliers_count'],
            'total_receivable' => (string) $this->resource['total_receivable'],
            'total_payable' => (string) $this->resource['total_payable'],
            'today_metrics' => $this->todayMetrics($this->resource['today_metrics']),
            'current_shift' => $this->resource['current_shift'] ? new CashShiftResource($this->resource['current_shift']) : null,
            'has_active_shift' => (bool) $this->resource['has_active_shift'],
            'low_stock_count' => (int) $this->resource['low_stock_count'],
            'recent_invoices' => InvoiceSummaryResource::collection($this->resource['recent_invoices']),
            'recent_logs' => ActivityLogResource::collection($this->resource['recent_logs']),
        ];
    }

    /**
     * BILL-03 review: the profit keys (total_cogs, net_profit, margin_percentage) are only in the
     * payload when the shop has `reports.advanced` (GetDashboardApiOverviewAction leaves them out);
     * they stay out here instead of failing on a missing key.
     *
     * @param  array<string, mixed>  $metrics
     * @return array<string, int|string>
     */
    private function todayMetrics(array $metrics): array
    {
        $out = [
            'net_sales' => (string) $metrics['net_sales'],
            'total_paid' => (string) $metrics['total_paid'],
            'total_expenses' => (string) $metrics['total_expenses'],
            'invoices_count' => (int) $metrics['invoices_count'],
        ];

        foreach (['total_cogs', 'net_profit', 'margin_percentage'] as $key) {
            if (array_key_exists($key, $metrics)) {
                $out[$key] = (string) $metrics[$key];
            }
        }

        return $out;
    }
}
