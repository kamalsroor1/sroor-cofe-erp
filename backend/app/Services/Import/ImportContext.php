<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * PRD-06: who runs the import and where opening stock lands.
 */
final readonly class ImportContext
{
    public function __construct(
        public int $userId,
        public ?int $storeId,
        public string $businessDate,
    ) {}
}
