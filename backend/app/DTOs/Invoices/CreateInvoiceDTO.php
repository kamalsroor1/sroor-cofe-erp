<?php

declare(strict_types=1);

namespace App\DTOs\Invoices;

final class CreateInvoiceDTO
{
    public function __construct(
        public readonly int $customer_id,
        public readonly array $items,
        public readonly ?int $store_id = null,
        public readonly string $invoice_date = '',
        public readonly string $payment_type = 'cash',
        public readonly string $payment_method = 'cash',
        public readonly string $discount_type = 'fixed',
        public readonly string $discount_value = '0.000',
        public readonly string $paid_amount = '0.000',
        public readonly ?string $notes = null,
        public readonly array $additional_expenses = [],
    ) {}

    public static function fromArray(array $data, ?int $storeId = null): self
    {
        return new self(
            customer_id: (int)$data['customer_id'],
            items: (array)($data['items'] ?? []),
            store_id: isset($data['store_id']) ? (int)$data['store_id'] : $storeId,
            invoice_date: (string)($data['invoice_date'] ?? now()->toDateString()),
            payment_type: (string)($data['payment_type'] ?? 'cash'),
            payment_method: (string)($data['payment_method'] ?? 'cash'),
            discount_type: (string)($data['discount_type'] ?? 'fixed'),
            discount_value: isset($data['discount_value']) ? (string)$data['discount_value'] : '0.000',
            paid_amount: isset($data['paid_amount']) ? (string)$data['paid_amount'] : '0.000',
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string)$data['notes'] : null,
            additional_expenses: (array)($data['additional_expenses'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'customer_id'         => $this->customer_id,
            'items'               => $this->items,
            'store_id'            => $this->store_id,
            'invoice_date'        => $this->invoice_date,
            'payment_type'        => $this->payment_type,
            'payment_method'      => $this->payment_method,
            'discount_type'       => $this->discount_type,
            'discount_value'      => $this->discount_value,
            'paid_amount'         => $this->paid_amount,
            'notes'               => $this->notes,
            'additional_expenses' => $this->additional_expenses,
        ];
    }
}
