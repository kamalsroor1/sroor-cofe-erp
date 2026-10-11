<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ActiveStore;
use App\Support\TenantClock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
     * BUG-01: the branch whose drawer handled the cash: an explicit store_id, else the
     * active store of the request (X-Store-Id, via the session), else the store of the
     * linked invoice / purchase. Shift and daily-journal cash totals are per store.
     *
     * @param  array<string, mixed>  $data
     */
    private function drawerStoreId(array $data, mixed $documentStoreId): ?int
    {
        $user = Auth::user();
        $activeStoreId = $user instanceof User ? $user->getCurrentStore()?->id : null;
        $storeId = $data['store_id'] ?? $activeStoreId ?? $documentStoreId;

        return is_numeric($storeId) ? (int) $storeId : null;
    }

    /**
     * SEC-1 (M4): a document of a branch the authenticated user may not read is "not found"
     * (404, CTO Q13), so a payment can never settle another branch's invoice or purchase.
     * Without an authenticated user (jobs, commands) the caller already chose the document.
     *
     * @param  class-string<Invoice|Purchase>  $model
     */
    private function assertDocumentReadable(mixed $storeId, string $model, int $id): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        if (! ActiveStore::canRead($user, is_numeric($storeId) ? (int) $storeId : null)) {
            throw (new ModelNotFoundException)->setModel($model, [$id]);
        }
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
            $documentStoreId = null;
            if ($invoiceId) {
                $invoice = Invoice::where('id', $invoiceId)->lockForUpdate()->firstOrFail();
                // SEC-1 (M4): before anything changes: the invoice is readable by the actor
                // (else 404) and belongs to this customer (else 422).
                $this->assertDocumentReadable($invoice->store_id, Invoice::class, (int) $invoice->id);
                if ((int) $invoice->customer_id !== (int) $customer->id) {
                    throw ValidationException::withMessages([
                        'invoice_id' => __('contacts.payment_invoice_customer_mismatch'),
                    ]);
                }
                $documentStoreId = $invoice->store_id;
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
                'store_id' => $this->drawerStoreId($data, $documentStoreId),
                'user_id' => $this->actorId($data),
                'amount' => $amount,
                'payment_date' => $data['payment_date'] ?? $this->tenantClock->businessDate(),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'notes' => $data['notes'] ?? __('contacts.default_receipt_note'),
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
            $documentStoreId = null;
            if ($purchaseId) {
                $purchase = Purchase::where('id', $purchaseId)->lockForUpdate()->firstOrFail();
                // SEC-1 (M4): before anything changes: the purchase is readable by the actor
                // (else 404) and belongs to this supplier (else 422).
                $this->assertDocumentReadable($purchase->store_id, Purchase::class, (int) $purchase->id);
                if ((int) $purchase->supplier_id !== (int) $supplier->id) {
                    throw ValidationException::withMessages([
                        'purchase_id' => __('contacts.payment_purchase_supplier_mismatch'),
                    ]);
                }
                $documentStoreId = $purchase->store_id;
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
                'store_id' => $this->drawerStoreId($data, $documentStoreId),
                'user_id' => $this->actorId($data),
                'amount' => $amount,
                'payment_date' => $data['payment_date'] ?? $this->tenantClock->businessDate(),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'notes' => $data['notes'] ?? __('contacts.default_disbursement_note'),
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
