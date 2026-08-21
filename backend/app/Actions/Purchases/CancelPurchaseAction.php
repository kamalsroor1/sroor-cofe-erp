<?php

declare(strict_types=1);

namespace App\Actions\Purchases;

use App\DTOs\Purchases\CancelPurchaseDTO;
use App\Models\Purchase;
use App\Services\PurchaseService;

final class CancelPurchaseAction
{
    public function __construct(
        private readonly PurchaseService $purchaseService
    ) {}

    /**
     * Cancel purchase invoice and reverse inventory and debt
     */
    public function execute(CancelPurchaseDTO $dto): Purchase
    {
        $purchase = Purchase::findOrFail($dto->purchase_id);

        return $this->purchaseService->cancelPurchase($purchase, $dto->reason);
    }
}
