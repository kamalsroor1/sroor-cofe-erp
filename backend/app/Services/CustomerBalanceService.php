<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OpeningBalance;
use App\Models\Payment;
use App\Models\ReturnDocument;
use App\Services\Reports\InvoiceAggregates;
use App\Services\Reports\ReportDateRange;
use App\Support\Money\Decimal;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class CustomerBalanceService
{
    /**
     * Recalculate customer balance: Confirmed Invoices - Payments - Sales Returns
     */
    public function updateBalance(int $customerId): string
    {
        $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();

        // Confirmed Invoices Net Total
        $totalInvoices = Invoice::where('customer_id', $customerId)
            ->where('status', 'confirmed')
            ->sum('net_total');

        // Total Customer Payments
        // BE-03: payments of a legacy cancelled invoice (never reversed) are not customer credit.
        $totalPayments = Payment::where('customer_id', $customerId)
            ->excludingCancelledInvoices()
            ->sum('amount');

        // Total Sales Returns
        $totalReturns = ReturnDocument::where('customer_id', $customerId)
            ->where('return_type', 'sales_return')
            ->sum('total_amount');

        $invoicesStr = (string) ($totalInvoices ?: '0.000');
        $paymentsStr = (string) ($totalPayments ?: '0.000');
        $returnsStr = (string) ($totalReturns ?: '0.000');

        $balance = bcsub(bcsub($invoicesStr, $paymentsStr, 3), $returnsStr, 3);
        // PRD-06: imported opening balance (signed, positive = customer owes us).
        $balance = bcadd($balance, OpeningBalance::totalFor(OpeningBalance::PARTY_CUSTOMER, $customerId), 3);

        $customer->current_balance = $balance;
        $customer->save();

        return $balance;
    }

    /**
     * Customer account statement (ledger), one page at a time.
     *
     * PERF-01: the four sources (opening balances, confirmed invoices, receipts / refunds,
     * sales returns) are one UNION ALL in SQL, ordered and paged there; nothing is loaded and
     * sorted in PHP. Order (unchanged): opening-balance entries first, then created_at, then the
     * source (invoice, payment, return), then the id.
     *
     * Balances are exact on every page:
     * - opening_balance: the carried-forward balance of everything dated BEFORE $fromDate
     *   (0 without a $fromDate, i.e. the whole history is shown);
     * - each row's balance_after = opening_balance + every movement of the period up to and
     *   including that row (the rows before the page are summed in SQL);
     * - totals: debit / credit / count of the WHOLE period, not of the page.
     *
     * @return array{customer: Customer, entries: list<array<string, mixed>>, current_balance: mixed, opening_balance: string, closing_balance: string, total_debit: string, total_credit: string, total: int}
     */
    public function getCustomerLedger(Customer $customer, ?string $fromDate = null, ?string $toDate = null, int $page = 1, ?int $perPage = null): array
    {
        $fromDate = $fromDate !== null && $fromDate !== '' ? $fromDate : null;
        $toDate = $toDate !== null && $toDate !== '' ? $toDate : null;

        $window = $this->ledgerUnion($customer, fn ($q, string $column) => ReportDateRange::apply($q, $column, $fromDate, $toDate));

        $opening = '0.000';
        if ($fromDate !== null) {
            $before = $this->ledgerUnion($customer, fn ($q, string $column) => ReportDateRange::before($q, $column, $fromDate));
            $opening = $this->netOf(DB::query()->fromSub($before, 'ledger'));
        }

        $totalsRow = DB::query()->fromSub($window, 'ledger')
            ->selectRaw('COUNT(*) as entries_count')
            ->selectRaw('COALESCE(SUM(ledger.debit), 0) as debit_sum')
            ->selectRaw('COALESCE(SUM(ledger.credit), 0) as credit_sum')
            ->first();
        $total = (int) ($totalsRow->entries_count ?? 0);
        $totalDebit = Decimal::normalize(InvoiceAggregates::scalar($totalsRow->debit_sum ?? null));
        $totalCredit = Decimal::normalize(InvoiceAggregates::scalar($totalsRow->credit_sum ?? null));

        $page = max(1, $page);
        $perPage = $perPage === null ? max(1, $total) : max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $ordered = fn () => $this->ordered(DB::query()->fromSub($window, 'ledger'));

        // Running balance at the top of the page: opening + every row of the period before it.
        $runningBalance = $opening;
        if ($offset > 0) {
            $runningBalance = bcadd($runningBalance, $this->netOf(DB::query()->fromSub(
                $ordered()->select(['ledger.debit', 'ledger.credit'])->limit($offset),
                'ledger',
            )), 3);
        }

        $rows = $ordered()->select('ledger.*')->offset($offset)->limit($perPage)->get();

        $entries = [];
        foreach ($rows as $row) {
            $debit = Decimal::normalize(InvoiceAggregates::scalar($row->debit));
            $credit = Decimal::normalize(InvoiceAggregates::scalar($row->credit));
            // Running Balance = Balance + Debit - Credit
            $runningBalance = bcsub(bcadd($runningBalance, $debit, 3), $credit, 3);

            $entries[] = [
                'date' => substr((string) $row->entry_date, 0, 10),
                'type' => $this->ledgerTypeLabel((string) $row->kind, $credit, $debit),
                'ref_number' => $row->ref_number,
                'debit' => $debit,
                'credit' => $credit,
                'notes' => $row->notes,
                'balance_after' => $runningBalance,
            ];
        }

        return [
            'customer' => $customer,
            'entries' => $entries,
            'current_balance' => $customer->current_balance,
            'opening_balance' => $opening,
            'closing_balance' => bcsub(bcadd($opening, $totalDebit, 3), $totalCredit, 3),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'total' => $total,
        ];
    }

    /**
     * The four statement sources as one UNION ALL with the same columns:
     * sort_group (0 = opening balance), sort_at, source_rank, entry_id, entry_date, kind,
     * ref_number, notes, debit, credit. $window($query, $dateColumn) applies the date filter.
     */
    private function ledgerUnion(Customer $customer, \Closure $window): QueryBuilder
    {
        $openings = OpeningBalance::query()
            ->where('party_type', OpeningBalance::PARTY_CUSTOMER)
            ->where('party_id', $customer->id);
        $window($openings, 'balance_date');
        $openings = $openings->toBase()->select([
            DB::raw('0 as sort_group'),
            DB::raw('NULL as sort_at'),
            DB::raw('0 as source_rank'),
            'opening_balances.id as entry_id',
            'opening_balances.balance_date as entry_date',
            DB::raw("'opening' as kind"),
            DB::raw('NULL as ref_number'),
            'opening_balances.notes as notes',
            DB::raw('CASE WHEN opening_balances.amount >= 0 THEN opening_balances.amount ELSE 0 END as debit'),
            DB::raw('CASE WHEN opening_balances.amount >= 0 THEN 0 ELSE 0 - opening_balances.amount END as credit'),
        ]);

        $invoices = Invoice::query()
            ->where('invoices.customer_id', $customer->id)
            ->where('invoices.status', 'confirmed');
        $window($invoices, 'invoices.invoice_date');
        $invoices = $invoices->toBase()->select([
            DB::raw('1 as sort_group'),
            'invoices.created_at as sort_at',
            DB::raw('1 as source_rank'),
            'invoices.id as entry_id',
            'invoices.invoice_date as entry_date',
            DB::raw("'invoice' as kind"),
            'invoices.invoice_number as ref_number',
            'invoices.notes as notes',
            'invoices.net_total as debit',
            DB::raw('0 as credit'),
        ]);

        // BE-03: payments of a legacy cancelled invoice (never reversed) are not customer credit.
        $payments = Payment::query()
            ->where('payments.customer_id', $customer->id)
            ->excludingCancelledInvoices();
        $window($payments, 'payments.payment_date');
        $payments = $payments->toBase()->select([
            DB::raw('1 as sort_group'),
            'payments.created_at as sort_at',
            DB::raw('2 as source_rank'),
            'payments.id as entry_id',
            'payments.payment_date as entry_date',
            DB::raw("'payment' as kind"),
            'payments.payment_number as ref_number',
            'payments.notes as notes',
            // BE-10: a refund voucher (negative amount) is cash given back: a debit.
            DB::raw('CASE WHEN payments.amount < 0 THEN 0 - payments.amount ELSE 0 END as debit'),
            DB::raw('CASE WHEN payments.amount < 0 THEN 0 ELSE payments.amount END as credit'),
        ]);

        $returns = ReturnDocument::query()
            ->where('returns.customer_id', $customer->id)
            ->where('returns.return_type', 'sales_return');
        $window($returns, 'returns.return_date');
        $returns = $returns->toBase()->select([
            DB::raw('1 as sort_group'),
            'returns.created_at as sort_at',
            DB::raw('3 as source_rank'),
            'returns.id as entry_id',
            'returns.return_date as entry_date',
            DB::raw("'return' as kind"),
            'returns.return_number as ref_number',
            'returns.reason as notes',
            DB::raw('0 as debit'),
            'returns.total_amount as credit',
        ]);

        return $openings->unionAll($invoices)->unionAll($payments)->unionAll($returns);
    }

    private function ordered(QueryBuilder $query): QueryBuilder
    {
        return $query
            ->orderBy('ledger.sort_group')
            ->orderBy('ledger.sort_at')
            ->orderBy('ledger.source_rank')
            ->orderBy('ledger.entry_id');
    }

    /**
     * SUM(debit) - SUM(credit) of a ledger query, as a scale-3 string.
     */
    private function netOf(QueryBuilder $query): string
    {
        $row = $query
            ->selectRaw('COALESCE(SUM(ledger.debit), 0) as debit_sum')
            ->selectRaw('COALESCE(SUM(ledger.credit), 0) as credit_sum')
            ->first();

        return bcsub(
            Decimal::normalize(InvoiceAggregates::scalar($row->debit_sum ?? null)),
            Decimal::normalize(InvoiceAggregates::scalar($row->credit_sum ?? null)),
            3,
        );
    }

    private function ledgerTypeLabel(string $kind, string $credit, string $debit): string
    {
        return match ($kind) {
            'opening' => __('import.opening_balance_entry'),
            'invoice' => __('invoices.sales_invoice'),
            'payment' => bccomp($debit, '0.000', 3) > 0 && bccomp($credit, '0.000', 3) === 0
                ? __('treasury.customer_refund_label')
                : __('contacts.cash_receipt_voucher'),
            'return' => __('contacts.sales_return_entry'),
            default => $kind,
        };
    }
}
