<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\DTOs\Imports\PreviewImportDTO;
use App\Services\Import\ImportAnalyzer;
use App\Services\Import\ImportRow;
use App\Services\Import\ImportSessionStore;

final class PreviewImportAction
{
    /** Rows returned besides every invalid one. */
    public const PREVIEW_ROW_CAP = 500;

    public function __construct(
        private readonly ImportAnalyzer $analyzer,
        private readonly ImportSessionStore $sessions,
    ) {}

    /**
     * Parse + validate the upload without writing anything, and keep the file for the commit step.
     *
     * @return array{token: string, type: string, summary: array{total: int, valid: int, invalid: int, duplicates: int}, rows: list<array<string, mixed>>, rows_truncated: bool}
     */
    public function execute(PreviewImportDTO $dto): array
    {
        $extension = strtolower($dto->file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';

        // Parse first: a broken file never leaves a stored copy behind.
        $rows = $this->analyzer->analyze($dto->type, (string) $dto->file->getRealPath(), $extension);

        $token = $this->sessions->create($dto->file, $dto->type, $dto->userId);

        $summary = ['total' => count($rows), 'valid' => 0, 'invalid' => 0, 'duplicates' => 0];
        $shown = [];
        foreach ($rows as $index => $row) {
            match ($row->status) {
                ImportRow::STATUS_VALID => $summary['valid']++,
                ImportRow::STATUS_INVALID => $summary['invalid']++,
                default => $summary['duplicates']++,
            };

            if ($index < self::PREVIEW_ROW_CAP || $row->status === ImportRow::STATUS_INVALID) {
                $shown[] = $row->toArray();
            }
        }

        return [
            'token' => $token,
            'type' => $dto->type->value,
            'summary' => $summary,
            'rows' => $shown,
            'rows_truncated' => count($shown) < count($rows),
        ];
    }
}
