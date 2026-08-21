<?php

declare(strict_types=1);

namespace App\DTOs\Customers;

final class CustomerDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly ?string $tax_number = null,
        public readonly string $opening_balance = '0.000',
        public readonly ?string $notes = null,
        public readonly bool $is_active = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string)$data['name'],
            phone: isset($data['phone']) && $data['phone'] !== '' ? (string)$data['phone'] : null,
            address: isset($data['address']) && $data['address'] !== '' ? (string)$data['address'] : null,
            tax_number: isset($data['tax_number']) && $data['tax_number'] !== '' ? (string)$data['tax_number'] : null,
            opening_balance: isset($data['opening_balance']) && is_numeric($data['opening_balance']) ? (string)$data['opening_balance'] : '0.000',
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string)$data['notes'] : null,
            is_active: !isset($data['is_active']) || (bool)$data['is_active'],
        );
    }

    public function toArray(): array
    {
        return [
            'name'            => $this->name,
            'phone'           => $this->phone,
            'address'         => $this->address,
            'tax_number'      => $this->tax_number,
            'opening_balance' => $this->opening_balance,
            'notes'           => $this->notes,
            'is_active'       => $this->is_active,
        ];
    }
}
