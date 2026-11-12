<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Support\Money\Decimal;

final class GetExpensesSummaryAction
{
    /**
     * Calculate monthly and period expense aggregates
     *
     * ROLE-01: $recordedBy narrows every figure to the expenses that user recorded (a reader who
     * may not see everyone's expenses, see ExpensePolicy::seesEveryonesExpenses).
     */
    public function execute(?int $storeId = null, ?string $fromDate = null, ?string $toDate = null, ?int $recordedBy = null): array
    {
        $monthStart = now()->startOfMonth()->toDateString();

        $monthQuery = Expense::query()
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($recordedBy !== null, fn ($q) => $q->where('user_id', $recordedBy));
        $totalMonth = Decimal::normalize((string) (clone $monthQuery)->whereDate('expense_date', '>=', $monthStart)->sum('amount'));
        $totalCashMonth = Decimal::normalize((string) (clone $monthQuery)->where('payment_method', 'cash')->whereDate('expense_date', '>=', $monthStart)->sum('amount'));

        $filteredQuery = Expense::query()
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($recordedBy !== null, fn ($q) => $q->where('user_id', $recordedBy))
            ->when($fromDate, fn ($q) => $q->whereDate('expense_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('expense_date', '<=', $toDate));

        $totalFiltered = Decimal::normalize((string) (clone $filteredQuery)->sum('amount'));
        $countFiltered = (int) (clone $filteredQuery)->count();

        return [
            'total_month' => $totalMonth,
            'total_cash' => $totalCashMonth,
            'total_filtered' => $totalFiltered,
            'count_filtered' => $countFiltered,
        ];
    }
}
