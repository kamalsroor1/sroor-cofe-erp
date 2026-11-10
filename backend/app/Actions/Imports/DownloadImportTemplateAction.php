<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\ImportType;
use App\Services\Import\ImportHandlerFactory;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DownloadImportTemplateAction
{
    public function __construct(private readonly ImportHandlerFactory $handlers) {}

    /**
     * An .xlsx with the translated header row and one example row.
     */
    public function execute(ImportType $type): BinaryFileResponse
    {
        $template = $this->handlers->make($type)->template();

        $path = tempnam(sys_get_temp_dir(), 'imp');
        if ($path === false) {
            abort(500, (string) __('import.errors.template_failed'));
        }

        $built = false;
        try {
            $writer = new Writer;
            $writer->openToFile($path);
            $writer->addRow(Row::fromValues($template['headers']));
            $writer->addRow(Row::fromValues($template['example']));
            $writer->close();
            $built = true;
        } finally {
            // A failed build must not leave the temp file behind (a sent file is deleted after send).
            if (! $built) {
                @unlink($path);
            }
        }

        return response()
            ->download($path, $type->value.'-import-template.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }
}
