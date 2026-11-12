<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * PRD-06: one analysed spreadsheet row.
 */
final readonly class ImportRow
{
    public const STATUS_VALID = 'valid';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_DUPLICATE = 'duplicate';

    /**
     * @param  array<string, string|bool|null>  $data  normalized values keyed by column key
     * @param  list<string>  $errors  translated messages
     * @param  list<string>  $warnings  IMP-02: translated non-blocking remarks (the row stays valid and importable)
     */
    public function __construct(
        public int $rowNumber,
        public array $data,
        public string $status,
        public array $errors = [],
        public array $warnings = [],
    ) {}

    /**
     * @return array{row_number: int, data: array<string, string|bool|null>, status: string, errors: list<string>, warnings: list<string>}
     */
    public function toArray(): array
    {
        return [
            'row_number' => $this->rowNumber,
            'data' => $this->data,
            'status' => $this->status,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
