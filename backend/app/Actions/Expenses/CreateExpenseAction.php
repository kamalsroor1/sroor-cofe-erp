<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\DTOs\Expenses\ExpenseDTO;
use App\Models\Expense;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

final class CreateExpenseAction
{
    /**
     * Create expense inside DB transaction with sequential number
     */
    public function execute(ExpenseDTO $dto, int $userId): Expense
    {
        return DB::transaction(function () use ($dto, $userId) {
            $storeId = $dto->store_id ?: Store::getMainStore()?->id ?: Store::first()?->id;

            $prefix = 'EXP-' . date('ymd');
            $count = Expense::whereDate('created_at', now()->toDateString())->count() + 1;
            $expenseNumber = $prefix . '-' . str_pad((string)$count, 4, '0', STR_PAD_LEFT);

            return Expense::create([
                'expense_number' => $expenseNumber,
                'title'          => $dto->title,
                'category'       => $dto->category,
                'cost_center'    => $dto->cost_center,
                'amount'         => $dto->amount,
                'expense_date'   => $dto->expense_date,
                'payment_method' => $dto->payment_method,
                'user_id'        => $userId,
                'store_id'       => $storeId,
                'notes'          => $dto->notes,
            ]);
        });
    }
}
