<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

final class GetExpensesBreakdownReportAction
{
    /**
     * Compute operational expenses categorized summary
     */
    public function execute(ReportFilterDTO $dto): array
    {
        $expensesByCategory = Expense::whereDate('expense_date', '>=', $dto->from_date)
            ->whereDate('expense_date', '<=', $dto->to_date)
            ->when($dto->store_id, fn($q) => $q->where('store_id', $dto->store_id))
            ->select('category', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn($e) => [
                'category' => $e->category,
                'amount'   => (float)$e->total_amount,
                'count'    => (int)$e->count,
            ])
            ->values()
            ->all();

        return $expensesByCategory;
    }
}
