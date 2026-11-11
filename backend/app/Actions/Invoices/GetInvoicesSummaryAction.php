<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Support\Money\Decimal;
use Illuminate\Database\Eloquent\Builder;

/**
 * Compute summary aggregates for the filtered invoice query.
 *
 * All financial aggregates are calculated using SQL / bcmath and formatted as
 * scale-3 decimal strings (DECIMAL(12,3)), completely free of float casting.
 */
final class GetInvoicesSummaryAction
{
    /**
     * @param  Builder<Invoice>  $query
     * @return array{total_count: int, total_sales: string, total_paid: string, total_due: string}
     */
    public function execute(Builder $query): array
    {
        $activeQuery = (clone $query)->where('status', '!=', 'cancelled');

        $totalCount = (int) (clone $query)->count();
        $totalSales = Decimal::normalize((string) (clone $activeQuery)->sum('net_total'));
        $totalPaid = Decimal::normalize((string) (clone $activeQuery)->sum('paid_amount'));
        $totalDue = Decimal::sub($totalSales, $totalPaid);

        return [
            'total_count' => $totalCount,
            'total_sales' => $totalSales,
            'total_paid' => $totalPaid,
            'total_due' => $totalDue,
        ];
    }
}
