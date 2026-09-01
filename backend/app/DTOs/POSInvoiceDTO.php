<?php

namespace App\DTOs;

class POSInvoiceDTO
{
    /**
     * @param POSInvoiceItemDTO[] $items
     */
    public function __construct(
        public readonly int $customerId,
        public readonly int $storeId,
        public readonly string $invoiceDate,
        public readonly string $paymentType,
        public readonly string $paymentMethod,
        public readonly string $discountType,
        public readonly float $discountValue,
        public readonly float $paidAmount,
        public readonly ?string $notes,
        public readonly array $items,
        public readonly array $additionalExpenses = [],
        public readonly ?array $payments = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn($item) => POSInvoiceItemDTO::fromArray($item),
            $data['items'] ?? []
        );

        return new self(
            customerId: (int)$data['customer_id'],
            storeId: (int)$data['store_id'],
            invoiceDate: $data['invoice_date'] ?? now()->toDateString(),
            paymentType: $data['payment_type'] ?? 'cash',
            paymentMethod: $data['payment_method'] ?? 'cash',
            discountType: $data['discount_type'] ?? 'fixed',
            discountValue: (float)($data['discount_value'] ?? 0),
            paidAmount: (float)($data['paid_amount'] ?? 0),
            notes: $data['notes'] ?? null,
            items: $items,
            additionalExpenses: $data['additional_expenses'] ?? $data['expenses'] ?? [],
            payments: $data['payments'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'customer_id' => $this->customerId,
            'store_id' => $this->storeId,
            'invoice_date' => $this->invoiceDate,
            'payment_type' => $this->paymentType,
            'payment_method' => $this->paymentMethod,
            'discount_type' => $this->discountType,
            'discount_value' => (string)$this->discountValue,
            'paid_amount' => (string)$this->paidAmount,
            'notes' => $this->notes,
            'items' => array_map(fn($item) => $item->toArray(), $this->items),
            'additional_expenses' => $this->additionalExpenses,
            'payments' => $this->payments,
        ];
    }
}
