<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use SensitiveParameter;

/**
 * SET-02: POST /api/v1/auth/unlock-screen. Exactly one of password / pin (enforced by
 * UnlockScreenRequest). Secrets stay out of stack traces and toArray().
 */
final readonly class UnlockScreenDTO
{
    public function __construct(
        #[SensitiveParameter] public ?string $password,
        #[SensitiveParameter] public ?string $pin,
    ) {}

    /**
     * @param  array{password?: string|null, pin?: string|null}  $data  validated request data
     */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        $password = $data['password'] ?? null;
        $pin = $data['pin'] ?? null;

        return new self(
            password: $password === null || $password === '' ? null : (string) $password,
            pin: $pin === null || $pin === '' ? null : (string) $pin,
        );
    }

    /**
     * @return array<string, never>
     */
    public function toArray(): array
    {
        // Never expose the secret.
        return [];
    }
}
