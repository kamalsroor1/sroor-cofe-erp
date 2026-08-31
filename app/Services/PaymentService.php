<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class PaymentService
{
    public function __construct(
        protected CustomerBalanceService $customerBalanceService,
        protected SupplierBalanceService $supplierBalanceService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Record a payment voucher from a customer (for an invoice or on account)
     */
    public function recordCustomerPayment(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::where('id', $data['customer_id'])->lockForUpdate()->firstOrFail();
            $amount = $data['amount'];

            $invoiceId = $data['invoice_id'] ?? null;
            if ($invoiceId) {
                $invoice = Invoice::where('id', $invoiceId)->lockForUpdate()->firstOrFail();
                $newPaid = bcadd((string)$invoice->paid_amount, (string)$amount, 3);
                $newRemaining = bcsub((string)$invoice->net_total, $newPaid, 3);

                if (bccomp($newRemaining, '0.000', 3) <= 0) {
                    $newRemaining = '0.000';
                    $newStatus = 'paid';
                } else {
                    $newStatus = 'partially_paid';
                }

                $invoice->update([
                    'paid_amount'      => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'payment_status'   => $newStatus,
                ]);
            } else {
                // 🔄 FIFO Auto-Settlement across customer's unpaid confirmed invoices
                $unpaidInvoices = Invoice::where('customer_id', $customer->id)
                    ->where('status', 'confirmed')
                    ->where('remaining_amount', '>', 0)
                    ->orderBy('invoice_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                $remainingToAllocate = (string)$amount;
                foreach ($unpaidInvoices as $inv) {
                    if (bccomp($remainingToAllocate, '0.000', 3) <= 0) {
                        break;
                    }

                    $due = (string)$inv->remaining_amount;
                    if (bccomp($remainingToAllocate, $due, 3) >= 0) {
                        $inv->update([
                            'paid_amount'      => $inv->net_total,
                            'remaining_amount' => '0.000',
                            'payment_status'   => 'paid',
                        ]);
                        $remainingToAllocate = bcsub($remainingToAllocate, $due, 3);
                    } else {
                        $newPaid = bcadd((string)$inv->paid_amount, $remainingToAllocate, 3);
                        $newRem  = bcsub((string)$inv->net_total, $newPaid, 3);
                        $inv->update([
                            'paid_amount'      => $newPaid,
                            'remaining_amount' => $newRem,
                            'payment_status'   => 'partially_paid',
                        ]);
                        $remainingToAllocate = '0.000';
                    }
                }
            }

            $payment = Payment::create([
                'payment_number' => $data['payment_number'] ?? 'PAY-CUST-' . strtoupper(uniqid()),
                'customer_id'    => $customer->id,
                'supplier_id'    => null,
                'invoice_id'     => $invoiceId,
                'purchase_id'    => null,
                'user_id'        => Auth::id() ?? 1,
                'amount'         => $amount,
                'payment_date'   => $data['payment_date'] ?? now()->toDateString(),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'notes'          => $data['notes'] ?? 'سند قبض نقدي من العميل',
            ]);

            $this->customerBalanceService->updateBalance($customer->id);

            $this->auditLogService->log(
                action: 'customer_payment_recorded',
                auditable: $payment,
                oldValues: null,
                newValues: $payment->toArray()
            );

            return $payment;
        });
    }

    /**
     * Record a payment to a supplier
     */
    public function recordSupplierPayment(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $supplier = Supplier::where('id', $data['supplier_id'])->lockForUpdate()->firstOrFail();
            $amount = $data['amount'];

            $purchaseId = $data['purchase_id'] ?? null;
            if ($purchaseId) {
                $purchase = Purchase::where('id', $purchaseId)->lockForUpdate()->firstOrFail();
                $newPaid = bcadd((string)$purchase->paid_amount, (string)$amount, 3);
                $newRemaining = bcsub((string)$purchase->net_total, $newPaid, 3);

                if (bccomp($newRemaining, '0.000', 3) <= 0) {
                    $newRemaining = '0.000';
                    $newStatus = 'paid';
                } else {
                    $newStatus = 'partially_paid';
                }

                $purchase->update([
                    'paid_amount'      => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'payment_status'   => $newStatus,
                ]);
            } else {
                // 🔄 FIFO Auto-Settlement across supplier's unpaid confirmed purchases
                $unpaidPurchases = Purchase::where('supplier_id', $supplier->id)
                    ->where('status', 'confirmed')
                    ->where('remaining_amount', '>', 0)
                    ->orderBy('purchase_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                $remainingToAllocate = (string)$amount;
                foreach ($unpaidPurchases as $pur) {
                    if (bccomp($remainingToAllocate, '0.000', 3) <= 0) {
                        break;
                    }

                    $due = (string)$pur->remaining_amount;
                    if (bccomp($remainingToAllocate, $due, 3) >= 0) {
                        $pur->update([
                            'paid_amount'      => $pur->net_total,
                            'remaining_amount' => '0.000',
                            'payment_status'   => 'paid',
                        ]);
                        $remainingToAllocate = bcsub($remainingToAllocate, $due, 3);
                    } else {
                        $newPaid = bcadd((string)$pur->paid_amount, $remainingToAllocate, 3);
                        $newRem  = bcsub((string)$pur->net_total, $newPaid, 3);
                        $pur->update([
                            'paid_amount'      => $newPaid,
                            'remaining_amount' => $newRem,
                            'payment_status'   => 'partially_paid',
                        ]);
                        $remainingToAllocate = '0.000';
                    }
                }
            }

            $payment = Payment::create([
                'payment_number' => $data['payment_number'] ?? 'PAY-SUPP-' . strtoupper(uniqid()),
                'customer_id'    => null,
                'supplier_id'    => $supplier->id,
                'invoice_id'     => null,
                'purchase_id'    => $purchaseId,
                'user_id'        => Auth::id() ?? 1,
                'amount'         => $amount,
                'payment_date'   => $data['payment_date'] ?? now()->toDateString(),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'notes'          => $data['notes'] ?? 'سند صرف نقدي للمورد',
            ]);

            // Update supplier balance atomically
            $this->supplierBalanceService->updateBalance($supplier->id);

            $this->auditLogService->log(
                action: 'supplier_payment_recorded',
                auditable: $payment,
                oldValues: null,
                newValues: $payment->toArray()
            );

            return $payment;
        });
    }
}
