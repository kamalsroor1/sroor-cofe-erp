<?php

declare(strict_types=1);

namespace App\Actions\Suppliers;

use App\DTOs\Suppliers\PaySupplierDTO;
use App\Models\Supplier;
use App\Services\PaymentService;

final class PaySupplierAction
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    /**
     * Record payment to supplier
     */
    public function execute(PaySupplierDTO $dto): array
    {
        $supplier = Supplier::findOrFail($dto->supplier_id);

        $payment = $this->paymentService->recordSupplierPayment([
            'supplier_id'    => $supplier->id,
            'amount'         => $dto->amount,
            'payment_method' => $dto->payment_method,
            'payment_date'   => $dto->payment_date,
            'notes'          => $dto->notes ?? __('contacts.payment_voucher'),
        ]);

        return [
            'supplier' => $supplier->fresh(),
            'payment'  => $payment,
        ];
    }
}
