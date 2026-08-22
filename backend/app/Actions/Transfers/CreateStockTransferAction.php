<?php

declare(strict_types=1);

namespace App\Actions\Transfers;

use App\DTOs\Transfers\CreateTransferDTO;
use App\Models\StockTransfer;
use App\Services\StockTransferService;

final class CreateStockTransferAction
{
    public function __construct(
        private readonly StockTransferService $transferService
    ) {}

    /**
     * Create and confirm stock transfer between stores atomically
     */
    public function execute(CreateTransferDTO $dto): StockTransfer
    {
        return $this->transferService->createTransfer($dto->toArray());
    }
}
