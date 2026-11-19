<?php

declare(strict_types=1);

namespace App\Actions\Treasury;

use App\DTOs\Treasury\TreasuryTransferDTO;
use App\Models\TreasuryTransfer;
use App\Services\TreasuryService;

/**
 * SHOP-06: move money between two treasury accounts of one branch.
 *
 * TreasuryService::transfer() owns the money rules: one TransactionRetry unit that locks the
 * branch's store row first (deterministic order), re-reads the source balance under that lock,
 * refuses an overdraft (422) and only then allocates the TRF number and writes the transfer.
 */
final class CreateTreasuryTransferAction
{
    public function __construct(private readonly TreasuryService $treasuryService) {}

    public function execute(TreasuryTransferDTO $dto): TreasuryTransfer
    {
        return $this->treasuryService->transfer($dto->toArray())->load(['creator', 'store']);
    }
}
