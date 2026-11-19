<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\ImportType;
use App\Services\Import\ImportAnalyzer;
use App\Services\Import\ImportHandlerFactory;
use App\Services\Import\ImportRow;
use App\Services\Import\ImportSessionStore;
use App\Support\Csv\CsvCell;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * IMP-01: a downloadable CSV of every row that will NOT be imported (invalid or duplicate), with the
 * sheet row number and the reasons, re-analysed from the stored upload so it is never truncated
 * like the on-screen preview.
 */
final class DownloadImportErrorsAction
{
    public function __construct(
        private readonly ImportAnalyzer $analyzer,
        private readonly ImportHandlerFactory $handlers,
        private readonly ImportSessionStore $sessions,
    ) {}

    public function execute(ImportType $type, string $token, int $userId): StreamedResponse
    {
        $session = $this->sessions->find($token, $type, $userId);
        if ($session === null) {
            throw new HttpException(404, (string) __('import.errors.token_not_found'));
        }

        $analysis = $this->analyzer->analyze($type, $session['absolute_path'], $session['extension']);
        $handler = $this->handlers->make($type);
        $columns = $handler->columns();

        $rows = array_values(array_filter(
            $analysis->rows,
            static fn (ImportRow $row): bool => $row->status !== ImportRow::STATUS_VALID,
        ));

        return response()->streamDownload(function () use ($rows, $columns, $handler): void {
            // BOM: Excel opens UTF-8 Arabic correctly only with it.
            echo "\xEF\xBB\xBF";
            echo CsvCell::row([
                __('import.error_report.row_number'),
                __('import.error_report.status'),
                __('import.error_report.reasons'),
                ...array_map($handler->label(...), $columns),
            ]);

            foreach ($rows as $row) {
                $cells = [
                    $row->rowNumber,
                    __('import.error_report.statuses.'.$row->status),
                    implode(' | ', $row->errors),
                ];
                // The cells as the user typed them (not the normalized values), so each line can be
                // found and fixed in the original sheet.
                foreach ($columns as $column) {
                    $cells[] = $row->raw[$column] ?? '';
                }
                echo CsvCell::row($cells);
            }
        }, $type->value.'-import-errors.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
