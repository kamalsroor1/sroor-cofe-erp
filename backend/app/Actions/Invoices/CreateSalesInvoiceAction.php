<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\DTOs\Invoices\CreateInvoiceDTO;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\Pos\SaleShiftGuard;

final class CreateSalesInvoiceAction
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly SaleShiftGuard $saleShiftGuard,
    ) {}

    /**
     * Confirm Sales / POS invoice atomically
     */
    public function execute(CreateInvoiceDTO $dto): Invoice
    {
        // POS-10: a branch that requires a shift cannot sell without an open one.
        $this->saleShiftGuard->assertCanSell($dto->store_id, $dto->client_uuid);

        return $this->invoiceService->confirmInvoice($dto->toArray());
    }
}
