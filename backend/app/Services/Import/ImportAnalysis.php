<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * IMP-01: everything one analysis of a spreadsheet found, without writing anything.
 */
final readonly class ImportAnalysis
{
    /**
     * @param  list<ImportRow>  $rows
     * @param  int  $headerRow  sheet line of the detected header (titles above it are skipped)
     * @param  list<string>  $ignoredColumns  header cells that match no column (their data is not imported)
     * @param  list<string>  $newCategories  categories the commit would create (items only)
     */
    public function __construct(
        public array $rows,
        public int $headerRow = 1,
        public array $ignoredColumns = [],
        public array $newCategories = [],
    ) {}

    /**
     * @return list<ImportRow>
     */
    public function withStatus(string $status): array
    {
        return array_values(array_filter($this->rows, static fn (ImportRow $row): bool => $row->status === $status));
    }
}
