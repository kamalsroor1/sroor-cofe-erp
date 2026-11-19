<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Services\Reports\BalanceAging;
use App\Support\TenantClock;

/**
 * SHOP-05: customer debt aging (0-30 / 31-60 / 61-90 / 90+ days) as of today. The open balance
 * is the customer's ledger balance, settled oldest-invoice-first (see BalanceAging). The period
 * filter does not apply (it is a snapshot); the store filter does.
 */
final class GetCustomerAgingReportAction
{
    public function __construct(
        private readonly BalanceAging $aging,
        private readonly TenantClock $clock,
    ) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>, total: int, as_of: string}
     */
    public function execute(ReportFilterDTO $dto, bool $paginate = true): array
    {
        $asOf = $this->clock->businessDate();
        $report = $this->aging->report(
            ['party_table' => 'customers', 'doc_table' => 'invoices', 'party_fk' => 'customer_id', 'date_column' => 'invoice_date'],
            $asOf,
            $dto->store_id,
            $paginate ? $dto->page : null,
            $dto->per_page,
        );

        return $report + ['as_of' => $asOf];
    }
}
