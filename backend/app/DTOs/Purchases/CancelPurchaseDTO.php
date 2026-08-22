<?php

declare(strict_types=1);

namespace App\DTOs\Purchases;

final class CancelPurchaseDTO
{
    public function __construct(
        public readonly int $purchase_id,
        public readonly ?string $reason = null,
    ) {}

    public static function fromArray(int $purchaseId, array $data): self
    {
        return new self(
            purchase_id: $purchaseId,
            reason: isset($data['reason']) && $data['reason'] !== '' ? (string)$data['reason'] : null,
        );
    }
}
