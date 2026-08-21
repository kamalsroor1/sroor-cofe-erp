<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\DTOs\Invoices\CancelInvoiceDTO;
use App\Models\Invoice;
use App\Services\InvoiceService;

final class CancelSalesInvoiceAction
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    /**
     * Cancel sales invoice, reverse stock and update customer balance
     */
    public function execute(CancelInvoiceDTO $dto): Invoice
    {
        $invoice = Invoice::findOrFail($dto->invoice_id);

        return $this->invoiceService->cancelInvoice($invoice, $dto->reason);
    }
}
