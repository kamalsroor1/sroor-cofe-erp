<?php

declare(strict_types=1);

namespace App\Actions\Central\Data;

/**
 * A second factor as typed by the operator: a 6-digit TOTP code OR one recovery code
 * (the Form Requests guarantee exactly one is present).
 */
final class TwoFactorCodeDTO
{
    public function __construct(
        public readonly ?string $code,
        public readonly ?string $recoveryCode,
    ) {}

    /**
     * @param  array{code?: string|null, recovery_code?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $code = isset($data['code']) ? preg_replace('/\s+/', '', (string) $data['code']) : null;
        $recovery = isset($data['recovery_code']) ? trim((string) $data['recovery_code']) : null;

        return new self(
            code: $code !== null && $code !== '' ? $code : null,
            recoveryCode: $recovery !== null && $recovery !== '' ? $recovery : null,
        );
    }

    /**
     * Never carries the code values themselves (they are secrets).
     *
     * @return array{method: string}
     */
    public function toArray(): array
    {
        return ['method' => $this->recoveryCode !== null ? 'recovery_code' : 'totp'];
    }
}
