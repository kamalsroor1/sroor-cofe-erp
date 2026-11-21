<?php

declare(strict_types=1);

/*
 * E2E helper: writes a real .xlsx from JSON rows, with the OpenSpout writer the app itself uses for
 * its import templates (DownloadImportTemplateAction). Local test data only.
 *
 *   php e2e/utils/make-xlsx.php <output.xlsx>   < rows.json   (rows.json: list of lists of strings)
 */

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

require dirname(__DIR__, 2).'/backend/vendor/autoload.php';

$target = $argv[1] ?? '';
if ($target === '' || ! str_ends_with($target, '.xlsx')) {
    fwrite(STDERR, "usage: php make-xlsx.php <output.xlsx> < rows.json\n");
    exit(1);
}

$rows = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);

$writer = new Writer;
$writer->openToFile($target);
foreach ($rows as $row) {
    $writer->addRow(Row::fromValues(array_map(static fn ($cell): string => (string) $cell, $row)));
}
$writer->close();
