<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Services\CustomerBalanceService;

final class GetCustomerStatementAction
{
    public function __construct(
        private readonly CustomerBalanceService $customerBalanceService
    ) {}

    /**
     * Generate customer statement of account (Ledger)
     */
    public function execute(Customer $customer, ?string $fromDate = null, ?string $toDate = null): array
    {
        $ledgerData = $this->customerBalanceService->getCustomerLedger($customer, $fromDate, $toDate);

        $totalDebit = '0.000';
        $totalCredit = '0.000';

        foreach ($ledgerData['entries'] as $entry) {
            $totalDebit = bcadd($totalDebit, (string)$entry['debit'], 3);
            $totalCredit = bcadd($totalCredit, (string)$entry['credit'], 3);
        }

        return [
            'customer' => [
                'id'              => $customer->id,
                'name'            => $customer->name,
                'phone'           => $customer->phone,
                'address'         => $customer->address,
                'tax_number'      => $customer->tax_number,
                'current_balance' => (float)$customer->current_balance,
            ],
            'filters' => [
                'from_date' => $fromDate,
                'to_date'   => $toDate,
            ],
            'summary' => [
                'total_debit'        => (float)$totalDebit,
                'total_credit'       => (float)$totalCredit,
                'current_balance'    => (float)$customer->current_balance,
                'transactions_count' => count($ledgerData['entries']),
            ],
            'ledger' => array_map(fn($row) => [
                'date'          => $row['date'],
                'type'          => $row['type'],
                'ref_number'    => $row['ref_number'],
                'debit'         => (float)$row['debit'],
                'credit'        => (float)$row['credit'],
                'balance_after' => (float)$row['balance_after'],
                'notes'         => $row['notes'],
            ], $ledgerData['entries']),
        ];
    }
}
