<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final class QuickLoginDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $deviceName = 'web-spa',
        public readonly ?string $deviceIp = null,
    ) {}

    /**
     * @param  array{user_id: int|string, device_name?: string|null, device_ip?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $deviceName = trim((string) ($data['device_name'] ?? ''));

        return new self(
            userId: (int) $data['user_id'],
            deviceName: $deviceName !== '' ? $deviceName : 'web-spa',
            deviceIp: isset($data['device_ip']) && $data['device_ip'] !== '' ? (string) $data['device_ip'] : null,
        );
    }

    /**
     * @return array{user_id: int, device_name: string, device_ip: string|null}
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'device_name' => $this->deviceName,
            'device_ip' => $this->deviceIp,
        ];
    }
}
