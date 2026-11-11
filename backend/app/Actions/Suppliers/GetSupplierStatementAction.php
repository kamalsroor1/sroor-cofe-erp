<?php

declare(strict_types=1);

namespace App\Actions\Suppliers;

use App\Models\Supplier;
use App\Services\SupplierBalanceService;
use App\Support\Money\Decimal;

final class GetSupplierStatementAction
{
    public function __construct(
        private readonly SupplierBalanceService $supplierBalanceService
    ) {}

    /**
     * Generate supplier statement of account (Ledger)
     */
    public function execute(Supplier $supplier, ?string $fromDate = null, ?string $toDate = null): array
    {
        $ledgerData = $this->supplierBalanceService->getSupplierLedger($supplier, $fromDate, $toDate);

        $totalCredit = '0.000'; // مستحقات المورد (مشتريات)
        $totalDebit = '0.000';  // مدفوعات للمورد (سدادات)

        foreach ($ledgerData['ledger'] as $entry) {
            $totalCredit = bcadd($totalCredit, (string) ($entry['credit'] ?? '0'), 3);
            $totalDebit = bcadd($totalDebit, (string) ($entry['debit'] ?? '0'), 3);
        }

        return [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'company_name' => $supplier->company_name,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'current_balance' => Decimal::normalize((string) $supplier->current_balance),
            ],
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'summary' => [
                'total_purchases' => Decimal::normalize((string) $totalCredit),
                'total_paid' => Decimal::normalize((string) $totalDebit),
                'current_balance' => Decimal::normalize((string) $supplier->current_balance),
                'transactions_count' => count($ledgerData['ledger']),
            ],
            'ledger' => array_map(fn ($row) => [
                'date' => $row['date'],
                'type' => $row['type'],
                'ref_number' => $row['ref_number'],
                'debit' => Decimal::normalize((string) $row['debit']),
                'credit' => Decimal::normalize((string) $row['credit']),
                'balance_after' => Decimal::normalize((string) $row['balance_after']),
                'notes' => $row['notes'],
            ], $ledgerData['ledger']),
        ];
    }
}
