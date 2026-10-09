<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\TreasuryTransfer;
use App\Support\TenantClock;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected ActivityLogService $activityLogService,
        protected TenantClock $tenantClock,
    ) {}

    /**
     * Get real-time financial balances per payment method / treasury account
     */
    public function getBalances(?int $storeId = null, ?string $date = null): array
    {
        $methods = PaymentMethod::activeMethods();
        $balances = [];
        $totalLiquidity = '0.000';

        foreach ($methods as $methodEnum) {
            $methodKey = $methodEnum->value;

            // 1. Inflows: All payments collected via this method (Sales + Debt collections)
            $inflows = (string) Payment::where('payment_method', $methodKey)
                ->whereNotNull('customer_id')
                ->when($date, fn ($q) => $q->whereDate('payment_date', '<=', $date))
                ->sum('amount');

            // 2. Outflows: Supplier payments + General Expenses recorded via this method
            $supplierOutflows = (string) Payment::where('payment_method', $methodKey)
                ->whereNotNull('supplier_id')
                ->when($date, fn ($q) => $q->whereDate('payment_date', '<=', $date))
                ->sum('amount');

            // Operational Expenses not generated via payments
            $generalExpenses = (string) Expense::where('payment_method', $methodKey)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->when($date, fn ($q) => $q->whereDate('expense_date', '<=', $date))
                ->sum('amount');

            // Expense payments (PAY-EXP)
            $expensePayments = (string) Payment::where('payment_method', $methodKey)
                ->where('payment_number', 'like', 'PAY-EXP-%')
                ->when($date, fn ($q) => $q->whereDate('payment_date', '<=', $date))
                ->sum('amount');

            $totalOutflows = bcadd($supplierOutflows, bcadd($generalExpenses, $expensePayments, 3), 3);

            // 3. Inbound Transfers (money received into this method)
            $inboundTransfers = (string) TreasuryTransfer::where('to_method', $methodKey)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->when($date, fn ($q) => $q->whereDate('transfer_date', '<=', $date))
                ->sum('amount');

            // 4. Outbound Transfers (money sent out from this method)
            $outboundTransfers = (string) TreasuryTransfer::where('from_method', $methodKey)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->when($date, fn ($q) => $q->whereDate('transfer_date', '<=', $date))
                ->sum('amount');

            // Outbound Transfer Fees
            $transferFees = (string) TreasuryTransfer::where('from_method', $methodKey)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->when($date, fn ($q) => $q->whereDate('transfer_date', '<=', $date))
                ->sum('transfer_fee');

            // Net Balance calculation: (Inflows + Inbound Transfers) - (Outflows + Outbound Transfers + Fees)
            $totalIn = bcadd($inflows, $inboundTransfers, 3);
            $totalOut = bcadd($totalOutflows, bcadd($outboundTransfers, $transferFees, 3), 3);
            $netBalance = bcsub($totalIn, $totalOut, 3);

            // Add Opening cash balance if physical cash drawer
            if ($methodEnum->isPhysicalCash()) {
                $activeShift = CashShift::where('status', 'open')
                    ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                    ->latest('id')
                    ->first();
                if ($activeShift) {
                    $netBalance = bcadd($netBalance, (string) $activeShift->opening_cash_balance, 3);
                }
            }

            $balances[$methodKey] = [
                'enum' => $methodEnum,
                'key' => $methodKey,
                'label' => $methodEnum->label(),
                'short_label' => $methodEnum->shortLabel(),
                'icon' => $methodEnum->icon(),
                'badge_class' => $methodEnum->badgeClass(),
                'inflows' => $totalIn,
                'outflows' => $totalOut,
                'balance' => $netBalance,
            ];

            $totalLiquidity = bcadd($totalLiquidity, $netBalance, 3);
        }

        $balances['total_liquidity'] = $totalLiquidity;

        return $balances;
    }

    /**
     * Execute an inter-account treasury transfer atomically
     */
    public function transfer(array $data): TreasuryTransfer
    {
        $fromMethod = $data['from_method'] ?? null;
        $toMethod = $data['to_method'] ?? null;
        $amount = (string) ($data['amount'] ?? '0.000');
        $fee = (string) ($data['transfer_fee'] ?? '0.000');
        $storeId = $data['store_id'] ?? null;
        $notes = $data['notes'] ?? null;
        $date = $data['transfer_date'] ?? $this->tenantClock->businessDate();
        // Authenticated user, else an explicit actor from a job/command, else NULL ("system").
        $actorId = Auth::id() ?? (isset($data['user_id']) && is_numeric($data['user_id']) ? (int) $data['user_id'] : null);

        if (empty($fromMethod) || empty($toMethod)) {
            throw new Exception('يرجى تحديد الحساب المحول منه والحساب المستلم.');
        }

        if ($fromMethod === $toMethod) {
            throw new Exception('عفواً، لا يمكن التحويل لنفس الحساب أو الخزينة!');
        }

        if (bccomp($amount, '0.000', 3) <= 0) {
            throw new Exception('يرجى إدخال مبلغ تحويل صحيح أكبر من الصفر.');
        }

        if (bccomp($fee, '0.000', 3) < 0) {
            $fee = '0.000';
        }

        // Check source balance sufficiency
        $balances = $this->getBalances(storeId: $storeId, date: $date);
        $sourceBal = $balances[$fromMethod]['balance'] ?? '0.000';
        $totalRequired = bcadd($amount, $fee, 3);

        if (bccomp($totalRequired, $sourceBal, 3) > 0) {
            $fromLabel = PaymentMethod::tryFrom($fromMethod)?->label() ?? $fromMethod;
            throw new Exception("عفواً، رصيد الحساب المحول منه [{$fromLabel}] غير كافٍ لإتمام التحويل (المتاح: ".number_format((float) $sourceBal, 2).' ج.م - المطلوب بالعمولة: '.number_format((float) $totalRequired, 2).' ج.م).');
        }

        return DB::transaction(function () use ($fromMethod, $toMethod, $amount, $fee, $storeId, $notes, $date, $actorId) {
            $transferNumber = 'TRF-'.str_replace('-', '', $this->tenantClock->businessDate()).'-'.strtoupper(substr(uniqid(), -4));

            $transfer = TreasuryTransfer::create([
                'transfer_number' => $transferNumber,
                'from_method' => $fromMethod,
                'to_method' => $toMethod,
                'amount' => $amount,
                'transfer_fee' => $fee,
                'store_id' => $storeId,
                'user_id' => $actorId,
                'transfer_date' => $date,
                'notes' => $notes,
            ]);

            $fromLabel = PaymentMethod::tryFrom($fromMethod)?->label() ?? $fromMethod;
            $toLabel = PaymentMethod::tryFrom($toMethod)?->label() ?? $toMethod;

            $this->auditLogService->log(
                action: 'treasury_transfer_created',
                auditable: $transfer,
                oldValues: null,
                newValues: $transfer->toArray()
            );

            $this->activityLogService->log(
                module: 'treasury',
                action: 'transfer',
                description: "تحويل رصيد مالي برقم [{$transferNumber}] بمبلغ ".number_format((float) $amount, 2)." ج.م من [{$fromLabel}] إلى [{$toLabel}]",
                subject: $transfer,
                storeId: $storeId
            );

            return $transfer;
        });
    }

    /**
     * Get a comprehensive report for all treasuries, accounts, and inter-account transfers over a date range
     */
    public function getTreasuryReport(string $fromDate, string $toDate, ?int $storeId = null, string $selectedMethod = 'all'): array
    {
        $methods = PaymentMethod::activeMethods();
        $accountSummaries = [];
        $totalOpeningBalance = '0.000';
        $totalPeriodInflows = '0.000';
        $totalPeriodOutflows = '0.000';
        $totalPeriodTransfersIn = '0.000';
        $totalPeriodTransfersOut = '0.000';
        $totalPeriodFees = '0.000';
        $totalCurrentBalance = '0.000';

        foreach ($methods as $methodEnum) {
            $key = $methodEnum->value;

            // 1. Opening Balance (All movements before $fromDate)
            $priorInflows = (string) Payment::where('payment_method', $key)
                ->whereNotNull('customer_id')
                ->whereDate('payment_date', '<', $fromDate)
                ->sum('amount');

            $priorSupplierOutflows = (string) Payment::where('payment_method', $key)
                ->whereNotNull('supplier_id')
                ->whereDate('payment_date', '<', $fromDate)
                ->sum('amount');

            $priorGeneralExpenses = (string) Expense::where('payment_method', $key)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->whereDate('expense_date', '<', $fromDate)
                ->sum('amount');

            $priorExpensePayments = (string) Payment::where('payment_method', $key)
                ->where('payment_number', 'like', 'PAY-EXP-%')
                ->whereDate('payment_date', '<', $fromDate)
                ->sum('amount');

            $priorInTransfers = (string) TreasuryTransfer::where('to_method', $key)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->whereDate('transfer_date', '<', $fromDate)
                ->sum('amount');

            $priorOutTransfers = (string) TreasuryTransfer::where('from_method', $key)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->whereDate('transfer_date', '<', $fromDate)
                ->sum('amount');

            $priorFees = (string) TreasuryTransfer::where('from_method', $key)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->whereDate('transfer_date', '<', $fromDate)
                ->sum('transfer_fee');

            $priorTotalIn = bcadd($priorInflows, $priorInTransfers, 3);
            $priorTotalOut = bcadd($priorSupplierOutflows, bcadd($priorGeneralExpenses, bcadd($priorExpensePayments, bcadd($priorOutTransfers, $priorFees, 3), 3), 3), 3);
            $openingBal = bcsub($priorTotalIn, $priorTotalOut, 3);

            // Add Opening cash shift balance if physical cash drawer
            if ($methodEnum->isPhysicalCash()) {
                $shift = CashShift::where('status', 'open')
                    ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                    ->latest('id')
                    ->first();
                if ($shift) {
                    $openingBal = bcadd($openingBal, (string) $shift->opening_cash_balance, 3);
                }
            }

            // 2. Period Movements ($fromDate to $toDate)
            $periodInflows = (string) Payment::where('payment_method', $key)
                ->whereNotNull('customer_id')
                ->whereDate('payment_date', '>=', $fromDate)
                ->whereDate('payment_date', '<=', $toDate)
                ->sum('amount');

            $periodSupplierOutflows = (string) Payment::where('payment_method', $key)
                ->whereNotNull('supplier_id')
                ->whereDate('payment_date', '>=', $fromDate)
                ->whereDate('payment_date', '<=', $toDate)
                ->sum('amount');

            $periodGeneralExpenses = (string) Expense::where('payment_method', $key)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->whereDate('expense_date', '>=', $fromDate)
                ->whereDate('expense_date', '<=', $toDate)
                ->sum('amount');

            $periodExpensePayments = (string) Payment::where('payment_method', $key)
                ->where('payment_number', 'like', 'PAY-EXP-%')
                ->whereDate('payment_date', '>=', $fromDate)
                ->whereDate('payment_date', '<=', $toDate)
                ->sum('amount');

            $periodOutflows = bcadd($periodSupplierOutflows, bcadd($periodGeneralExpenses, $periodExpensePayments, 3), 3);

            $periodInTransfers = (string) TreasuryTransfer::where('to_method', $key)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->whereDate('transfer_date', '>=', $fromDate)
                ->whereDate('transfer_date', '<=', $toDate)
                ->sum('amount');

            $periodOutTransfers = (string) TreasuryTransfer::where('from_method', $key)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->whereDate('transfer_date', '>=', $fromDate)
                ->whereDate('transfer_date', '<=', $toDate)
                ->sum('amount');

            $periodFees = (string) TreasuryTransfer::where('from_method', $key)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->whereDate('transfer_date', '>=', $fromDate)
                ->whereDate('transfer_date', '<=', $toDate)
                ->sum('transfer_fee');

            // Net Period Change & Closing Balance
            $netPeriodChange = bcsub(bcadd($periodInflows, $periodInTransfers, 3), bcadd($periodOutflows, bcadd($periodOutTransfers, $periodFees, 3), 3), 3);
            $closingBalance = bcadd($openingBal, $netPeriodChange, 3);

            $accountSummaries[$key] = [
                'enum' => $methodEnum,
                'key' => $key,
                'label' => $methodEnum->label(),
                'short_label' => $methodEnum->shortLabel(),
                'icon' => $methodEnum->icon(),
                'badge_class' => $methodEnum->badgeClass(),
                'opening_balance' => $openingBal,
                'inflows' => $periodInflows,
                'outflows' => $periodOutflows,
                'transfers_in' => $periodInTransfers,
                'transfers_out' => $periodOutTransfers,
                'fees' => $periodFees,
                'net_change' => $netPeriodChange,
                'closing_balance' => $closingBalance,
            ];

            $totalOpeningBalance = bcadd($totalOpeningBalance, $openingBal, 3);
            $totalPeriodInflows = bcadd($totalPeriodInflows, $periodInflows, 3);
            $totalPeriodOutflows = bcadd($totalPeriodOutflows, $periodOutflows, 3);
            $totalPeriodTransfersIn = bcadd($totalPeriodTransfersIn, $periodInTransfers, 3);
            $totalPeriodTransfersOut = bcadd($totalPeriodTransfersOut, $periodOutTransfers, 3);
            $totalPeriodFees = bcadd($totalPeriodFees, $periodFees, 3);
            $totalCurrentBalance = bcadd($totalCurrentBalance, $closingBalance, 3);
        }

        // Calculate percentage share of total liquidity
        foreach ($accountSummaries as $k => $acc) {
            $share = '0.00';
            if (bccomp($totalCurrentBalance, '0.000', 3) > 0 && bccomp($acc['closing_balance'], '0.000', 3) > 0) {
                $share = bcmul(bcdiv($acc['closing_balance'], $totalCurrentBalance, 4), '100', 1);
            }
            $accountSummaries[$k]['liquidity_share'] = $share;
        }

        // 3. Period Transfers Query
        $transfers = TreasuryTransfer::with(['creator', 'store'])
            ->when($fromDate, fn ($q) => $q->whereDate('transfer_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('transfer_date', '<=', $toDate))
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($selectedMethod !== 'all', function ($q) use ($selectedMethod) {
                $q->where(function ($sub) use ($selectedMethod) {
                    $sub->where('from_method', $selectedMethod)
                        ->orWhere('to_method', $selectedMethod);
                });
            })
            ->latest('id')
            ->get();

        // 4. Build Chronological Ledger Entries
        $ledgerEntries = $this->buildLedgerEntries($fromDate, $toDate, $storeId, $selectedMethod, $accountSummaries);

        return [
            'accounts' => $accountSummaries,
            'total_opening' => $totalOpeningBalance,
            'total_inflows' => $totalPeriodInflows,
            'total_outflows' => $totalPeriodOutflows,
            'total_transfers_in' => $totalPeriodTransfersIn,
            'total_transfers_out' => $totalPeriodTransfersOut,
            'total_fees' => $totalPeriodFees,
            'total_liquidity' => $totalCurrentBalance,
            'transfers' => $transfers,
            'ledger_entries' => $ledgerEntries,
        ];
    }

    /**
     * Build Chronological Running Ledger of Treasury Operations
     */
    protected function buildLedgerEntries(string $fromDate, string $toDate, ?int $storeId, string $selectedMethod, array $accountSummaries): array
    {
        $entries = [];

        // Customer Payments (Inflows)
        $payments = Payment::with(['customer', 'invoice'])
            ->whereNotNull('customer_id')
            ->whereDate('payment_date', '>=', $fromDate)
            ->whereDate('payment_date', '<=', $toDate)
            ->when($storeId, fn ($q) => $q->where(function ($sub) use ($storeId) {
                $sub->whereHas('invoice', fn ($iq) => $iq->where('store_id', $storeId))
                    ->orWhereNull('invoice_id');
            }))
            ->when($selectedMethod !== 'all', fn ($q) => $q->where('payment_method', $selectedMethod))
            ->get();

        foreach ($payments as $p) {
            $entries[] = [
                'date' => $p->payment_date->format('Y-m-d'),
                'time' => $p->created_at->format('H:i'),
                'timestamp' => $p->created_at->timestamp,
                'doc_number' => $p->payment_number,
                'type' => 'inflow',
                'type_label' => 'تحصيل مبيعات / سند قبض',
                'method' => $p->payment_method,
                'method_label' => PaymentMethod::tryFrom($p->payment_method)?->shortLabel() ?? $p->payment_method,
                'party' => $p->customer?->name ?? 'عميل نقدي',
                'notes' => $p->notes ?? 'تحصيل مبيعات',
                'debit' => (string) $p->amount,
                'credit' => '0.000',
            ];
        }

        // Supplier Payments (Outflows)
        $supplierPayments = Payment::with(['supplier', 'purchase'])
            ->whereNotNull('supplier_id')
            ->whereDate('payment_date', '>=', $fromDate)
            ->whereDate('payment_date', '<=', $toDate)
            ->when($storeId, fn ($q) => $q->where(function ($sub) use ($storeId) {
                $sub->whereHas('purchase', fn ($pq) => $pq->where('store_id', $storeId))
                    ->orWhereNull('purchase_id');
            }))
            ->when($selectedMethod !== 'all', fn ($q) => $q->where('payment_method', $selectedMethod))
            ->get();

        foreach ($supplierPayments as $sp) {
            $entries[] = [
                'date' => $sp->payment_date->format('Y-m-d'),
                'time' => $sp->created_at->format('H:i'),
                'timestamp' => $sp->created_at->timestamp,
                'doc_number' => $sp->payment_number,
                'type' => 'outflow',
                'type_label' => 'سداد مورد / دفعة توريد',
                'method' => $sp->payment_method,
                'method_label' => PaymentMethod::tryFrom($sp->payment_method)?->shortLabel() ?? $sp->payment_method,
                'party' => $sp->supplier?->name ?? 'مورد',
                'notes' => $sp->notes ?? 'سداد دفعة للمورد',
                'debit' => '0.000',
                'credit' => (string) $sp->amount,
            ];
        }

        // Expenses (Outflows)
        $expenses = Expense::with(['store', 'user'])
            ->whereDate('expense_date', '>=', $fromDate)
            ->whereDate('expense_date', '<=', $toDate)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($selectedMethod !== 'all', fn ($q) => $q->where('payment_method', $selectedMethod))
            ->get();

        foreach ($expenses as $e) {
            $entries[] = [
                'date' => $e->expense_date->format('Y-m-d'),
                'time' => $e->created_at->format('H:i'),
                'timestamp' => $e->created_at->timestamp,
                'doc_number' => $e->expense_number,
                'type' => 'expense',
                'type_label' => 'مصروف ونثريات',
                'method' => $e->payment_method,
                'method_label' => PaymentMethod::tryFrom($e->payment_method)?->shortLabel() ?? $e->payment_method,
                'party' => $e->category ?: 'مصروفات عامة',
                'notes' => $e->title.($e->notes ? " ({$e->notes})" : ''),
                'debit' => '0.000',
                'credit' => (string) $e->amount,
            ];
        }

        // Treasury Transfers (Inbound & Outbound)
        $transfers = TreasuryTransfer::with(['creator', 'store'])
            ->whereDate('transfer_date', '>=', $fromDate)
            ->whereDate('transfer_date', '<=', $toDate)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->get();

        foreach ($transfers as $tr) {
            $fromLabel = PaymentMethod::tryFrom($tr->from_method)?->shortLabel() ?? $tr->from_method;
            $toLabel = PaymentMethod::tryFrom($tr->to_method)?->shortLabel() ?? $tr->to_method;

            if ($selectedMethod === 'all' || $selectedMethod === $tr->from_method) {
                $totalOut = bcadd((string) $tr->amount, (string) $tr->transfer_fee, 3);
                $entries[] = [
                    'date' => $tr->transfer_date->format('Y-m-d'),
                    'time' => $tr->created_at->format('H:i'),
                    'timestamp' => $tr->created_at->timestamp,
                    'doc_number' => $tr->transfer_number,
                    'type' => 'transfer_out',
                    'type_label' => "تحويل صادر إلى [{$toLabel}]",
                    'method' => $tr->from_method,
                    'method_label' => $fromLabel,
                    'party' => "إلى: {$toLabel}",
                    'notes' => (bccomp((string) $tr->transfer_fee, '0.000', 3) > 0 ? "شامل عمولة {$tr->transfer_fee} ج.م - " : '').($tr->notes ?: 'تحويل بين الخزن'),
                    'debit' => '0.000',
                    'credit' => $totalOut,
                ];
            }

            if ($selectedMethod === 'all' || $selectedMethod === $tr->to_method) {
                $entries[] = [
                    'date' => $tr->transfer_date->format('Y-m-d'),
                    'time' => $tr->created_at->format('H:i'),
                    'timestamp' => $tr->created_at->timestamp,
                    'doc_number' => $tr->transfer_number,
                    'type' => 'transfer_in',
                    'type_label' => "تحويل وارد من [{$fromLabel}]",
                    'method' => $tr->to_method,
                    'method_label' => $toLabel,
                    'party' => "من: {$fromLabel}",
                    'notes' => $tr->notes ?: 'تحويل بين الخزن',
                    'debit' => (string) $tr->amount,
                    'credit' => '0.000',
                ];
            }
        }

        // Sort by timestamp asc to compute running balance correctly
        usort($entries, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        // Calculate running balance
        $runningBal = ($selectedMethod !== 'all' && isset($accountSummaries[$selectedMethod]))
            ? $accountSummaries[$selectedMethod]['opening_balance']
            : ($accountSummaries['cash']['opening_balance'] ?? '0.000');

        foreach ($entries as $idx => $ent) {
            $runningBal = bcadd(bcsub($runningBal, $ent['credit'], 3), $ent['debit'], 3);
            $entries[$idx]['running_balance'] = $runningBal;
        }

        // Reverse to show latest on top for UI
        return array_reverse($entries);
    }
}
