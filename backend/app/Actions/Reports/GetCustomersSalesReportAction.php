<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Models\Invoice;
use App\Services\Reports\SalesReturnAggregates;
use App\Support\Money\Decimal;
use Illuminate\Support\Facades\DB;

final class GetCustomersSalesReportAction
{
    public function __construct(
        private readonly SalesReturnAggregates $salesReturns,
    ) {}

    /**
     * Compute Customer purchases, payments, and receivables in period.
     *
     * BUG-01: total_bought is NET of the customer's sales returns dated in the period
     * (gross_bought / total_returns expose the parts) and the ranking uses it. The returns are
     * one pre-grouped row per customer, so the join never multiplies the invoice rows.
     */
    public function execute(ReportFilterDTO $dto): array
    {
        $returns = $this->salesReturns->byCustomerSubquery($dto->from_date, $dto->to_date, $dto->store_id);

        $customerSales = Invoice::where('invoices.status', 'confirmed')
            ->whereDate('invoices.invoice_date', '>=', $dto->from_date)
            ->whereDate('invoices.invoice_date', '<=', $dto->to_date)
            ->when($dto->store_id, fn ($q) => $q->where('invoices.store_id', $dto->store_id))
            ->leftJoinSub($returns, 'customer_returns', 'customer_returns.returned_customer_id', '=', 'invoices.customer_id')
            ->select(
                'invoices.customer_id',
                DB::raw('COUNT(*) as total_invoices'),
                DB::raw('SUM(invoices.net_total) as gross_bought'),
                DB::raw('COALESCE(MAX(customer_returns.returned_value), 0) as total_returns'),
                DB::raw('SUM(invoices.net_total) - COALESCE(MAX(customer_returns.returned_value), 0) as net_bought'),
                DB::raw('SUM(invoices.paid_amount) as total_paid'),
                DB::raw('SUM(invoices.remaining_amount) as total_debt_in_period')
            )
            ->groupBy('invoices.customer_id')
            ->with('customer')
            ->orderByDesc('net_bought')
            ->take(50)
            ->get()
            ->map(fn (Invoice $c) => [
                'customer_id' => $c->customer_id,
                'name' => $c->customer?->name ?? __('contacts.deleted_customer'),
                'phone' => $c->customer?->phone,
                'current_balance' => Decimal::normalize((string) ($c->customer?->current_balance ?? 0)),
                'total_invoices' => (int) $c->total_invoices,
                'total_bought' => Decimal::sub((string) $c->getAttribute('gross_bought'), (string) $c->getAttribute('total_returns')),
                'gross_bought' => Decimal::normalize((string) $c->getAttribute('gross_bought')),
                'total_returns' => Decimal::normalize((string) $c->getAttribute('total_returns')),
                'net_bought' => Decimal::sub((string) $c->getAttribute('gross_bought'), (string) $c->getAttribute('total_returns')),
                'total_paid' => Decimal::normalize((string) $c->total_paid),
                'total_debt_in_period' => Decimal::normalize((string) $c->total_debt_in_period),
            ])
            ->values()
            ->all();

        return $customerSales;
    }
}
