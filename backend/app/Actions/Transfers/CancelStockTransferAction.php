<?php

declare(strict_types=1);

namespace App\Actions\Transfers;

use App\DTOs\Transfers\CancelTransferDTO;
use App\Models\StockTransfer;
use App\Services\StockTransferService;

final class CancelStockTransferAction
{
    public function __construct(
        private readonly StockTransferService $transferService
    ) {}

    /**
     * Cancel transfer and safely rollback stock to source store
     */
    public function execute(CancelTransferDTO $dto): StockTransfer
    {
        $transfer = StockTransfer::findOrFail($dto->transfer_id);

        return $this->transferService->cancelTransfer($transfer, $dto->reason);
    }
}
