<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use App\Models\Supplier;
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
        $sorted = $entries->sortBy('timestamp')->values();

        $runningBalance = '0.000';
        $totalDebit = '0.000';
        $totalCredit = '0.000';

        $ledger = $sorted->map(function ($entry) use (&$runningBalance, &$totalDebit, &$totalCredit) {
            $totalDebit = bcadd($totalDebit, (string) $entry['debit'], 3);
            $totalCredit = bcadd($totalCredit, (string) $entry['credit'], 3);

            // Running Balance = Previous + Credit - Debit
            $runningBalance = bcsub(bcadd($runningBalance, (string) $entry['credit'], 3), (string) $entry['debit'], 3);

            return array_merge($entry, [
                'balance_after' => (float) $runningBalance,
                'debit' => (float) $entry['debit'],
                'credit' => (float) $entry['credit'],
            ]);
        })->toArray();

        return [
            'ledger' => $ledger,
            'summary' => [
                'total_purchases' => (float) $totalCredit,
                'total_payments' => (float) $totalDebit,
                'current_balance' => (float) $supplier->current_balance,
            ],
        ];
    }
}
