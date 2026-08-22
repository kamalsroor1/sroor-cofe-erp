<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\DTOs\Expenses\ExpenseDTO;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

final class UpdateExpenseAction
{
    /**
     * Update expense inside DB transaction
     */
    public function execute(Expense $expense, ExpenseDTO $dto): Expense
    {
        return DB::transaction(function () use ($expense, $dto) {
            $expense->update([
                'title'          => $dto->title,
                'category'       => $dto->category,
                'cost_center'    => $dto->cost_center,
                'amount'         => $dto->amount,
                'expense_date'   => $dto->expense_date,
                'payment_method' => $dto->payment_method,
                'notes'          => $dto->notes,
            ]);

            return $expense->fresh();
        });
    }
}
