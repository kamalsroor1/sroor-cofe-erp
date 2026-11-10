<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\DocumentNumberService;
use App\Support\TenantClock;
use Illuminate\Support\Facades\Auth;

/**
 * BE-03: cancelling an invoice gives back what was paid on it. For every live positive voucher
 * linked to the invoice (the sale's PAY-INV lines and any later receipt against it) that has no
 * reversal yet, books one negative PAY-REF voucher of the same method, customer and store, dated
 * the business date and pointing back through reverses_payment_id. Money leaves the same
 * treasury account it entered (cash from the drawer, visa from visa...), in the cancelling shift
 * and day, while the original sale keeps showing in its own shift and day.
 *
 * Call inside the cancel transaction, with the invoice row and its customer already locked and
 * before the customer balance is recomputed (lock order: customer, items, number sequences).
 */
final class ReverseInvoicePaymentsAction
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly TenantClock $tenantClock,
    ) {}

    public function execute(Invoice $lockedInvoice): void
    {
        $originals = Payment::query()
            ->where('invoice_id', $lockedInvoice->id)
            ->where('amount', '>', 0)
            ->excludingRefunds()
            ->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('payments as reversal')
                    ->whereColumn('reversal.reverses_payment_id', 'payments.id')
                    ->whereNull('reversal.deleted_at');
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $actorId = Auth::id();

        foreach ($originals as $original) {
            Payment::create([
                'payment_number' => $this->documentNumbers->nextDated('PAY-REF', 6, Payment::class, 'payment_number'),
                'customer_id' => $original->customer_id,
                'supplier_id' => null,
                'invoice_id' => $lockedInvoice->id,
                'purchase_id' => null,
                'store_id' => $original->store_id ?? $lockedInvoice->store_id,
                'return_id' => null,
                'reverses_payment_id' => $original->id,
                'user_id' => $actorId !== null ? (int) $actorId : null,
                'amount' => bcsub('0.000', (string) $original->amount, 3),
                // The money leaves today, whatever day the sale was made.
                'payment_date' => $this->tenantClock->businessDate(),
                'payment_method' => $original->payment_method,
                'notes' => __('invoices.cancel_refund_voucher_note', [
                    'number' => $lockedInvoice->invoice_number,
                    'voucher' => $original->payment_number,
                ]),
            ]);
        }
    }
}
