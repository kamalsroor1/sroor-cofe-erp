<?php

declare(strict_types=1);

namespace App\DTOs\Shifts;

final class OpenShiftDTO
{
    public function __construct(
        public readonly string $opening_cash_balance,
        public readonly ?string $notes = null,
        public readonly ?int $store_id = null,
    ) {}

    public static function fromArray(array $data, ?int $storeId = null): self
    {
        return new self(
            opening_cash_balance: (string)($data['opening_cash_balance'] ?? '0.000'),
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string)$data['notes'] : null,
            store_id: isset($data['store_id']) ? (int)$data['store_id'] : $storeId,
        );
    }
}
