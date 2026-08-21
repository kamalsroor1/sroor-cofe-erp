<?php

declare(strict_types=1);

namespace App\DTOs\Suppliers;

final class SupplierDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $company_name = null,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly string $opening_balance = '0.000',
        public readonly ?string $notes = null,
        public readonly bool $is_active = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string)$data['name'],
            company_name: isset($data['company_name']) && $data['company_name'] !== '' ? (string)$data['company_name'] : null,
            phone: isset($data['phone']) && $data['phone'] !== '' ? (string)$data['phone'] : null,
            address: isset($data['address']) && $data['address'] !== '' ? (string)$data['address'] : null,
            opening_balance: isset($data['opening_balance']) && is_numeric($data['opening_balance']) ? (string)$data['opening_balance'] : '0.000',
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string)$data['notes'] : null,
            is_active: !isset($data['is_active']) || (bool)$data['is_active'],
        );
    }

    public function toArray(): array
    {
        return [
            'name'            => $this->name,
            'company_name'    => $this->company_name,
            'phone'           => $this->phone,
            'address'         => $this->address,
            'opening_balance' => $this->opening_balance,
            'notes'           => $this->notes,
            'is_active'       => $this->is_active,
        ];
    }
}
