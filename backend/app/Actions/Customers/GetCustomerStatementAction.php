<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\DTOs\Customers\CustomerStatementFilterDTO;
use App\Models\Customer;
use App\Services\CustomerBalanceService;
use App\Support\Money\Decimal;

final class GetCustomerStatementAction
{
    public function __construct(
        private readonly CustomerBalanceService $customerBalanceService
    ) {}

    /**
     * Customer statement of account (ledger), one page of the period.
     *
     * PERF-01: summary totals cover the whole period; opening_balance is carried forward from
     * before the period and every balance_after continues it exactly across pages.
     *
     * @return array<string, mixed>
     */
    public function execute(Customer $customer, CustomerStatementFilterDTO $filter): array
    {
        $ledgerData = $this->customerBalanceService->getCustomerLedger(
            $customer,
            $filter->fromDate,
            $filter->toDate,
            $filter->page,
            $filter->perPage,
        );

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'tax_number' => $customer->tax_number,
                'current_balance' => Decimal::normalize((string) $customer->current_balance),
            ],
            'filters' => [
                'from_date' => $filter->fromDate,
                'to_date' => $filter->toDate,
                'all_history' => $filter->allHistory,
            ],
            'summary' => [
                'total_debit' => $ledgerData['total_debit'],
                'total_credit' => $ledgerData['total_credit'],
                'current_balance' => Decimal::normalize((string) $customer->current_balance),
                'transactions_count' => $ledgerData['total'],
                'opening_balance' => $ledgerData['opening_balance'],
                'closing_balance' => $ledgerData['closing_balance'],
            ],
            'ledger' => array_map(fn (array $row): array => [
                'date' => $row['date'],
                'type' => $row['type'],
                'ref_number' => $row['ref_number'],
                'debit' => $row['debit'],
                'credit' => $row['credit'],
                'balance_after' => $row['balance_after'],
                'notes' => $row['notes'],
            ], $ledgerData['entries']),
            'meta' => [
                'current_page' => $filter->page,
                'per_page' => $filter->perPage,
                'total' => $ledgerData['total'],
                'last_page' => max(1, (int) ceil($ledgerData['total'] / $filter->perPage)),
            ],
        ];
    }
}
