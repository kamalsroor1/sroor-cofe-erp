<?php

namespace App\Services;

use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Support\TenantClock;
use Exception;
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
            throw new Exception("يوجد وردية عمل مفتوحة بالفعل لهذا الفرع برقم {$existing->shift_number}. يجب إغلاقها أولاً.");
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

        $storeName = Store::find($targetStoreId)?->name ?? 'الفرع الرئيسي';
        $this->activityLogService->logShift(
            action: 'shift_opened',
            shift: $shift,
            description: "تم فتح وردية عمل جديدة رقم [{$shiftNumber}] في فرع ({$storeName}) برصيد افتتاحي ".number_format((float) $openingCash, 2).' ج.م'
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
     */
    public function calculateShiftTotals(CashShift $shift): array
    {
        $openedAt = $shift->opened_at;
        $storeId = $shift->store_id;

        // Total cash sales (invoices paid in cash in this store)
        $cashSales = Invoice::where('status', 'confirmed')
            ->where('payment_type', 'cash')
            ->where('created_at', '>=', $openedAt)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->sum('paid_amount') ?: '0.000';

        // Credit sales on account
        $creditSales = Invoice::where('status', 'confirmed')
            ->whereIn('payment_type', ['credit', 'partial'])
            ->where('created_at', '>=', $openedAt)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->sum('remaining_amount') ?: '0.000';

        // Partial cash collected from partial invoices
        $partialCashSales = Invoice::where('status', 'confirmed')
            ->where('payment_type', 'partial')
            ->where('created_at', '>=', $openedAt)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->sum('paid_amount') ?: '0.000';

        // Total cash inflows from customer payment vouchers
        $paymentsCollected = Payment::where('created_at', '>=', $openedAt)
            ->whereNotNull('customer_id')
            ->where('payment_method', 'cash')
            ->sum('amount') ?: '0.000';

        $totalCashSalesInflow = bcadd((string) $cashSales, (string) $partialCashSales, 3);

        $totalCashIn = bccomp((string) $paymentsCollected, '0.000', 3) > 0
            ? bcadd((string) $paymentsCollected, (string) $totalCashSalesInflow, 3)
            : (string) $totalCashSalesInflow;

        // Cash outflows: Operational expenses paid in cash
        $expenses = Expense::where('created_at', '>=', $openedAt)
            ->where('payment_method', 'cash')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->sum('amount') ?: '0.000';

        // Cash outflows: Supplier payments paid in cash
        $supplierPaid = Payment::where('created_at', '>=', $openedAt)
            ->whereNotNull('supplier_id')
            ->where('payment_method', 'cash')
            ->sum('amount') ?: '0.000';

        // Sales Returns refunded in cash
        $refunds = ReturnDocument::where('created_at', '>=', $openedAt)
            ->where('return_type', 'sales_return')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->sum('total_amount') ?: '0.000';

        $cashSales = bcadd((string) $cashSales, '0.000', 3);
        $creditSales = bcadd((string) $creditSales, '0.000', 3);
        $expenses = bcadd((string) $expenses, '0.000', 3);
        $supplierPaid = bcadd((string) $supplierPaid, '0.000', 3);
        $refunds = bcadd((string) $refunds, '0.000', 3);

        $totalOutflows = bcadd((string) $expenses, (string) $supplierPaid, 3);
        $totalOutflows = bcadd($totalOutflows, (string) $refunds, 3);

        // Expected in drawer = Opening Cash + Total Cash In - Total Cash Out
        $expectedCash = bcadd((string) $shift->opening_cash_balance, $totalCashIn, 3);
        $expectedCash = bcsub($expectedCash, $totalOutflows, 3);

        return [
            'opening_cash_balance' => (string) $shift->opening_cash_balance,
            'total_cash_sales' => (string) $cashSales,
            'total_credit_sales' => (string) $creditSales,
            'total_payments_collected' => $totalCashIn,
            'total_expenses' => (string) $expenses,
            'total_supplier_paid' => (string) $supplierPaid,
            'total_refunds' => (string) $refunds,
            'expected_cash_balance' => (string) $expectedCash,
        ];
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

            $diffMsg = bccomp($diff, '0.000', 3) === 0
                ? 'متطابقة تماماً بدون عجز أو زيادة'
                : (bccomp($diff, '0.000', 3) > 0
                    ? 'بزيادة قدرها '.number_format((float) $diff, 2).' ج.م'
                    : 'بعجز قدره '.number_format((float) abs((float) $diff), 2).' ج.م');

            $this->activityLogService->logShift(
                action: 'shift_closed',
                shift: $shift,
                description: "تم تقفيل وإغلاق وردية العمل رقم [{$shift->shift_number}] بنقدية فعلية ".number_format((float) $actualCash, 2)." ج.م ({$diffMsg})",
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
