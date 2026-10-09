<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\DTOs\Expenses\ExpenseDTO;
use App\Models\Expense;
use App\Models\Store;
use App\Support\TenantClock;
use Illuminate\Support\Facades\DB;

final class CreateExpenseAction
{
    public function __construct(
        private readonly TenantClock $tenantClock,
    ) {}

    /**
     * Create expense inside DB transaction with sequential number
     */
    public function execute(ExpenseDTO $dto, int $userId): Expense
    {
        return DB::transaction(function () use ($dto, $userId) {
            $storeId = $dto->store_id ?: Store::getMainStore()?->id ?: Store::first()?->id;

            // SETG-2 ext: number and daily counter follow the tenant business day.
            $businessDate = $this->tenantClock->businessDate();
            [$dayStart, $dayEnd] = $this->tenantClock->businessDayRange($businessDate);
            $prefix = 'EXP-'.substr(str_replace('-', '', $businessDate), 2);
            $count = Expense::where('created_at', '>=', $dayStart)
                ->where('created_at', '<', $dayEnd)
                ->count() + 1;
            $expenseNumber = $prefix.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);

            return Expense::create([
                'expense_number' => $expenseNumber,
                'title' => $dto->title,
                'category' => $dto->category,
                'cost_center' => $dto->cost_center,
                'amount' => $dto->amount,
                'expense_date' => $dto->expense_date,
                'payment_method' => $dto->payment_method,
                'user_id' => $userId,
                'store_id' => $storeId,
                'notes' => $dto->notes,
            ]);
        });
    }
}
