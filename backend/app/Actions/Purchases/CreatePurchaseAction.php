<?php

declare(strict_types=1);

namespace App\Actions\Purchases;

use App\DTOs\Purchases\PurchaseDTO;
use App\Models\Purchase;
use App\Services\PurchaseService;

final class CreatePurchaseAction
{
    public function __construct(
        private readonly PurchaseService $purchaseService
    ) {}

    /**
     * Create purchase invoice and allocate landed costs
     */
    public function execute(PurchaseDTO $dto): Purchase
    {
        return $this->purchaseService->createPurchase($dto->toArray());
    }
}
