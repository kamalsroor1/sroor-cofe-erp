<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

final class DeleteExpenseAction
{
    /**
     * Delete expense
     */
    public function execute(Expense $expense): bool
    {
        return DB::transaction(function () use ($expense) {
            return (bool)$expense->delete();
        });
    }
}
