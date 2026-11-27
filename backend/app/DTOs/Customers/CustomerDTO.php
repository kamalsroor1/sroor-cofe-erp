<?php

declare(strict_types=1);

namespace App\DTOs\Customers;

use App\Support\Money\Decimal;

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
        // SHOP-03: the credit limit (null = no limit). $credit_limit_provided = false when the request did
        // not send the field: create then uses the shop default and update keeps the stored limit.
        public readonly ?string $credit_limit = null,
        public readonly bool $credit_limit_provided = false,
        public readonly string $price_tier = 'retail',
        public readonly bool $price_tier_provided = false,
    ) {}

    public static function fromArray(array $data): self
    {
        $priceTier = isset($data['price_tier']) && is_string($data['price_tier']) && $data['price_tier'] !== ''
            ? $data['price_tier']
            : null;

        return new self(
            name: (string) $data['name'],
            phone: isset($data['phone']) && $data['phone'] !== '' ? (string) $data['phone'] : null,
            address: isset($data['address']) && $data['address'] !== '' ? (string) $data['address'] : null,
            tax_number: isset($data['tax_number']) && $data['tax_number'] !== '' ? (string) $data['tax_number'] : null,
            opening_balance: isset($data['opening_balance']) && is_numeric($data['opening_balance']) ? (string) $data['opening_balance'] : '0.000',
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null,
            is_active: ! isset($data['is_active']) || (bool) $data['is_active'],
            credit_limit: isset($data['credit_limit']) && $data['credit_limit'] !== '' ? Decimal::normalize((string) $data['credit_limit']) : null,
            credit_limit_provided: array_key_exists('credit_limit', $data),
            price_tier: $priceTier ?? 'retail',
            price_tier_provided: $priceTier !== null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'tax_number' => $this->tax_number,
            'opening_balance' => $this->opening_balance,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'credit_limit' => $this->credit_limit,
            'price_tier' => $this->price_tier,
        ];
    }
}
