<?php

declare(strict_types=1);

namespace App\Actions\DataExport;

use App\Models\DataExport;

/** PRD-17: the CURRENT shop's latest export (or null), for the Settings card polling. */
final class GetLatestDataExportAction
{
    public function execute(): ?DataExport
    {
        return DataExport::query()->orderByDesc('id')->first();
    }
}
