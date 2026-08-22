<?php

declare(strict_types=1);

namespace App\DTOs\Users;

final class CreateUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $phone,
        public readonly string $password,
        public readonly string $role = 'cashier',
        public readonly ?string $email = null,
        public readonly ?int $default_store_id = null,
        public readonly bool $is_active = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string)$data['name'],
            phone: (string)$data['phone'],
            password: (string)$data['password'],
            role: (string)($data['role'] ?? 'cashier'),
            email: isset($data['email']) && $data['email'] !== '' ? (string)$data['email'] : null,
            default_store_id: isset($data['default_store_id']) && $data['default_store_id'] !== '' ? (int)$data['default_store_id'] : null,
            is_active: (bool)($data['is_active'] ?? true),
        );
    }
}
