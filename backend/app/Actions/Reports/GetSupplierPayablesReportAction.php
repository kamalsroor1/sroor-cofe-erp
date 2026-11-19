<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Services\Reports\BalanceAging;
use App\Support\TenantClock;

/**
 * SHOP-05: supplier payables aged (0-30 / 31-60 / 61-90 / 90+ days) as of today. The open balance
 * is the supplier's ledger balance (what we owe), settled oldest-purchase-first (see BalanceAging). The period
 * filter does not apply (it is a snapshot); the store filter does.
 */
final class GetSupplierPayablesReportAction
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
            ['party_table' => 'suppliers', 'doc_table' => 'purchases', 'party_fk' => 'supplier_id', 'date_column' => 'purchase_date'],
            $asOf,
            $dto->store_id,
            $paginate ? $dto->page : null,
            $dto->per_page,
        );

        return $report + ['as_of' => $asOf];
    }
}
