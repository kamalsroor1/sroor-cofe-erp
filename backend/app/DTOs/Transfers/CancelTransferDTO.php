<?php

declare(strict_types=1);

namespace App\DTOs\Transfers;

final class CancelTransferDTO
{
    public function __construct(
        public readonly int $transfer_id,
        public readonly string $reason = 'إلغاء من النظام',
    ) {}

    public static function fromArray(int $transferId, array $data): self
    {
        return new self(
            transfer_id: $transferId,
            reason: (string)($data['reason'] ?? 'إلغاء من النظام'),
        );
    }
}
