<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\DTOs\Customers\CollectCustomerPaymentDTO;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\PaymentService;

final class CollectCustomerPaymentAction
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    /**
     * Collect and record customer payment receipt
     */
    public function execute(CollectCustomerPaymentDTO $dto): array
    {
        $customer = Customer::findOrFail($dto->customer_id);

        $payment = $this->paymentService->recordCustomerPayment([
            'customer_id'    => $customer->id,
            'amount'         => $dto->amount,
            'payment_method' => $dto->payment_method,
            'payment_date'   => $dto->payment_date,
            'notes'          => $dto->notes ?? __('contacts.receipt_voucher'),
        ]);

        return [
            'customer' => $customer->fresh(),
            'payment'  => $payment,
        ];
    }
}
