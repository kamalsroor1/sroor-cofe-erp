<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\DTOs\Expenses\ExpenseDTO;
use App\Models\Expense;
use App\Models\Store;
use App\Services\DocumentNumberService;
use App\Support\Database\TransactionRetry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

final class CreateExpenseAction
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
    ) {}

    /**
     * Create expense inside DB transaction with sequential number.
     *
     * Idempotent when $dto->client_uuid is set: a replay of the same key returns the
     * original expense (wasRecentlyCreated === false) with no new side effects.
     */
    public function execute(ExpenseDTO $dto, int $userId): Expense
    {
        // SETG-2 ext: number and daily counter follow the tenant business day.
        // DATE-03: EXP-YYYYMMDD-NNNN, the same format and counter as the expenses a sale records.
        // BUG-04: the day's counter row exists before the transaction takes its first read.
        $this->documentNumbers->prepareDated('EXP', Expense::class, 'expense_number');

        try {
            // BUG-07: restarted as a whole on a lock conflict; a failed attempt's number rolls back.
            return TransactionRetry::run(function () use ($dto, $userId) {
                $storeId = $dto->store_id ?: Store::getMainStore()?->id ?: Store::first()?->id;

                if ($dto->client_uuid !== null) {
                    $existing = Expense::query()->where('client_uuid', $dto->client_uuid)->first();
                    if ($existing instanceof Expense) {
                        $this->assertReplayMatches($existing, $storeId !== null ? (int) $storeId : null, $userId);

                        return $existing;
                    }
                }

                $expenseNumber = $this->documentNumbers->nextDated('EXP', 4, Expense::class, 'expense_number');

                return Expense::create([
                    'expense_number' => $expenseNumber,
                    'client_uuid' => $dto->client_uuid,
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
        } catch (UniqueConstraintViolationException $e) {
            // Backstop for a concurrent insert of the same client_uuid that slipped past the
            // replay check above. Any other unique violation (e.g. expense_number) is rethrown.
            if ($dto->client_uuid === null) {
                throw $e;
            }

            $existing = Expense::query()->where('client_uuid', $dto->client_uuid)->first();
            if (! $existing instanceof Expense) {
                throw $e;
            }

            $storeId = $dto->store_id ?: Store::getMainStore()?->id ?: Store::first()?->id;
            $this->assertReplayMatches($existing, $storeId !== null ? (int) $storeId : null, $userId);

            return $existing;
        }
    }

    /**
     * A replayed client_uuid must belong to the same store and actor; otherwise the key
     * was reused for a different operation and must not leak the original expense.
     */
    private function assertReplayMatches(Expense $existing, ?int $storeId, int $userId): void
    {
        $existingStoreId = $existing->store_id !== null ? (int) $existing->store_id : null;

        if ($existingStoreId !== $storeId || (int) $existing->user_id !== $userId) {
            throw ValidationException::withMessages([
                'client_uuid' => [__('expenses.idempotency_key_conflict')],
            ]);
        }
    }
}
