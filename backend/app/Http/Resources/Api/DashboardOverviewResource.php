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
            'success'          => true,
            'customers_count'  => (int)$this->resource['customers_count'],
            'suppliers_count'  => (int)$this->resource['suppliers_count'],
            'total_receivable' => (string)$this->resource['total_receivable'],
            'total_payable'    => (string)$this->resource['total_payable'],
            'today_metrics'    => [
                'net_sales'         => (string)$this->resource['today_metrics']['net_sales'],
                'total_paid'        => (string)$this->resource['today_metrics']['total_paid'],
                'total_cogs'        => (string)$this->resource['today_metrics']['total_cogs'],
                'total_expenses'    => (string)$this->resource['today_metrics']['total_expenses'],
                'net_profit'        => (string)$this->resource['today_metrics']['net_profit'],
                'margin_percentage' => (string)$this->resource['today_metrics']['margin_percentage'],
                'invoices_count'    => (int)$this->resource['today_metrics']['invoices_count'],
            ],
            'current_shift'    => $this->resource['current_shift'] ? new CashShiftResource($this->resource['current_shift']) : null,
            'has_active_shift' => (bool)$this->resource['has_active_shift'],
            'low_stock_count'  => (int)$this->resource['low_stock_count'],
            'recent_invoices'  => InvoiceSummaryResource::collection($this->resource['recent_invoices']),
            'recent_logs'      => ActivityLogResource::collection($this->resource['recent_logs']),
        ];
    }
}
