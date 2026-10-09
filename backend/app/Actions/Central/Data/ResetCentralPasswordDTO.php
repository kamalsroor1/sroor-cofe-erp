<?php

declare(strict_types=1);

namespace App\Actions\Central\Data;

final class ResetCentralPasswordDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $token,
        public readonly string $password,
    ) {}

    /**
     * @param  array{email: string, token: string, password: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: mb_strtolower(trim((string) $data['email'])),
            token: (string) $data['token'],
            password: (string) $data['password'],
        );
    }

    /**
     * Token and password are deliberately left out.
     *
     * @return array{email: string}
     */
    public function toArray(): array
    {
        return ['email' => $this->email];
    }
}
