<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\TenantClock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected CustomerBalanceService $customerBalanceService,
        protected SupplierBalanceService $supplierBalanceService,
        protected AuditLogService $auditLogService,
        protected TenantClock $tenantClock,
    ) {}

    /**
     * The user a document is attributed to: the authenticated user, else the explicit
     * `user_id` a job/command passed in $data, else NULL ("system"). Never a made-up id.
     *
     * @param  array<string, mixed>  $data
     */
    private function actorId(array $data = []): ?int
    {
        $authId = Auth::id();
        if ($authId !== null) {
            return (int) $authId;
        }

        return isset($data['user_id']) && is_numeric($data['user_id']) ? (int) $data['user_id'] : null;
    }

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
                $newPaid = bcadd($invoice->paid_amount, $amount, 3);
                $newRemaining = bcsub($invoice->net_total, $newPaid, 3);

                if (bccomp($newRemaining, '0.000', 3) <= 0) {
                    $newRemaining = '0.000';
                    $newStatus = 'paid';
                } else {
                    $newStatus = 'partially_paid';
                }

                $invoice->update([
                    'paid_amount' => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'payment_status' => $newStatus,
                ]);
            }

            $payment = Payment::create([
                'payment_number' => $data['payment_number'] ?? 'PAY-CUST-'.strtoupper(uniqid()),
                'customer_id' => $customer->id,
                'supplier_id' => null,
                'invoice_id' => $invoiceId,
                'purchase_id' => null,
                'user_id' => $this->actorId($data),
                'amount' => $amount,
                'payment_date' => $data['payment_date'] ?? $this->tenantClock->businessDate(),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'notes' => $data['notes'] ?? 'سند قبض نقدي من العميل',
            ]);

            $this->customerBalanceService->updateBalance($customer->id);

            $this->auditLogService->log(
                action: 'customer_payment_recorded',
                auditable: $payment,
                oldValues: null,
                newValues: $payment->toArray(),
                actorId: $this->actorId($data),
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
                $newPaid = bcadd($purchase->paid_amount, $amount, 3);
                $newRemaining = bcsub($purchase->net_total, $newPaid, 3);

                if (bccomp($newRemaining, '0.000', 3) <= 0) {
                    $newRemaining = '0.000';
                    $newStatus = 'paid';
                } else {
                    $newStatus = 'partially_paid';
                }

                $purchase->update([
                    'paid_amount' => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'payment_status' => $newStatus,
                ]);
            }

            $payment = Payment::create([
                'payment_number' => $data['payment_number'] ?? 'PAY-SUPP-'.strtoupper(uniqid()),
                'customer_id' => null,
                'supplier_id' => $supplier->id,
                'invoice_id' => null,
                'purchase_id' => $purchaseId,
                'user_id' => $this->actorId($data),
                'amount' => $amount,
                'payment_date' => $data['payment_date'] ?? $this->tenantClock->businessDate(),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'notes' => $data['notes'] ?? 'سند صرف نقدي للمورد',
            ]);

            // Update supplier balance atomically
            $this->supplierBalanceService->updateBalance($supplier->id);

            $this->auditLogService->log(
                action: 'supplier_payment_recorded',
                auditable: $payment,
                oldValues: null,
                newValues: $payment->toArray(),
                actorId: $this->actorId($data),
            );

            return $payment;
        });
    }
}
