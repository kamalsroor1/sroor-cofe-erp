<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Store;
use App\Models\TreasuryTransfer;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected ActivityLogService $activityLogService,
        protected TenantClock $tenantClock,
        protected DocumentNumberService $documentNumbers,
    ) {}

    /**
     * Get real-time financial balances per payment method / treasury account.
     *
     * BE-11: every payment sum respects the branch (Payment::forStore) and ALL payment methods
     * are covered. Methods outside PaymentMethod::activeMethods() (visa, bank_transfer, check,
     * other) are listed only when they have any movement, so the three active accounts stay the
     * stable core of the response and the dashboard is not padded with empty accounts.
     */
    public function getBalances(?int $storeId = null, ?string $date = null): array
    {
        $activeKeys = array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::activeMethods());
        $balances = [];
        $totalLiquidity = '0.000';

        foreach (PaymentMethod::cases() as $methodEnum) {
            $methodKey = $methodEnum->value;

            $m = $this->movements($methodKey, $storeId, onOrBefore: $date);

            // Net Balance calculation: (Inflows + Inbound Transfers) - (Outflows + Outbound Transfers + Fees)
            $totalIn = bcadd($m['customer_inflows'], $m['transfers_in'], 3);
            $totalOutflows = bcadd($m['supplier_outflows'], bcadd($m['general_expenses'], $m['expense_payments'], 3), 3);
            $totalOut = bcadd($totalOutflows, bcadd($m['transfers_out'], $m['fees'], 3), 3);
            $netBalance = bcsub($totalIn, $totalOut, 3);

            // Add Opening cash balance if physical cash drawer
            if ($methodEnum->isPhysicalCash()) {
                $netBalance = bcadd($netBalance, $this->openShiftsOpeningCash($storeId), 3);
            } elseif (! in_array($methodKey, $activeKeys, true) && $this->isZero($totalIn) && $this->isZero($totalOut)) {
                continue;
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
     * Execute an inter-account treasury transfer atomically.
     *
     * Lock design (BE-11): the sufficiency check and the insert share one transaction that first
     * locks the `stores` row(s) the balance is computed from, so two concurrent transfers of the
     * same branch serialise and the second one sees the first one's outflow.
     *  - store transfer  -> locks that store row;
     *  - null-store (all branches) transfer -> locks EVERY store row, ascending id.
     * A null-store transfer draws on the money of all branches, so it must wait for any branch
     * transfer, and vice versa; with ascending-id acquisition (one row for a branch transfer)
     * there is no lock cycle, hence no deadlock. The TRF number is allocated only after these
     * locks (BE-09 order: lock the business rows first, number sequence last).
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
            throw BusinessRuleException::make('treasury.transfer_accounts_required');
        }

        if ($fromMethod === $toMethod) {
            throw BusinessRuleException::make('treasury.same_account_error');
        }

        if (bccomp($amount, '0.000', 3) <= 0) {
            throw BusinessRuleException::make('treasury.transfer_amount_invalid');
        }

        if (bccomp($fee, '0.000', 3) < 0) {
            $fee = '0.000';
        }

        return DB::transaction(function () use ($fromMethod, $toMethod, $amount, $fee, $storeId, $notes, $date, $actorId) {
            $this->lockTreasuryScope($storeId);

            // Check source balance sufficiency (under the lock)
            $balances = $this->getBalances(storeId: $storeId, date: $date);
            $sourceBal = $balances[$fromMethod]['balance'] ?? '0.000';
            $totalRequired = bcadd($amount, $fee, 3);

            if (bccomp($totalRequired, $sourceBal, 3) > 0) {
                $fromLabel = PaymentMethod::tryFrom($fromMethod)?->label() ?? $fromMethod;
                throw BusinessRuleException::make('treasury.transfer_insufficient_balance', [
                    'account' => $fromLabel,
                    'available' => Decimal::normalize($sourceBal),
                    'required' => Decimal::normalize($totalRequired),
                    'currency' => __('common.currency'),
                ]);
            }

            // Visible prefix TRF- like stock transfers, but its own counter (TTRF-) because both share the prefix.
            $transferNumber = $this->documentNumbers->nextDated('TRF', 4, TreasuryTransfer::class, 'transfer_number', 'TTRF');

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
                description: __('treasury.activity.transfer_created', [
                    'number' => $transferNumber,
                    'amount' => Decimal::normalize($amount),
                    'currency' => __('common.currency'),
                    'from' => $fromLabel,
                    'to' => $toLabel,
                ]),
                subject: $transfer,
                storeId: $storeId
            );

            return $transfer;
        });
    }

    /**
     * Serialisation point for treasury balance checks (see transfer()). Must run inside a transaction.
     */
    private function lockTreasuryScope(?int $storeId): void
    {
        Store::withTrashed()
            ->when($storeId !== null, fn ($q) => $q->where('id', $storeId))
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');
    }

    /**
     * Get a comprehensive report for all treasuries, accounts, and inter-account transfers over a date range.
     * Same method coverage rule as getBalances(): non-active methods appear only with any movement.
     */
    public function getTreasuryReport(string $fromDate, string $toDate, ?int $storeId = null, string $selectedMethod = 'all'): array
    {
        $activeKeys = array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::activeMethods());
        $accountSummaries = [];
        $totalOpeningBalance = '0.000';
        $totalPeriodInflows = '0.000';
        $totalPeriodOutflows = '0.000';
        $totalPeriodTransfersIn = '0.000';
        $totalPeriodTransfersOut = '0.000';
        $totalPeriodFees = '0.000';
        $totalCurrentBalance = '0.000';

        foreach (PaymentMethod::cases() as $methodEnum) {
            $key = $methodEnum->value;

            // 1. Opening Balance (All movements before $fromDate)
            $prior = $this->movements($key, $storeId, before: $fromDate);

            $priorTotalIn = bcadd($prior['customer_inflows'], $prior['transfers_in'], 3);
            $priorTotalOut = bcadd($prior['supplier_outflows'], bcadd($prior['general_expenses'], bcadd($prior['expense_payments'], bcadd($prior['transfers_out'], $prior['fees'], 3), 3), 3), 3);
            $openingBal = bcsub($priorTotalIn, $priorTotalOut, 3);

            // Add Opening cash shift balance if physical cash drawer
            if ($methodEnum->isPhysicalCash()) {
                $openingBal = bcadd($openingBal, $this->openShiftsOpeningCash($storeId), 3);
            }

            // 2. Period Movements ($fromDate to $toDate)
            $period = $this->movements($key, $storeId, onOrAfter: $fromDate, onOrBefore: $toDate);

            $periodInflows = $period['customer_inflows'];
            $periodInTransfers = $period['transfers_in'];
            $periodOutTransfers = $period['transfers_out'];
            $periodFees = $period['fees'];
            $periodOutflows = bcadd($period['supplier_outflows'], bcadd($period['general_expenses'], $period['expense_payments'], 3), 3);

            if (! in_array($key, $activeKeys, true)
                && $this->isZero($openingBal) && $this->isZero($periodInflows) && $this->isZero($periodOutflows)
                && $this->isZero($periodInTransfers) && $this->isZero($periodOutTransfers) && $this->isZero($periodFees)) {
                continue;
            }

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
            ->when($storeId, fn ($q) => $q->forStore($storeId))
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
            ->when($storeId, fn ($q) => $q->forStore($storeId))
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

    /**
     * Every movement of one payment method, as scale-3 strings, over an optional date window.
     * Payments are scoped to the branch with Payment::forStore (payments.store_id, falling back
     * to the invoice/purchase store for legacy rows); all branches when $storeId is null.
     *
     * PAY-EXP vouchers (treasury-paid purchase expenses) carry supplier_id, so they are already
     * in supplier_outflows (as DailyCashFlowService counts them: supplier_paid). The
     * expense_payments bucket therefore only holds PAY-EXP vouchers WITHOUT a supplier.
     *
     * @return array{customer_inflows: string, supplier_outflows: string, general_expenses: string, expense_payments: string, transfers_in: string, transfers_out: string, fees: string}
     */
    private function movements(string $method, ?int $storeId, ?string $onOrAfter = null, ?string $before = null, ?string $onOrBefore = null): array
    {
        $window = function ($q, string $column) use ($onOrAfter, $before, $onOrBefore) {
            return $q
                ->when($onOrAfter !== null, fn ($w) => $w->whereDate($column, '>=', $onOrAfter))
                ->when($before !== null, fn ($w) => $w->whereDate($column, '<', $before))
                ->when($onOrBefore !== null, fn ($w) => $w->whereDate($column, '<=', $onOrBefore));
        };

        $payments = fn () => $window(
            Payment::query()
                ->where('payments.payment_method', $method)
                ->when($storeId, fn ($q) => $q->forStore($storeId)),
            'payments.payment_date'
        );
        $transfers = fn () => $window(
            TreasuryTransfer::query()->when($storeId, fn ($q) => $q->where('store_id', $storeId)),
            'transfer_date'
        );

        return [
            'customer_inflows' => Decimal::normalize((string) $payments()->whereNotNull('payments.customer_id')->sum('payments.amount')),
            'supplier_outflows' => Decimal::normalize((string) $payments()->whereNotNull('payments.supplier_id')->sum('payments.amount')),
            'general_expenses' => Decimal::normalize((string) $window(
                Expense::query()->where('payment_method', $method)->when($storeId, fn ($q) => $q->where('store_id', $storeId)),
                'expense_date'
            )->sum('amount')),
            'expense_payments' => Decimal::normalize((string) $payments()
                ->where('payments.payment_number', 'like', 'PAY-EXP-%')
                ->whereNull('payments.supplier_id')
                ->sum('payments.amount')),
            'transfers_in' => Decimal::normalize((string) $transfers()->where('to_method', $method)->sum('amount')),
            'transfers_out' => Decimal::normalize((string) $transfers()->where('from_method', $method)->sum('amount')),
            'fees' => Decimal::normalize((string) $transfers()->where('from_method', $method)->sum('transfer_fee')),
        ];
    }

    /**
     * Opening cash of the open shift(s): the latest open shift of the branch, or - for the
     * all-branches view - the latest open shift of every branch.
     */
    private function openShiftsOpeningCash(?int $storeId): string
    {
        $shifts = CashShift::where('status', 'open')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->orderBy('id')
            ->get(['id', 'store_id', 'opening_cash_balance'])
            ->keyBy(fn (CashShift $s): string => (string) $s->store_id); // later (higher id) wins

        $total = '0.000';
        foreach ($shifts as $shift) {
            $total = bcadd($total, (string) $shift->opening_cash_balance, 3);
        }

        return $total;
    }

    private function isZero(string $value): bool
    {
        return bccomp($value, '0.000', 3) === 0;
    }
}
