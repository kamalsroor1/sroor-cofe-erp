<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Store;
use App\Models\TreasuryTransfer;
use App\Services\Reports\ReportDateRange;
use App\Services\Settings\TenantSettings;
use App\Support\Database\TransactionRetry;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected ActivityLogService $activityLogService,
        protected TenantClock $tenantClock,
        protected DocumentNumberService $documentNumbers,
        protected ?TenantSettings $tenantSettings = null,
    ) {}

    private function currency(): string
    {
        return ($this->tenantSettings ?? app(TenantSettings::class))->currencyLabel();
    }

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

        // PERF-01: every method's sums in one grouped statement per source.
        $all = $this->movementWindows($storeId, null, $date)['from'];

        foreach (PaymentMethod::cases() as $methodEnum) {
            $methodKey = $methodEnum->value;

            $m = $all[$methodKey];

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

        // BUG-04: the day's TTRF counter row exists before the transaction takes its first read.
        $this->documentNumbers->prepareDated('TRF', TreasuryTransfer::class, 'transfer_number', 'TTRF');

        // BUG-07: restarted as a whole on a lock conflict; only plain input is captured.
        return TransactionRetry::run(function () use ($fromMethod, $toMethod, $amount, $fee, $storeId, $notes, $date, $actorId) {
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
                    'currency' => $this->currency(),
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
                    'currency' => $this->currency(),
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
    /**
     * PERF-01: the ledger is paged: `ledger_entries` is page $ledgerPage of $ledgerPerPage
     * entries, newest first (`transfers` = the latest $ledgerPerPage transfers); null = every
     * entry (the A4 print). Running balances are exact on every page: they are walked back from
     * the period's closing balance (opening + SQL sums of every entry). `ledger_total` is the
     * number of entries of the whole period.
     */
    public function getTreasuryReport(string $fromDate, string $toDate, ?int $storeId = null, string $selectedMethod = 'all', ?int $ledgerPerPage = null, int $ledgerPage = 1): array
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

        // PERF-01: every method's sums, before the period and in it, in one pass per source.
        $windows = $this->movementWindows($storeId, $fromDate, $toDate);
        $priorByMethod = $windows['before'];
        $periodByMethod = $windows['from'];

        foreach (PaymentMethod::cases() as $methodEnum) {
            $key = $methodEnum->value;

            // 1. Opening Balance (All movements before $fromDate)
            $prior = $priorByMethod[$key];

            $priorTotalIn = bcadd($prior['customer_inflows'], $prior['transfers_in'], 3);
            $priorTotalOut = bcadd($prior['supplier_outflows'], bcadd($prior['general_expenses'], bcadd($prior['expense_payments'], bcadd($prior['transfers_out'], $prior['fees'], 3), 3), 3), 3);
            $openingBal = bcsub($priorTotalIn, $priorTotalOut, 3);

            // Add Opening cash shift balance if physical cash drawer
            if ($methodEnum->isPhysicalCash()) {
                $openingBal = bcadd($openingBal, $this->openShiftsOpeningCash($storeId), 3);
            }

            // 2. Period Movements ($fromDate to $toDate)
            $period = $periodByMethod[$key];

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
            ->tap(fn ($q) => ReportDateRange::apply($q, 'transfer_date', $fromDate, $toDate))
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($selectedMethod !== 'all', function ($q) use ($selectedMethod) {
                $q->where(function ($sub) use ($selectedMethod) {
                    $sub->where('from_method', $selectedMethod)
                        ->orWhere('to_method', $selectedMethod);
                });
            })
            ->latest('id')
            ->when($ledgerPerPage !== null, fn ($q) => $q->limit((int) $ledgerPerPage))
            ->get();

        // 4. Build Chronological Ledger Entries
        ['entries' => $ledgerEntries, 'total' => $ledgerTotal] = $this->buildLedgerEntries($fromDate, $toDate, $storeId, $selectedMethod, $accountSummaries, $ledgerPerPage, $ledgerPage);

        return [
            'accounts' => $accountSummaries,
            'total_opening' => $totalOpeningBalance,
            'total_inflows' => $totalPeriodInflows,
            'total_outflows' => $totalPeriodOutflows,
            'total_transfers_in' => $totalPeriodTransfersIn,
            'total_transfers_out' => $totalPeriodTransfersOut,
            'total_fees' => $totalPeriodFees,
            // PERF-01: net change of all accounts over the period (closing - opening).
            'total_net_change' => bcsub($totalCurrentBalance, $totalOpeningBalance, 3),
            'total_liquidity' => $totalCurrentBalance,
            'transfers' => $transfers,
            'ledger_entries' => $ledgerEntries,
            'ledger_total' => $ledgerTotal,
        ];
    }

    /**
     * Build Chronological Running Ledger of Treasury Operations, one page, newest first.
     *
     * The five sources (customer receipts, supplier payments, expenses, transfers out, transfers
     * in) are one UNION ALL in SQL, ordered and paged there; only the page's documents are loaded
     * as models for their labels. Order (unchanged): created_at, then the source (customer
     * receipt, supplier payment, expense, transfer), then the id, transfer out before in;
     * reversed for "newest first".
     *
     * Running balance: the closing balance (the selected account's opening balance - the cash
     * drawer's for "all" - plus every entry of the period) minus every entry NEWER than the page,
     * then walked back through the page. Every page therefore shows the full ledger's figures.
     *
     * @param  array<string, array<string, mixed>>  $accountSummaries
     * @return array{entries: list<array<string, mixed>>, total: int}
     */
    protected function buildLedgerEntries(string $fromDate, string $toDate, ?int $storeId, string $selectedMethod, array $accountSummaries, ?int $perPage = null, int $page = 1): array
    {
        $method = $selectedMethod !== 'all' ? $selectedMethod : null;

        $customerPayments = Payment::query()
            ->excludingCancelledInvoices()
            ->whereNotNull('payments.customer_id')
            ->tap(fn ($q) => ReportDateRange::apply($q, 'payments.payment_date', $fromDate, $toDate))
            ->when($storeId, fn ($q) => $q->forStore($storeId))
            ->when($method !== null, fn ($q) => $q->where('payments.payment_method', $method))
            ->toBase()
            ->select([
                'payments.created_at as sort_at', DB::raw('0 as source_rank'), 'payments.id as entry_id', DB::raw('0 as sub_rank'),
                // BE-10: a refund voucher (negative amount) is cash going OUT to the customer.
                DB::raw('CASE WHEN payments.amount < 0 THEN 0 ELSE payments.amount END as debit'),
                DB::raw('CASE WHEN payments.amount < 0 THEN 0 - payments.amount ELSE 0 END as credit'),
            ]);
        $supplierPayments = Payment::query()
            ->whereNotNull('payments.supplier_id')
            ->tap(fn ($q) => ReportDateRange::apply($q, 'payments.payment_date', $fromDate, $toDate))
            ->when($storeId, fn ($q) => $q->forStore($storeId))
            ->when($method !== null, fn ($q) => $q->where('payments.payment_method', $method))
            ->toBase()
            ->select(['payments.created_at as sort_at', DB::raw('1 as source_rank'), 'payments.id as entry_id', DB::raw('0 as sub_rank'), DB::raw('0 as debit'), 'payments.amount as credit']);
        $expenses = Expense::query()
            ->tap(fn ($q) => ReportDateRange::apply($q, 'expenses.expense_date', $fromDate, $toDate))
            ->when($storeId, fn ($q) => $q->where('expenses.store_id', $storeId))
            ->when($method !== null, fn ($q) => $q->where('expenses.payment_method', $method))
            ->toBase()
            ->select(['expenses.created_at as sort_at', DB::raw('2 as source_rank'), 'expenses.id as entry_id', DB::raw('0 as sub_rank'), DB::raw('0 as debit'), 'expenses.amount as credit']);
        $transfersOut = TreasuryTransfer::query()
            ->tap(fn ($q) => ReportDateRange::apply($q, 'treasury_transfers.transfer_date', $fromDate, $toDate))
            ->when($storeId, fn ($q) => $q->where('treasury_transfers.store_id', $storeId))
            ->when($method !== null, fn ($q) => $q->where('treasury_transfers.from_method', $method))
            ->toBase()
            ->select([
                'treasury_transfers.created_at as sort_at', DB::raw('3 as source_rank'), 'treasury_transfers.id as entry_id', DB::raw('0 as sub_rank'),
                DB::raw('0 as debit'), DB::raw('treasury_transfers.amount + treasury_transfers.transfer_fee as credit'),
            ]);
        $transfersIn = TreasuryTransfer::query()
            ->tap(fn ($q) => ReportDateRange::apply($q, 'treasury_transfers.transfer_date', $fromDate, $toDate))
            ->when($storeId, fn ($q) => $q->where('treasury_transfers.store_id', $storeId))
            ->when($method !== null, fn ($q) => $q->where('treasury_transfers.to_method', $method))
            ->toBase()
            ->select(['treasury_transfers.created_at as sort_at', DB::raw('3 as source_rank'), 'treasury_transfers.id as entry_id', DB::raw('1 as sub_rank'), 'treasury_transfers.amount as debit', DB::raw('0 as credit')]);

        $union = $customerPayments->unionAll($supplierPayments)->unionAll($expenses)->unionAll($transfersOut)->unionAll($transfersIn);

        $totals = DB::query()->fromSub($union, 'ledger')
            ->selectRaw('COUNT(*) as entries_count')
            ->selectRaw('COALESCE(SUM(ledger.debit), 0) as debit_sum')
            ->selectRaw('COALESCE(SUM(ledger.credit), 0) as credit_sum')
            ->first();
        $total = (int) ($totals->entries_count ?? 0);
        $net = bcsub(Decimal::normalize((string) ($totals->debit_sum ?? '0')), Decimal::normalize((string) ($totals->credit_sum ?? '0')), 3);

        $newestFirst = fn () => DB::query()->fromSub($union, 'ledger')
            ->orderByDesc('ledger.sort_at')
            ->orderByDesc('ledger.source_rank')
            ->orderByDesc('ledger.entry_id')
            ->orderByDesc('ledger.sub_rank');

        $page = max(1, $page);
        $offset = $perPage !== null ? ($page - 1) * max(1, $perPage) : 0;

        $opening = ($method !== null && isset($accountSummaries[$method]))
            ? $accountSummaries[$method]['opening_balance']
            : ($accountSummaries['cash']['opening_balance'] ?? '0.000');

        // Balance after the newest entry of the page = closing - every newer entry.
        $balance = bcadd((string) $opening, $net, 3);
        if ($offset > 0) {
            $newer = DB::query()
                ->fromSub($newestFirst()->select(['ledger.debit', 'ledger.credit'])->limit($offset), 'newer')
                ->selectRaw('COALESCE(SUM(newer.debit), 0) as debit_sum')
                ->selectRaw('COALESCE(SUM(newer.credit), 0) as credit_sum')
                ->first();
            $balance = bcsub($balance, bcsub(Decimal::normalize((string) ($newer->debit_sum ?? '0')), Decimal::normalize((string) ($newer->credit_sum ?? '0')), 3), 3);
        }

        $pageRows = $newestFirst()
            ->select(['ledger.source_rank', 'ledger.entry_id', 'ledger.sub_rank'])
            ->when($perPage !== null, fn ($q) => $q->offset($offset)->limit(max(1, (int) $perPage)))
            ->get();

        // Only the page's documents, with their relations.
        $ids = [0 => [], 1 => [], 2 => [], 3 => []];
        foreach ($pageRows as $row) {
            $ids[(int) $row->source_rank][] = (int) $row->entry_id;
        }
        $payments = Payment::with(['customer', 'invoice', 'supplier', 'purchase'])->whereIn('id', array_merge($ids[0], $ids[1]))->get()->keyBy('id');
        $expenseRows = Expense::with(['store', 'user'])->whereIn('id', $ids[2])->get()->keyBy('id');
        $transferRows = TreasuryTransfer::with(['creator', 'store'])->whereIn('id', $ids[3])->get()->keyBy('id');

        $out = [];
        foreach ($pageRows as $row) {
            $id = (int) $row->entry_id;
            $entry = match ((int) $row->source_rank) {
                0 => $this->customerPaymentEntry($payments[$id]),
                1 => $this->supplierPaymentEntry($payments[$id]),
                2 => $this->expenseEntry($expenseRows[$id]),
                default => (int) $row->sub_rank === 0
                    ? $this->transferOutEntry($transferRows[$id])
                    : $this->transferInEntry($transferRows[$id]),
            };
            $entry['running_balance'] = $balance;
            $out[] = $entry;
            $balance = bcsub(bcsub($balance, $entry['debit'], 3), bcsub('0.000', $entry['credit'], 3), 3);
        }

        return ['entries' => $out, 'total' => $total];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerPaymentEntry(Payment $p): array
    {
        // BE-10: a refund voucher (negative amount) is cash going OUT to the customer.
        $isRefund = bccomp((string) $p->amount, '0.000', 3) < 0;

        return $this->ledgerRow($p->created_at, [
            'date' => $p->payment_date->format('Y-m-d'),
            'doc_number' => $p->payment_number,
            'type' => $isRefund ? 'outflow' : 'inflow',
            'type_label' => $isRefund ? __('treasury.customer_refund_label') : __('treasury.ledger.customer_receipt_type'),
            'method' => $p->payment_method,
            'method_label' => PaymentMethod::tryFrom($p->payment_method)?->shortLabel() ?? $p->payment_method,
            'party' => $p->customer?->name ?? __('treasury.ledger.cash_customer'),
            'notes' => $p->notes ?? __('treasury.ledger.customer_receipt_note'),
            'debit' => $isRefund ? '0.000' : (string) $p->amount,
            'credit' => $isRefund ? bcsub('0.000', (string) $p->amount, 3) : '0.000',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierPaymentEntry(Payment $sp): array
    {
        return $this->ledgerRow($sp->created_at, [
            'date' => $sp->payment_date->format('Y-m-d'),
            'doc_number' => $sp->payment_number,
            'type' => 'outflow',
            'type_label' => __('treasury.ledger.supplier_payment_type'),
            'method' => $sp->payment_method,
            'method_label' => PaymentMethod::tryFrom($sp->payment_method)?->shortLabel() ?? $sp->payment_method,
            'party' => $sp->supplier?->name ?? __('treasury.ledger.supplier'),
            'notes' => $sp->notes ?? __('treasury.ledger.supplier_payment_note'),
            'debit' => '0.000',
            'credit' => (string) $sp->amount,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function expenseEntry(Expense $e): array
    {
        return $this->ledgerRow($e->created_at, [
            'date' => $e->expense_date->format('Y-m-d'),
            'doc_number' => $e->expense_number,
            'type' => 'expense',
            'type_label' => __('treasury.ledger.expense_type'),
            'method' => $e->payment_method,
            'method_label' => PaymentMethod::tryFrom($e->payment_method)?->shortLabel() ?? $e->payment_method,
            'party' => $e->category ?: __('treasury.ledger.general_expenses'),
            'notes' => $e->title.($e->notes ? " ({$e->notes})" : ''),
            'debit' => '0.000',
            'credit' => (string) $e->amount,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transferOutEntry(TreasuryTransfer $tr): array
    {
        $fromLabel = PaymentMethod::tryFrom($tr->from_method)?->shortLabel() ?? $tr->from_method;
        $toLabel = PaymentMethod::tryFrom($tr->to_method)?->shortLabel() ?? $tr->to_method;
        $notes = $tr->notes ?: __('treasury.ledger.transfer_default_note');

        return $this->ledgerRow($tr->created_at, [
            'date' => $tr->transfer_date->format('Y-m-d'),
            'doc_number' => $tr->transfer_number,
            'type' => 'transfer_out',
            'type_label' => __('treasury.ledger.transfer_out_type', ['to' => $toLabel]),
            'method' => $tr->from_method,
            'method_label' => $fromLabel,
            'party' => __('treasury.ledger.transfer_to', ['to' => $toLabel]),
            'notes' => bccomp((string) $tr->transfer_fee, '0.000', 3) > 0
                ? __('treasury.ledger.transfer_fee_note', ['fee' => (string) $tr->transfer_fee, 'currency' => $this->currency(), 'notes' => $notes])
                : $notes,
            'debit' => '0.000',
            'credit' => bcadd((string) $tr->amount, (string) $tr->transfer_fee, 3),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transferInEntry(TreasuryTransfer $tr): array
    {
        $fromLabel = PaymentMethod::tryFrom($tr->from_method)?->shortLabel() ?? $tr->from_method;
        $toLabel = PaymentMethod::tryFrom($tr->to_method)?->shortLabel() ?? $tr->to_method;

        return $this->ledgerRow($tr->created_at, [
            'date' => $tr->transfer_date->format('Y-m-d'),
            'doc_number' => $tr->transfer_number,
            'type' => 'transfer_in',
            'type_label' => __('treasury.ledger.transfer_in_type', ['from' => $fromLabel]),
            'method' => $tr->to_method,
            'method_label' => $toLabel,
            'party' => __('treasury.ledger.transfer_from', ['from' => $fromLabel]),
            'notes' => $tr->notes ?: __('treasury.ledger.transfer_default_note'),
            'debit' => (string) $tr->amount,
            'credit' => '0.000',
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function ledgerRow(?\DateTimeInterface $createdAt, array $fields): array
    {
        return array_merge([
            'date' => $fields['date'],
            'time' => $createdAt?->format('H:i'),
            'timestamp' => $createdAt?->getTimestamp() ?? 0,
        ], $fields);
    }

    /**
     * Every movement of EVERY payment method, as scale-3 strings, keyed by method (every
     * PaymentMethod case is present, zero when it has no movement), split into two windows:
     * 'before' = dated before $splitAt, 'from' = dated on/after $splitAt (all of it when
     * $splitAt is null), both up to and including $onOrBefore when given.
     * Payments are scoped to the branch with Payment::forStore (payments.store_id, falling back
     * to the invoice/purchase store for legacy rows); all branches when $storeId is null.
     *
     * PAY-EXP vouchers (treasury-paid purchase expenses) carry supplier_id, so they are already
     * in supplier_outflows (as DailyCashFlowService counts them: supplier_paid). The
     * expense_payments bucket therefore only holds PAY-EXP vouchers WITHOUT a supplier.
     *
     * PERF-01: each source is read ONCE for both windows, grouped by method and window (5
     * statements instead of 7 per method and window), with half-open bounds on the date column
     * itself (same rows as whereDate(), index-friendly).
     *
     * @return array{before: array<string, array<string, string>>, from: array<string, array<string, string>>}
     */
    private function movementWindows(?int $storeId, ?string $splitAt, ?string $onOrBefore): array
    {
        $splitAt = $splitAt !== null && $splitAt !== '' ? CarbonImmutable::parse($splitAt)->toDateString() : null;

        $windowKey = fn (string $column): array => $splitAt === null
            ? ["'from'", []]
            : ["CASE WHEN {$column} < ? THEN 'before' ELSE 'from' END", [$splitAt]];

        /**
         * @return list<object> rows with window_key, method_key and the selected sums
         */
        $read = function ($query, string $dateColumn, string $methodColumn, array $sums) use ($windowKey, $onOrBefore): array {
            ReportDateRange::apply($query, $dateColumn, null, $onOrBefore);
            [$windowSql, $bindings] = $windowKey($dateColumn);
            $base = $query->toBase()
                ->selectRaw($windowSql.' as window_key', $bindings)
                ->selectRaw($methodColumn.' as method_key');
            foreach ($sums as $alias => $expression) {
                $base->selectRaw('COALESCE(SUM('.$expression.'), 0) as '.$alias);
            }

            return $base->groupBy('window_key', 'method_key')->get()->all();
        };

        $buckets = ['customer_inflows', 'supplier_outflows', 'general_expenses', 'expense_payments', 'transfers_in', 'transfers_out', 'fees'];
        $out = ['before' => [], 'from' => []];
        foreach (['before', 'from'] as $window) {
            foreach (PaymentMethod::cases() as $methodEnum) {
                $out[$window][$methodEnum->value] = array_fill_keys($buckets, '0.000');
            }
        }
        $add = function (array $rows, array $map) use (&$out): void {
            foreach ($rows as $row) {
                $window = (string) $row->window_key;
                $method = (string) $row->method_key;
                if (! isset($out[$window][$method])) {
                    continue; // a method outside PaymentMethod is not an account
                }
                foreach ($map as $alias => $bucket) {
                    $out[$window][$method][$bucket] = bcadd($out[$window][$method][$bucket], Decimal::normalize((string) $row->{$alias}), 3);
                }
            }
        };

        // The three payment buckets in ONE pass over the vouchers (conditional sums): the voucher
        // filter (cancelled-invoice exclusion, branch) is the expensive part.
        // PERF-02: a branch is read as its two disjoint halves (stamped store_id / legacy NULL
        // store_id rows), whose sums add up to exactly forStore(): the OR of both halves kept
        // MySQL off the store_id index and made it read every voucher of every branch.
        $branchHalves = $storeId
            ? [fn ($q) => $q->stampedForStore($storeId), fn ($q) => $q->legacyForStore($storeId)]
            : [fn ($q) => $q];
        foreach ($branchHalves as $half) {
            $add($read(
                $half(Payment::query()->excludingCancelledInvoices()),
                'payments.payment_date',
                'payments.payment_method',
                [
                    'customer_sum' => 'CASE WHEN payments.customer_id IS NOT NULL THEN payments.amount ELSE 0 END',
                    'supplier_sum' => 'CASE WHEN payments.supplier_id IS NOT NULL THEN payments.amount ELSE 0 END',
                    'expense_payment_sum' => "CASE WHEN payments.supplier_id IS NULL AND payments.payment_number LIKE 'PAY-EXP-%' THEN payments.amount ELSE 0 END",
                ],
            ), ['customer_sum' => 'customer_inflows', 'supplier_sum' => 'supplier_outflows', 'expense_payment_sum' => 'expense_payments']);
        }

        $add($read(
            Expense::query()->when($storeId, fn ($q) => $q->where('store_id', $storeId)),
            'expense_date',
            'payment_method',
            ['expense_sum' => 'amount'],
        ), ['expense_sum' => 'general_expenses']);

        $transfers = fn () => TreasuryTransfer::query()->when($storeId, fn ($q) => $q->where('store_id', $storeId));
        $add($read($transfers(), 'transfer_date', 'to_method', ['in_sum' => 'amount']), ['in_sum' => 'transfers_in']);
        $add($read($transfers(), 'transfer_date', 'from_method', ['out_sum' => 'amount', 'fee_sum' => 'transfer_fee']), ['out_sum' => 'transfers_out', 'fee_sum' => 'fees']);

        return $out;
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
