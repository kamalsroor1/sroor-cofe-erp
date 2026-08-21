<?php

declare(strict_types=1);

namespace App\DTOs\Items;

final class ItemDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $code = null,
        public readonly ?string $category = null,
        public readonly string $unit = 'كجم',
        public readonly string $cost_price = '0.000',
        public readonly string $selling_price = '0.000',
        public readonly string $min_stock_level = '0.000',
        public readonly ?string $notes = null,
        public readonly bool $is_active = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string)$data['name'],
            code: isset($data['code']) && $data['code'] !== '' ? (string)$data['code'] : null,
            category: isset($data['category']) && $data['category'] !== '' ? (string)$data['category'] : null,
            unit: (string)($data['unit'] ?? 'كجم'),
            cost_price: isset($data['cost_price']) ? (string)$data['cost_price'] : '0.000',
            selling_price: isset($data['selling_price']) ? (string)$data['selling_price'] : '0.000',
            min_stock_level: isset($data['min_stock_level']) ? (string)$data['min_stock_level'] : '0.000',
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string)$data['notes'] : null,
            is_active: !isset($data['is_active']) || (bool)$data['is_active'],
        );
    }

    public function toArray(): array
    {
        return [
            'name'            => $this->name,
            'code'            => $this->code,
            'category'        => $this->category,
            'unit'            => $this->unit,
            'cost_price'      => $this->cost_price,
            'selling_price'   => $this->selling_price,
            'min_stock_level' => $this->min_stock_level,
            'notes'           => $this->notes,
            'is_active'       => $this->is_active,
        ];
    }
}
