<?php

declare(strict_types=1);

namespace App\Actions\Shifts;

use App\DTOs\Shifts\OpenShiftDTO;
use App\Models\CashShift;
use App\Services\ShiftService;

final class OpenShiftAction
{
    public function __construct(
        private readonly ShiftService $shiftService
    ) {}

    /**
     * Open a new shift for a cashier and store
     */
    public function execute(OpenShiftDTO $dto, int $userId): CashShift
    {
        return $this->shiftService->openShift(
            openingCash: $dto->opening_cash_balance,
            notes: $dto->notes,
            storeId: $dto->store_id
        );
    }
}
