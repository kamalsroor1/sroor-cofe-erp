<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use SensitiveParameter;

/**
 * SELL-01: POST /api/v1/auth/change-password. Both values stay out of stack traces
 * (#[SensitiveParameter]) and toArray().
 */
final readonly class ChangePasswordDTO
{
    public function __construct(
        #[SensitiveParameter] public string $currentPassword,
        #[SensitiveParameter] public string $password,
    ) {}

    /**
     * @param  array{current_password: string, password: string}  $data  validated request data
     */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            currentPassword: (string) $data['current_password'],
            password: (string) $data['password'],
        );
    }

    /**
     * @return array<string, never>
     */
    public function toArray(): array
    {
        // Never expose either password.
        return [];
    }
}
