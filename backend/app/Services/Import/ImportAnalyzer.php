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
     * @throws ValidationException
     */
    public function analyze(ImportType $type, string $absolutePath, string $extension): ImportAnalysis
    {
        $handler = $this->handlers->make($type);
        $sheet = $this->reader->read($absolutePath, $extension, $handler->headerScore(...));

        return $handler->analyze($sheet['headers'], $sheet['rows'], $sheet['header_row']);
    }
}
