<?php

declare(strict_types=1);

namespace App\DTOs\Transfers;

final class CreateTransferDTO
{
    public function __construct(
        public readonly int $from_store_id,
        public readonly int $to_store_id,
        public readonly array $items,
        public readonly string $transfer_date = '',
        public readonly string $status = 'confirmed',
        public readonly ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            from_store_id: (int)$data['from_store_id'],
            to_store_id: (int)$data['to_store_id'],
            items: (array)($data['items'] ?? []),
            transfer_date: (string)($data['transfer_date'] ?? now()->toDateString()),
            status: (string)($data['status'] ?? 'confirmed'),
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string)$data['notes'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'from_store_id' => $this->from_store_id,
            'to_store_id'   => $this->to_store_id,
            'items'         => $this->items,
            'transfer_date' => $this->transfer_date,
            'status'        => $this->status,
            'notes'         => $this->notes,
        ];
    }
}
