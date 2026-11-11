<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Models\Invoice;
use App\Support\Money\Decimal;
use Illuminate\Support\Facades\DB;

final class GetCustomersSalesReportAction
{
    /**
     * Compute Customer purchases, payments, and receivables in period
     */
    public function execute(ReportFilterDTO $dto): array
    {
        $customerSales = Invoice::where('status', 'confirmed')
            ->whereDate('invoice_date', '>=', $dto->from_date)
            ->whereDate('invoice_date', '<=', $dto->to_date)
            ->when($dto->store_id, fn ($q) => $q->where('store_id', $dto->store_id))
            ->select(
                'customer_id',
                DB::raw('COUNT(*) as total_invoices'),
                DB::raw('SUM(net_total) as total_bought'),
                DB::raw('SUM(paid_amount) as total_paid'),
                DB::raw('SUM(remaining_amount) as total_debt_in_period')
            )
            ->groupBy('customer_id')
            ->with('customer')
            ->orderByDesc('total_bought')
            ->take(50)
            ->get()
            ->map(fn ($c) => [
                'customer_id' => $c->customer_id,
                'name' => $c->customer?->name ?? __('contacts.deleted_customer'),
                'phone' => $c->customer?->phone,
                'current_balance' => Decimal::normalize((string) ($c->customer?->current_balance ?? 0)),
                'total_invoices' => (int) $c->total_invoices,
                'total_bought' => Decimal::normalize((string) $c->total_bought),
                'total_paid' => Decimal::normalize((string) $c->total_paid),
                'total_debt_in_period' => Decimal::normalize((string) $c->total_debt_in_period),
            ])
            ->values()
            ->all();

        return $customerSales;
    }
}
