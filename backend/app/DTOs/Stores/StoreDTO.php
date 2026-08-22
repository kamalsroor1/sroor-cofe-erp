<?php

declare(strict_types=1);

namespace App\DTOs\Stores;

final class StoreDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $code = null,
        public readonly ?string $address = null,
        public readonly ?string $phone = null,
        public readonly bool $is_main = false,
        public readonly bool $is_active = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string)$data['name'],
            type: (string)$data['type'],
            code: isset($data['code']) && $data['code'] !== '' ? (string)$data['code'] : null,
            address: isset($data['address']) && $data['address'] !== '' ? (string)$data['address'] : null,
            phone: isset($data['phone']) && $data['phone'] !== '' ? (string)$data['phone'] : null,
            is_main: !empty($data['is_main']),
            is_active: !isset($data['is_active']) || (bool)$data['is_active'],
        );
    }

    public function toArray(): array
    {
        return [
            'name'      => $this->name,
            'type'      => $this->type,
            'code'      => $this->code,
            'address'   => $this->address,
            'phone'     => $this->phone,
            'is_main'   => $this->is_main,
            'is_active' => $this->is_active,
        ];
    }
}
