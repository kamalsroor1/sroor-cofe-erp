<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\DTOs\Expenses\ExpenseDTO;
use App\Models\Expense;
use App\Models\Store;
use App\Services\DocumentNumberService;
use App\Support\Database\TransactionRetry;
use App\Support\TenantClock;

final class CreateExpenseAction
{
    public function __construct(
        private readonly TenantClock $tenantClock,
        private readonly DocumentNumberService $documentNumbers,
    ) {}

    /**
     * Create expense inside DB transaction with sequential number
     */
    public function execute(ExpenseDTO $dto, int $userId): Expense
    {
        // SETG-2 ext: number and daily counter follow the tenant business day.
        $prefix = 'EXP-'.substr(str_replace('-', '', $this->tenantClock->businessDate()), 2);

        // BUG-04: the day's counter row exists before the transaction takes its first read.
        $this->documentNumbers->prepareFor($prefix, Expense::class, 'expense_number');

        // BUG-07: restarted as a whole on a lock conflict; a failed attempt's number rolls back.
        return TransactionRetry::run(function () use ($dto, $userId, $prefix) {
            $storeId = $dto->store_id ?: Store::getMainStore()?->id ?: Store::first()?->id;

            $expenseNumber = $this->documentNumbers->nextFor($prefix, 4, Expense::class, 'expense_number');

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
