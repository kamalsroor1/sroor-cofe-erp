<?php

namespace App\DTOs;

class POSInvoiceItemDTO
{
    public function __construct(
        public readonly int $itemId,
        public readonly string $quantity,
        /** null = not sent: the server prices the line at the customer's tier list price (BE-08). */
        public readonly ?string $unitPrice,
        public readonly string $discountAmount = '0.000',
        /** POS-07: percent of the line total; wins over $discountAmount when > 0. */
        public readonly ?string $discountPercent = null,
        public readonly ?int $entrySequence = null,
    ) {}

    public static function fromArray(array $data, ?int $defaultSequence = null): self
    {
        return new self(
            itemId: (int) $data['item_id'],
            quantity: (string) $data['quantity'],
            unitPrice: isset($data['unit_price']) && $data['unit_price'] !== '' ? (string) $data['unit_price'] : null,
            discountAmount: (string) ($data['discount'] ?? $data['discount_amount'] ?? '0.000'),
            discountPercent: isset($data['discount_percent']) && $data['discount_percent'] !== '' ? (string) $data['discount_percent'] : null,
            entrySequence: isset($data['entry_sequence']) && $data['entry_sequence'] !== '' ? (int) $data['entry_sequence'] : $defaultSequence,
        );
    }

    public function toArray(): array
    {
        return [
            'item_id' => $this->itemId,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'discount_amount' => $this->discountAmount,
            'discount_percent' => $this->discountPercent,
            'entry_sequence' => $this->entrySequence,
        ];
    }
}
