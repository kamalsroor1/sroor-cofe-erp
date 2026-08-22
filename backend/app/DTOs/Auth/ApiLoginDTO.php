<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\ApiLoginRequest;

final class ApiLoginDTO
{
    public function __construct(
        public readonly string $login,
        public readonly string $password,
        public readonly string $deviceName = 'web-spa',
        public readonly ?string $tenantId = null,
        public readonly ?string $deviceIp = null,
    ) {}

    public static function fromRequest(ApiLoginRequest $request): self
    {
        $loginInput = $request->validated('login') 
            ?? $request->validated('phone') 
            ?? $request->validated('email') 
            ?? '';

        return new self(
            login: trim((string)$loginInput),
            password: (string)$request->validated('password'),
            deviceName: (string)($request->validated('device_name') ?? $request->header('X-Device-Name') ?? 'web-spa'),
            tenantId: $request->validated('tenant') ?? $request->header('X-Tenant') ?? null,
            deviceIp: $request->ip(),
        );
    }
}
