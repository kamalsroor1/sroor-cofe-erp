<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Models\Expense;

final class GetExpensesSummaryAction
{
    /**
     * Calculate monthly and period expense aggregates
     */
    public function execute(?int $storeId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $monthStart = now()->startOfMonth()->toDateString();

        $monthQuery = Expense::query()->when($storeId, fn($q) => $q->where('store_id', $storeId));
        $totalMonth = (float)(clone $monthQuery)->whereDate('expense_date', '>=', $monthStart)->sum('amount');
        $totalCashMonth = (float)(clone $monthQuery)->where('payment_method', 'cash')->whereDate('expense_date', '>=', $monthStart)->sum('amount');

        $filteredQuery = Expense::query()
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->when($fromDate, fn($q) => $q->whereDate('expense_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('expense_date', '<=', $toDate));

        $totalFiltered = (float)(clone $filteredQuery)->sum('amount');
        $countFiltered = (int)(clone $filteredQuery)->count();

        return [
            'total_month'    => $totalMonth,
            'total_cash'     => $totalCashMonth,
            'total_filtered' => $totalFiltered,
            'count_filtered' => $countFiltered,
        ];
    }
}
