<?php

declare(strict_types=1);

namespace App\DTOs\Shifts;

final class CloseShiftDTO
{
    public function __construct(
        public readonly int $shift_id,
        public readonly string $actual_cash_balance,
        public readonly ?string $notes = null,
    ) {}

    public static function fromArray(int $shiftId, array $data): self
    {
        return new self(
            shift_id: $shiftId,
            actual_cash_balance: (string)$data['actual_cash_balance'],
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string)$data['notes'] : null,
        );
    }
}
