<?php

namespace App\Services;

use App\Models\OpeningBalance;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use App\Models\Supplier;
use App\Support\Money\Decimal;
use Illuminate\Support\Facades\DB;

class SupplierBalanceService
{
    /**
     * Atomically recalculate and save the exact balance of a supplier.
     * Balance = (Total Confirmed Purchases) - (Total Supplier Payments) - (Total Purchase Returns)
     */
    public function updateBalance(int $supplierId): string
    {
        return DB::transaction(function () use ($supplierId) {
            $supplier = Supplier::where('id', $supplierId)->lockForUpdate()->firstOrFail();

            // 1. Total Confirmed Purchases
            $totalPurchases = Purchase::where('supplier_id', $supplier->id)
                ->where('status', 'confirmed')
                ->sum('net_total') ?: '0.000';

            // 2. Total Payments made to this supplier
            $totalPayments = Payment::where('supplier_id', $supplier->id)
                ->sum('amount') ?: '0.000';

            // 3. Total Purchase Returns
            $totalReturns = '0.000';
            if (class_exists(ReturnDocument::class)) {
                $totalReturns = ReturnDocument::where('supplier_id', $supplier->id)
                    ->where('return_type', 'purchase_return')
                    ->sum('total_amount') ?: '0.000';
            }

            // Running calculation: (Purchases - Payments) - Returns
            $balance = bcsub((string) $totalPurchases, (string) $totalPayments, 3);
            $finalBalance = bcsub($balance, (string) $totalReturns, 3);
            // PRD-06: imported opening balance (signed, positive = we owe the supplier).
            $finalBalance = bcadd($finalBalance, OpeningBalance::totalFor(OpeningBalance::PARTY_SUPPLIER, (int) $supplier->id), 3);

            $supplier->current_balance = $finalBalance;
            $supplier->save();

            return $finalBalance;
        });
    }

