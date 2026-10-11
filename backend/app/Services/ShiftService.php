<?php

namespace App\Services;

use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShiftService
{
    public function __construct(
        protected ?ActivityLogService $activityLogService = null,
        protected ?TenantClock $tenantClock = null,
    ) {
        $this->activityLogService = $this->activityLogService ?: app(ActivityLogService::class);
        $this->tenantClock = $this->tenantClock ?: app(TenantClock::class);
    }

    /**
     * Get active shift for a specific store or user
     */
    public function getActiveShift(?int $storeId = null, ?int $userId = null): ?CashShift
    {
        return CashShift::where('status', 'open')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest()
            ->first();
    }

    /**
     * Open a new shift for a store/van
     */
    public function openShift(string $openingCash = '0.000', ?string $notes = null, ?int $storeId = null, ?int $actorId = null): CashShift
    {
        $targetStoreId = $storeId
            ?? session('current_store_id')
            ?? Auth::user()?->getCurrentStore()?->id
            ?? Store::getMainStore()?->id;

        $existing = $this->getActiveShift(storeId: $targetStoreId);
        if ($existing) {
            throw new Exception(__('treasury.shift_already_open', ['number' => $existing->shift_number]));
        }

        $shiftNumber = $this->nextShiftNumber();

        $shift = CashShift::create([
            // Authenticated user, else the explicit actor (job/command), else NULL ("system").
            'user_id' => Auth::id() ?? $actorId,
            'store_id' => $targetStoreId,
            'shift_number' => $shiftNumber,
            'status' => 'open',
            'opened_at' => now(),
            'opening_cash_balance' => $openingCash,
            'notes' => $notes,
        ]);

        $storeName = Store::find($targetStoreId)?->name ?? __('common.main_store_default');
        $this->activityLogService->logShift(
            action: 'shift_opened',
            shift: $shift,
            description: __('treasury.activity.shift_opened', [
                'number' => $shiftNumber,
                'store' => $storeName,
                'amount' => number_format((float) $openingCash, 2),
                'currency' => __('common.currency'),
            ])
        );

        return $shift;
    }

    /**
     * SETG-2 (+ ext): the daily shift sequence restarts at the start of the business day
     * (tenant clock + business_day_cutoff), and the date in the number is the business
     * date — a shift opened at 01:30 with cutoff 03:00 belongs to the previous day. The
     * next sequence is derived from the numbers already issued for that date (not from
     * opened_at), so it never collides with an existing number even if the timezone or
     * cutoff changed earlier the same day.
     */
    private function nextShiftNumber(): string
    {
        $prefix = 'SHIFT-'.str_replace('-', '', $this->tenantClock->businessDate()).'-';

        $lastNumber = CashShift::query()
            ->where('shift_number', 'like', $prefix.'%')
            ->orderByDesc('shift_number')
            ->value('shift_number');

        $sequence = $lastNumber !== null ? ((int) substr((string) $lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate current live metrics for open shift (scoped by store)
     *
     * BUG-01: drawer cash comes from the cash payment vouchers only. Every cash amount that
     * reaches the drawer is exactly one voucher: the invoice writes one per paid line at the
     * sale (PAY-INV-*), a debt collection writes its own. Summing invoices.paid_amount as
     * well counted every sale twice, also counted non-cash split lines as cash and later
     * collections against the invoice again. Vouchers are limited to the shift's store and
     * to the shift's window [opened_at, closed_at ?? now].
     */
    public function calculateShiftTotals(CashShift $shift): array
    {
        $openedAt = $shift->opened_at;
        $closesAt = $shift->closed_at ?? now();
        $storeId = $shift->store_id !== null ? (int) $shift->store_id : null;

        $cashVouchers = fn () => Payment::query()
            ->where('payments.payment_method', 'cash')
            ->where('payments.created_at', '>=', $openedAt)
            ->where('payments.created_at', '<=', $closesAt)
            ->when($storeId !== null, fn ($q) => $q->forStore((int) $storeId));

        // Cash taken at the sale (cash invoices and the paid part of partial invoices)
        $cashSales = $this->sumDecimal($cashVouchers()->collectedAtSale()->excludingCancelledInvoices(), 'payments.amount');

        // Cash collected from customers afterwards (receipts / debt collection)
        $customerReceipts = $this->sumDecimal($cashVouchers()->customerReceipts()->excludingCancelledInvoices(), 'payments.amount');

        // Credit sales on account
        $creditSales = $this->sumDecimal(Invoice::where('status', 'confirmed')
            ->whereIn('payment_type', ['credit', 'partial'])
            ->where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closesAt)
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId)), 'remaining_amount');

        $totalCashIn = bcadd($cashSales, $customerReceipts, 3);

        // Cash outflows: Operational expenses paid in cash
        $expenses = $this->sumDecimal(Expense::where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closesAt)
            ->where('payment_method', 'cash')
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId)), 'amount');

        // Cash outflows: Supplier payments paid in cash from this drawer
        $supplierPaid = $this->sumDecimal($cashVouchers()->whereNotNull('payments.supplier_id'), 'payments.amount');

        // Sales Returns refunded in cash
        $refunds = $this->sumDecimal(ReturnDocument::where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closesAt)
            ->where('return_type', 'sales_return')
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId)), 'total_amount');

        $totalOutflows = bcadd($expenses, $supplierPaid, 3);
        $totalOutflows = bcadd($totalOutflows, $refunds, 3);

        // Expected in drawer = Opening Cash + Total Cash In - Total Cash Out
        $openingCash = Decimal::normalize((string) $shift->opening_cash_balance);
        $expectedCash = bcadd($openingCash, $totalCashIn, 3);
        $expectedCash = bcsub($expectedCash, $totalOutflows, 3);

        return [
            'opening_cash_balance' => $openingCash,
            'total_cash_sales' => $cashSales,
            'total_customer_receipts' => $customerReceipts,
            'total_credit_sales' => $creditSales,
            'total_payments_collected' => $totalCashIn,
            'total_expenses' => $expenses,
            'total_supplier_paid' => $supplierPaid,
            'total_refunds' => $refunds,
            'expected_cash_balance' => $expectedCash,
        ];
    }

    /**
     * SUM of a DECIMAL(12,3) column as a scale-3 string. MySQL returns an exact decimal
     * string; sqlite may return int/float, which Decimal::normalize settles half-up.
     *
     * @param  Builder<covariant Model>  $query
     */
    private function sumDecimal(Builder $query, string $column): string
    {
        return Decimal::normalize((string) $query->sum($column));
    }

    /**
     * Close shift with actual counted cash
     */
    public function closeShift(CashShift $shift, string $actualCash, ?string $notes = null): CashShift
    {
        return DB::transaction(function () use ($shift, $actualCash, $notes) {
            $totals = $this->calculateShiftTotals($shift);

            $diff = bcsub($actualCash, $totals['expected_cash_balance'], 3);

            $shift->update([
                'status' => 'closed',
                'closed_at' => now(),
                'total_cash_sales' => $totals['total_cash_sales'],
                'total_credit_sales' => $totals['total_credit_sales'],
                'total_payments_collected' => $totals['total_payments_collected'],
                'total_refunds' => $totals['total_refunds'],
                'expected_cash_balance' => $totals['expected_cash_balance'],
                'actual_cash_balance' => $actualCash,
                'cash_difference' => $diff,
                'notes' => $notes ?: $shift->notes,
            ]);

            $currency = __('common.currency');
            $diffMsg = bccomp($diff, '0.000', 3) === 0
                ? __('treasury.cash_diff_matched')
                : (bccomp($diff, '0.000', 3) > 0
                    ? __('treasury.cash_diff_surplus', ['amount' => number_format((float) $diff, 2), 'currency' => $currency])
                    : __('treasury.cash_diff_shortage', ['amount' => number_format((float) abs((float) $diff), 2), 'currency' => $currency]));

            $this->activityLogService->logShift(
                action: 'shift_closed',
                shift: $shift,
                description: __('treasury.activity.shift_closed', [
                    'number' => $shift->shift_number,
                    'amount' => number_format((float) $actualCash, 2),
                    'currency' => $currency,
                    'status' => $diffMsg,
                ]),
                properties: [
                    'expected_cash' => $totals['expected_cash_balance'],
                    'actual_cash' => $actualCash,
                    'difference' => $diff,
                ]
            );

            // Send instant Telegram alert if there is a cash discrepancy
            if (bccomp($diff, '0.000', 3) !== 0) {
                try {
                    app(TelegramService::class)->sendShiftDiscrepancyNotification($shift);
                } catch (\Throwable $e) {
                    Log::error('Shift discrepancy notification failed: '.$e->getMessage());
                }
            }

            return $shift;
        });
    }
}
