<?php

declare(strict_types=1);

namespace App\DTOs\Suppliers;

final class PaySupplierDTO
{
    public function __construct(
        public readonly int $supplier_id,
        public readonly string $amount,
        public readonly string $payment_method,
        public readonly string $payment_date,
        public readonly ?string $notes = null,
    ) {}

    public static function fromArray(int $supplierId, array $data): self
    {
        return new self(
            supplier_id: $supplierId,
            amount: (string)$data['amount'],
            payment_method: (string)$data['payment_method'],
            payment_date: (string)$data['payment_date'],
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string)$data['notes'] : null,
        );
    }
}