    /**
     * Generate chronological supplier account statement ledger
     */
    public function getSupplierLedger(Supplier $supplier, ?string $fromDate = null, ?string $toDate = null): array
    {
        $entries = collect();

        // PRD-06: opening balance entries come first on the statement.
        $openings = OpeningBalance::query()
            ->where('party_type', OpeningBalance::PARTY_SUPPLIER)
            ->where('party_id', $supplier->id)
            ->when($fromDate, fn ($q) => $q->whereDate('balance_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('balance_date', '<=', $toDate))
            ->get();

        foreach ($openings as $opening) {
            $amount = (string) $opening->amount;
            $isCredit = bccomp($amount, '0.000', 3) >= 0;
            $entries->push([
                'date' => $opening->balance_date->format('Y-m-d'),
                'type' => __('import.opening_balance_entry'),
                'ref_number' => null,
                'debit' => $isCredit ? '0.000' : ltrim($amount, '-'),
                'credit' => $isCredit ? $amount : '0.000',
                'notes' => $opening->notes,
                'timestamp' => 0,
            ]);
        }

        // 1. Confirmed Purchases (Credit - المستحقات للمورد)
        $purchases = Purchase::where('supplier_id', $supplier->id)
            ->where('status', 'confirmed')
            ->when($fromDate, fn ($q) => $q->whereDate('purchase_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('purchase_date', '<=', $toDate))
            ->get();

        foreach ($purchases as $pur) {
            $entries->push([
                'date' => $pur->purchase_date->format('Y-m-d'),
                'type' => 'فاتورة مشتريات وتوريد',
                'ref_number' => $pur->purchase_number,
                'debit' => '0.000',
                'credit' => $pur->net_total,    // دائن (مستحق للمورد)
                'notes' => $pur->notes ?? $pur->supplier_invoice_ref,
                'timestamp' => $pur->created_at->timestamp,
            ]);
        }

        // 2. Payments (Debit - المسدد للمورد)
        $payments = Payment::where('supplier_id', $supplier->id)
            ->when($fromDate, fn ($q) => $q->whereDate('payment_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('payment_date', '<=', $toDate))
            ->get();

        foreach ($payments as $pay) {
            $entries->push([
                'date' => $pay->payment_date ? $pay->payment_date->format('Y-m-d') : $pay->created_at->format('Y-m-d'),
                'type' => 'سند صرف وسداد نقدي',
                'ref_number' => $pay->payment_number,
                'debit' => $pay->amount,       // مدين (تخفيض مديونية المورد)
                'credit' => '0.000',
                'notes' => $pay->notes,
                'timestamp' => $pay->created_at->timestamp,
            ]);
        }

        // 3. Returns (Debit - مرتجع مشتريات يخفض مديونية المورد)
        if (class_exists(ReturnDocument::class)) {
            $returns = ReturnDocument::where('supplier_id', $supplier->id)
                ->where('return_type', 'purchase_return')
                ->when($fromDate, fn ($q) => $q->whereDate('return_date', '>=', $fromDate))
                ->when($toDate, fn ($q) => $q->whereDate('return_date', '<=', $toDate))
                ->get();

            foreach ($returns as $ret) {
                $entries->push([
                    'date' => $ret->return_date ? $ret->return_date->format('Y-m-d') : $ret->created_at->format('Y-m-d'),
                    'type' => 'مرتجع مشتريات',
                    'ref_number' => $ret->return_number,
                    'debit' => $ret->total_amount, // مدين (تخفيض مستحقات المورد)
                    'credit' => '0.000',
                    'notes' => $ret->reason,
                    'timestamp' => $ret->created_at->timestamp,
                ]);
            }
        }

        // Sort chronologically and compute running balance
        // BUG-08: by document date first, then by entry time.
        $sorted = $entries->sortBy([['date', 'asc'], ['timestamp', 'asc']])->values();

        // BUG-08: balance carried forward from everything dated before the period.
        $openingBalance = $fromDate ? $this->balanceBefore($supplier, $fromDate) : '0.000';
        $runningBalance = $openingBalance;
        $totalDebit = '0.000';
        $totalCredit = '0.000';

        $ledger = $sorted->map(function ($entry) use (&$runningBalance, &$totalDebit, &$totalCredit) {
            $totalDebit = bcadd($totalDebit, (string) $entry['debit'], 3);
            $totalCredit = bcadd($totalCredit, (string) $entry['credit'], 3);

            // Running Balance = Previous + Credit - Debit
            $runningBalance = bcsub(bcadd($runningBalance, (string) $entry['credit'], 3), (string) $entry['debit'], 3);

            return array_merge($entry, [
                'balance_after' => Decimal::normalize((string) $runningBalance),
                'debit' => Decimal::normalize((string) $entry['debit']),
                'credit' => Decimal::normalize((string) $entry['credit']),
            ]);
        })->toArray();

        return [
            'ledger' => $ledger,
            'opening_balance' => Decimal::normalize($openingBalance),
            'summary' => [
                'total_purchases' => Decimal::normalize((string) $totalCredit),
                'total_payments' => Decimal::normalize((string) $totalDebit),
                'current_balance' => Decimal::normalize((string) $supplier->current_balance),
            ],
        ];
    }

    /**
     * Net payable (credit - debit) of every supplier movement dated strictly before $date.
     */
    private function balanceBefore(Supplier $supplier, string $date): string
    {
        $day = \Carbon\CarbonImmutable::parse($date)->toDateString();

        $openings = (string) (OpeningBalance::query()
            ->where('party_type', OpeningBalance::PARTY_SUPPLIER)
            ->where('party_id', $supplier->id)
            ->where('balance_date', '<', $day)
            ->sum('amount') ?: '0.000');
        $purchases = (string) (Purchase::where('supplier_id', $supplier->id)
            ->where('status', 'confirmed')
            ->where('purchase_date', '<', $day)
            ->sum('net_total') ?: '0.000');
        $payments = (string) (Payment::where('supplier_id', $supplier->id)
            ->where('payment_date', '<', $day)
            ->sum('amount') ?: '0.000');
        $returns = (string) (ReturnDocument::where('supplier_id', $supplier->id)
            ->where('return_type', 'purchase_return')
            ->where('return_date', '<', $day)
            ->sum('total_amount') ?: '0.000');

        $balance = bcadd($openings, $purchases, 3);

        return bcsub(bcsub($balance, $payments, 3), $returns, 3);
    }
}
