<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use SensitiveParameter;

/**
 * SIGN-15: POST /api/v1/auth/reset-password (shop host). The token and the new password stay out
 * of stack traces (#[SensitiveParameter]) and out of toArray().
 */
final readonly class ResetTenantPasswordDTO
{
    public function __construct(
        public string $email,
        #[SensitiveParameter] public string $token,
        #[SensitiveParameter] public string $password,
    ) {}

    /**
     * @param  array{email: string, token: string, password: string}  $data  validated request data
     */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            email: mb_strtolower(trim((string) $data['email'])),
            token: (string) $data['token'],
            password: (string) $data['password'],
        );
    }

    /**
     * @return array{email: string}
     */
    public function toArray(): array
    {
        return ['email' => $this->email];
    }
}
