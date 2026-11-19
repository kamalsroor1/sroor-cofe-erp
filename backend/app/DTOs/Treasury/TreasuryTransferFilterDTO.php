<?php

declare(strict_types=1);

namespace App\DTOs\Treasury;

/**
 * SHOP-06: filters of the treasury transfers history. storeId null = every branch (verified `all`).
 */
final readonly class TreasuryTransferFilterDTO
{
    public function __construct(
        public ?int $storeId,
        public ?string $fromDate = null,
        public ?string $toDate = null,
        public ?string $method = null,
        public int $perPage = 20,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public static function fromArray(array $data, ?int $storeId): self
    {
        $method = isset($data['method']) && $data['method'] !== 'all' && $data['method'] !== '' ? (string) $data['method'] : null;

        return new self(
            storeId: $storeId,
            fromDate: ! empty($data['from_date']) ? (string) $data['from_date'] : null,
            toDate: ! empty($data['to_date']) ? (string) $data['to_date'] : null,
            method: $method,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 20,
        );
    }
}
