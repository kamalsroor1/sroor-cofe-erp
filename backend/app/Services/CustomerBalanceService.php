<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReturnDocument;

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
        $totalPayments = Payment::where('customer_id', $customerId)
            ->sum('amount');

        // Total Sales Returns
        $totalReturns = ReturnDocument::where('customer_id', $customerId)
            ->where('return_type', 'sales_return')
            ->sum('total_amount');

        $invoicesStr = (string) ($totalInvoices ?: '0.000');
        $paymentsStr = (string) ($totalPayments ?: '0.000');
        $returnsStr = (string) ($totalReturns ?: '0.000');

        $balance = bcsub(bcsub($invoicesStr, $paymentsStr, 3), $returnsStr, 3);

        $customer->current_balance = $balance;
        $customer->save();

        return $balance;
    }

    /**
     * Get Customer Account Statement (Ledger)
     */
    public function getCustomerLedger(Customer $customer, ?string $fromDate = null, ?string $toDate = null): array
    {
        $entries = collect();

        // Invoices
        $invoices = Invoice::where('customer_id', $customer->id)
            ->where('status', 'confirmed')
            ->when($fromDate, fn ($q) => $q->whereDate('invoice_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('invoice_date', '<=', $toDate))
            ->get();

        foreach ($invoices as $inv) {
            $entries->push([
                'date' => $inv->invoice_date->format('Y-m-d'),
                'type' => 'فاتورة مبيعات',
                'ref_number' => $inv->invoice_number,
                'debit' => $inv->net_total,    // مدين (على العميل)
                'credit' => '0.000',
                'notes' => $inv->notes,
                'timestamp' => $inv->created_at->timestamp,
            ]);
        }

        // Payments
        $payments = Payment::where('customer_id', $customer->id)
            ->when($fromDate, fn ($q) => $q->whereDate('payment_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('payment_date', '<=', $toDate))
            ->get();

        foreach ($payments as $pay) {
            $entries->push([
                'date' => $pay->payment_date->format('Y-m-d'),
                'type' => 'سند قبض نقدي',
                'ref_number' => $pay->payment_number,
                'debit' => '0.000',
                'credit' => $pay->amount,       // دائن (سداد من العميل)
                'notes' => $pay->notes,
                'timestamp' => $pay->created_at->timestamp,
            ]);
        }

        // Returns
        $returns = ReturnDocument::where('customer_id', $customer->id)
            ->where('return_type', 'sales_return')
            ->when($fromDate, fn ($q) => $q->whereDate('return_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('return_date', '<=', $toDate))
            ->get();

        foreach ($returns as $ret) {
            $entries->push([
                'date' => $ret->return_date->format('Y-m-d'),
                'type' => 'مرتجع مبيعات',
                'ref_number' => $ret->return_number,
                'debit' => '0.000',
                'credit' => $ret->total_amount, // دائن (تخفيض دين)
                'notes' => $ret->reason,
                'timestamp' => $ret->created_at->timestamp,
            ]);
        }

        // Sort chronologically and compute running balance
        $sorted = $entries->sortBy('timestamp')->values();
        $runningBalance = '0.000';
        $ledger = [];

        foreach ($sorted as $row) {
            // Running Balance = Balance + Debit - Credit
            $runningBalance = bcadd($runningBalance, $row['debit'], 3);
            $runningBalance = bcsub($runningBalance, $row['credit'], 3);

            $ledger[] = array_merge($row, ['balance_after' => $runningBalance]);
        }

        return [
            'customer' => $customer,
            'entries' => $ledger,
            'current_balance' => $customer->current_balance,
        ];
    }
}
