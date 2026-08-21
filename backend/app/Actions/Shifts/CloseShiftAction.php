<?php

declare(strict_types=1);

namespace App\Actions\Shifts;

use App\DTOs\Shifts\CloseShiftDTO;
use App\Models\CashShift;
use App\Services\ShiftService;
use Illuminate\Validation\ValidationException;

final class CloseShiftAction
{
    public function __construct(
        private readonly ShiftService $shiftService
    ) {}

    /**
     * Close cashier shift with counted actual cash balance and discrepancy calculation
     */
    public function execute(CloseShiftDTO $dto): CashShift
    {
        $shift = CashShift::where('status', 'open')->findOrFail($dto->shift_id);

        return $this->shiftService->closeShift(
            shift: $shift,
            actualCash: $dto->actual_cash_balance,
            notes: $dto->notes
        );
    }
}
