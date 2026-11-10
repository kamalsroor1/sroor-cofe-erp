<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\ImportType;
use Illuminate\Validation\ValidationException;

/**
 * PRD-06: parse + validate a spreadsheet WITHOUT writing. Used by preview and, again, by commit.
 */
final class ImportAnalyzer
{
    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly ImportHandlerFactory $handlers,
    ) {}

    /**
     * @return list<ImportRow>
     *
     * @throws ValidationException
     */
    public function analyze(ImportType $type, string $absolutePath, string $extension): array
    {
        $sheet = $this->reader->read($absolutePath, $extension);

        return $this->handlers->make($type)->analyze($sheet['headers'], $sheet['rows']);
    }
}
