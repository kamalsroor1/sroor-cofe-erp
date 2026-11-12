<?php

declare(strict_types=1);

namespace App\DTOs\Users;

final class CreateUserDTO
{
    /**
     * @param  list<int>  $store_ids  ROLE-01: branches assigned in the same save (store_user)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $phone,
        public readonly string $password,
        public readonly string $role = 'cashier',
        public readonly ?string $email = null,
        public readonly ?int $default_store_id = null,
        public readonly bool $is_active = true,
        public readonly array $store_ids = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            phone: (string) $data['phone'],
            password: (string) $data['password'],
            role: (string) ($data['role'] ?? 'cashier'),
            email: isset($data['email']) && $data['email'] !== '' ? (string) $data['email'] : null,
            default_store_id: isset($data['default_store_id']) && $data['default_store_id'] !== '' ? (int) $data['default_store_id'] : null,
            is_active: (bool) ($data['is_active'] ?? true),
            store_ids: self::storeIds($data['store_ids'] ?? null) ?? [],
        );
    }

    /**
     * @return list<int>|null null when the field was not sent or sent as null
     */
    public static function storeIds(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        return array_values(array_unique(array_map(fn ($id): int => (int) $id, $raw)));
    }
}
