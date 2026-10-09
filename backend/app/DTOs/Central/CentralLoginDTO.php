<?php

declare(strict_types=1);

namespace App\DTOs\Central;

final class CentralLoginDTO
{
    public const DEFAULT_DEVICE_NAME = 'central-console';

    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $deviceName = self::DEFAULT_DEVICE_NAME,
    ) {}

    /**
     * @param  array{email: string, password: string, device_name?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $deviceName = isset($data['device_name']) ? trim((string) $data['device_name']) : '';

        return new self(
            email: mb_strtolower(trim((string) $data['email'])),
            password: (string) $data['password'],
            deviceName: $deviceName !== '' ? $deviceName : self::DEFAULT_DEVICE_NAME,
        );
    }

    /**
     * The password is deliberately left out.
     *
     * @return array{email: string, device_name: string}
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'device_name' => $this->deviceName,
        ];
    }
}
