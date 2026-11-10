<?php

namespace App\Actions\Invoices;

use App\DTOs\POSInvoiceDTO;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\Pos\SaleShiftGuard;

class ProcessPOSInvoiceAction
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected SaleShiftGuard $saleShiftGuard,
    ) {}

    public function execute(POSInvoiceDTO $dto): Invoice
    {
        // POS-10: a branch that requires a shift cannot sell without an open one.
        $this->saleShiftGuard->assertCanSell($dto->storeId, $dto->clientUuid);

        return $this->invoiceService->confirmInvoice($dto->toArray());
    }
}
