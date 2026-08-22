<?php

declare(strict_types=1);

namespace App\DTOs\Purchases;

final class PurchaseDTO
{
    public function __construct(
        public readonly int $supplier_id,
        public readonly string $purchase_date,
        public readonly array $items,
        public readonly string $paid_amount = '0.000',
        public readonly string $discount_amount = '0.000',
        public readonly ?string $payment_method = 'cash',
        public readonly ?string $supplier_invoice_ref = null,
        public readonly ?string $notes = null,
        public readonly ?int $store_id = null,
        public readonly array $additional_expenses = [],
    ) {}

    public static function fromArray(array $data, ?int $storeId = null): self
    {
        return new self(
            supplier_id: (int)$data['supplier_id'],
            purchase_date: (string)($data['purchase_date'] ?? now()->toDateString()),
            items: (array)($data['items'] ?? []),
            paid_amount: isset($data['paid_amount']) ? (string)$data['paid_amount'] : '0.000',
            discount_amount: isset($data['discount_amount']) ? (string)$data['discount_amount'] : '0.000',
            payment_method: (string)($data['payment_method'] ?? 'cash'),
            supplier_invoice_ref: isset($data['supplier_invoice_ref']) && $data['supplier_invoice_ref'] !== '' ? (string)$data['supplier_invoice_ref'] : null,
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string)$data['notes'] : null,
            store_id: isset($data['store_id']) ? (int)$data['store_id'] : $storeId,
            additional_expenses: (array)($data['additional_expenses'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'supplier_id'          => $this->supplier_id,
            'purchase_date'        => $this->purchase_date,
            'items'                => $this->items,
            'paid_amount'          => $this->paid_amount,
            'discount_amount'      => $this->discount_amount,
            'payment_method'       => $this->payment_method,
            'supplier_invoice_ref' => $this->supplier_invoice_ref,
            'notes'                => $this->notes,
            'store_id'             => $this->store_id,
            'additional_expenses'  => $this->additional_expenses,
        ];
    }
}
