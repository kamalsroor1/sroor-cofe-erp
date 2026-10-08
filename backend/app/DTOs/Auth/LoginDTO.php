<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\LoginRequest;

final class LoginDTO
{
    public function __construct(
        public readonly string $phone,
        public readonly string $password,
        public readonly bool $remember = false,
    ) {}

    public static function fromRequest(LoginRequest $request): self
    {
        return new self(
            phone: trim((string) $request->validated('phone')),
            password: (string) $request->validated('password'),
            remember: (bool) $request->boolean('remember', false),
        );
    }
}
