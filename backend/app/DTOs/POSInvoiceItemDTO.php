<?php

namespace App\DTOs;

class POSInvoiceItemDTO
{
    public function __construct(
        public readonly int $itemId,
        public readonly string $quantity,
        public readonly string $unitPrice,
        public readonly string $discountAmount = '0.000',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            itemId: (int) $data['item_id'],
            quantity: (string) $data['quantity'],
            unitPrice: (string) $data['unit_price'],
            discountAmount: (string) ($data['discount'] ?? $data['discount_amount'] ?? '0.000'),
        );
    }

    public function toArray(): array
    {
        return [
            'item_id' => $this->itemId,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'discount_amount' => $this->discountAmount,
        ];
    }
}
