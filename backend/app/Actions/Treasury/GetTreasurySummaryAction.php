<?php

declare(strict_types=1);

namespace App\Actions\Treasury;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Services\DailyCashFlowService;
use App\Services\TreasuryService;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Today's treasury summary of one branch (GET /api/v1/treasury/summary).
 *
 * BUG-01: cash in/out comes from DailyCashFlowService (the branch's cash vouchers only). The
 * old controller added invoices.paid_amount to every customer voucher of the tenant, so each
 * cash sale was counted twice and other branches' receipts and supplier payments leaked in.
 * Money is returned as scale-3 bcmath strings.
 */
final class GetTreasurySummaryAction
{
    public function __construct(
        private readonly DailyCashFlowService $dailyCashFlow,
        private readonly TreasuryService $treasuryService,
        private readonly TenantClock $tenantClock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(int $storeId): array
    {
        // The tenant's business day (SETG-2), not the server's.
        $today = $this->tenantClock->today();

        $salesTotal = $this->sum(Invoice::query()
            ->where('status', '!=', 'cancelled')
            ->whereDate('invoice_date', $today)
            ->where('store_id', $storeId), 'net_total');

        $expensesTotal = $this->sum(Expense::query()
            ->whereDate('expense_date', $today)
            ->where('store_id', $storeId), 'amount');

        $cashFlow = $this->dailyCashFlow->forDate($today, $storeId);

        // Receivables / payables are per tenant: customers and suppliers are not branch records.
        $totalReceivable = $this->sum(Customer::query()->where('current_balance', '>', 0), 'current_balance');
        $totalPayable = $this->sum(Supplier::query()->where('current_balance', '>', 0), 'current_balance');

        $activeShift = CashShift::query()->where('store_id', $storeId)->where('status', 'open')->latest('id')->first();

        return [
            'store_id' => $storeId,
            'today' => [
                'date' => $today,
                'sales_total' => $salesTotal,
                'cash_collected' => $cashFlow['cash_sales'],
                'customer_receipts' => $cashFlow['customer_receipts'],
                'total_inflow' => $cashFlow['total_cash_in'],
                'supplier_paid' => $cashFlow['supplier_paid'],
                'expenses_total' => $expensesTotal,
                'cash_expenses' => $cashFlow['cash_expenses'],
                'total_outflow' => $cashFlow['total_cash_out'],
                'net_cash' => $cashFlow['net_cash'],
            ],
            'balances' => [
                'total_receivable' => $totalReceivable,
                'total_payable' => $totalPayable,
                'accounts' => $this->treasuryService->getBalances($storeId),
            ],
            'active_shift' => $activeShift instanceof CashShift ? [
                'id' => $activeShift->id,
                'shift_number' => $activeShift->shift_number ?? $activeShift->id,
                'opening_cash_balance' => Decimal::normalize((string) $activeShift->opening_cash_balance),
                'opened_at' => $activeShift->opened_at,
            ] : null,
        ];
    }

    /**
     * SUM of a DECIMAL(12,3) column as a scale-3 string. MySQL returns an exact decimal string;
     * sqlite may return int/float, which Decimal::normalize settles half-up.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function sum(Builder $query, string $column): string
    {
        return Decimal::normalize((string) $query->sum($column));
    }
}
