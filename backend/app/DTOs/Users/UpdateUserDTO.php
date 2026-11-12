<?php

declare(strict_types=1);

namespace App\DTOs\Users;

final class UpdateUserDTO
{
    /**
     * @param  list<int>|null  $store_ids  ROLE-01: the user's new branches (store_user); null keeps the current ones
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $phone,
        public readonly string $role = 'cashier',
        public readonly ?string $email = null,
        public readonly ?string $password = null,
        public readonly ?int $default_store_id = null,
        public readonly bool $is_active = true,
        public readonly ?array $store_ids = null,
    ) {}

    public static function fromArray(int $id, array $data): self
    {
        return new self(
            id: $id,
            name: (string) $data['name'],
            phone: (string) $data['phone'],
            role: (string) ($data['role'] ?? 'cashier'),
            email: isset($data['email']) && $data['email'] !== '' ? (string) $data['email'] : null,
            password: isset($data['password']) && $data['password'] !== '' ? (string) $data['password'] : null,
            default_store_id: isset($data['default_store_id']) && $data['default_store_id'] !== '' ? (int) $data['default_store_id'] : null,
            is_active: (bool) ($data['is_active'] ?? true),
            // ROLE-01: omitted or explicit null = unchanged; only an array (empty included) replaces.
            store_ids: CreateUserDTO::storeIds($data['store_ids'] ?? null),
        );
    }
}
