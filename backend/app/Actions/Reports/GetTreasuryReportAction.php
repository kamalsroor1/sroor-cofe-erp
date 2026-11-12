<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Services\TreasuryService;

final class GetTreasuryReportAction
{
    public function __construct(
        private readonly TreasuryService $treasuryService
    ) {}

    /**
     * Compute Treasury inflows, outflows, and net cash flow.
     *
     * PERF-01: the ledger is paged (page $page of $perPage entries, newest first; totals and the
     * running balances stay those of the whole period); a null $perPage returns every entry.
     */
    public function execute(ReportFilterDTO $dto, ?int $perPage = null, int $page = 1): array
    {
        return $this->treasuryService->getTreasuryReport(
            fromDate: $dto->from_date,
            toDate: $dto->to_date,
            storeId: $dto->store_id,
            ledgerPerPage: $perPage,
            ledgerPage: $page,
        );
    }
}
