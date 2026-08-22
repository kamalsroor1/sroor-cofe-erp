<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\DTOs\Invoices\CreateInvoiceDTO;
use App\Models\Invoice;
use App\Services\InvoiceService;

final class CreateSalesInvoiceAction
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    /**
     * Confirm Sales / POS invoice atomically
     */
    public function execute(CreateInvoiceDTO $dto): Invoice
    {
        return $this->invoiceService->confirmInvoice($dto->toArray());
    }
}
