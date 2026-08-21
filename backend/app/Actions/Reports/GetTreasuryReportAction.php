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
     * Compute Treasury inflows, outflows, and net cash flow
     */
    public function execute(ReportFilterDTO $dto): array
    {
        return $this->treasuryService->getTreasuryReport(
            fromDate: $dto->from_date,
            toDate: $dto->to_date,
            storeId: $dto->store_id,
        );
    }
}
