<?php

declare(strict_types=1);

namespace App\DTOs\Users;

/**
 * BE-08 / SEC-20: set (or, with a null pin, clear) a user's manager approval PIN.
 */
final class SetUserPosPinDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly ?string $pin,
    ) {}

    /**
     * @param  array{pin?: string|null}  $data  validated request data
     */
    public static function fromArray(int $userId, array $data): self
    {
        $pin = $data['pin'] ?? null;

        return new self(
            userId: $userId,
            pin: $pin === null || $pin === '' ? null : (string) $pin,
        );
    }

    /**
     * @return array{user_id: int, pin_set: bool}
     */
    public function toArray(): array
    {
        // Never expose the PIN itself.
        return ['user_id' => $this->userId, 'pin_set' => $this->pin !== null];
    }
}
