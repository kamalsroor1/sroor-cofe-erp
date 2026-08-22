<?php

declare(strict_types=1);

namespace App\DTOs\Items;

final class AdjustStockDTO
{
    public function __construct(
        public readonly int $item_id,
        public readonly int $store_id,
        public readonly string $quantity,
        public readonly string $movement_type,
        public readonly ?string $unit_cost = null,
        public readonly ?string $notes = null,
    ) {}

    public static function fromArray(int $itemId, array $data): self
    {
        return new self(
            item_id: $itemId,
            store_id: (int)$data['store_id'],
            quantity: (string)$data['quantity'],
            movement_type: (string)$data['movement_type'],
            unit_cost: isset($data['unit_cost']) && $data['unit_cost'] !== '' ? (string)$data['unit_cost'] : null,
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string)$data['notes'] : null,
        );
    }
}
