<?php

declare(strict_types=1);

namespace App\Actions\Central\Data;

final class TwoFactorChallengeDTO
{
    public function __construct(
        public readonly string $challengeId,
        public readonly TwoFactorCodeDTO $secondFactor,
    ) {}

    /**
     * @param  array{challenge_id: string, code?: string|null, recovery_code?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            challengeId: trim((string) $data['challenge_id']),
            secondFactor: TwoFactorCodeDTO::fromArray($data),
        );
    }

    /**
     * The challenge id and codes are secrets and are left out.
     *
     * @return array{method: string}
     */
    public function toArray(): array
    {
        return $this->secondFactor->toArray();
    }
}
