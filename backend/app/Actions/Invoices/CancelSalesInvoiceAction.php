<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\DTOs\Invoices\CancelInvoiceDTO;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\InvoiceCancelGuard;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;

final class CancelSalesInvoiceAction
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceCancelGuard $cancelGuard,
    ) {}

    /**
     * Cancel sales invoice, reverse stock and update customer balance
     */
    public function execute(CancelInvoiceDTO $dto, ?User $actor = null): Invoice
    {
        $invoice = Invoice::findOrFail($dto->invoice_id);

        $currentUser = $actor ?? (Auth::user() instanceof User ? Auth::user() : null);
        $approvedBy = $this->cancelGuard->validate($invoice, $currentUser, $dto->override);

        return $this->invoiceService->cancelInvoice($invoice, $dto->reason, $approvedBy);
    }
}
