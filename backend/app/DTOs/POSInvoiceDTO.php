<?php

namespace App\DTOs;

class POSInvoiceDTO
{
    /**
     * @param  POSInvoiceItemDTO[]  $items
     */
    public function __construct(
        public readonly int $customerId,
        public readonly int $storeId,
        public readonly string $invoiceDate,
        public readonly string $paymentType,
        public readonly string $paymentMethod,
        public readonly string $discountType,
        public readonly string $discountValue,
        public readonly string $paidAmount,
        public readonly ?string $notes,
        public readonly array $items,
        public readonly array $additionalExpenses = [],
        public readonly ?array $payments = null,
        public readonly ?string $clientUuid = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn ($item) => POSInvoiceItemDTO::fromArray($item),
            $data['items'] ?? []
        );

        return new self(
            customerId: (int) $data['customer_id'],
            storeId: (int) $data['store_id'],
            invoiceDate: $data['invoice_date'] ?? now()->toDateString(),
            paymentType: $data['payment_type'] ?? 'cash',
            paymentMethod: $data['payment_method'] ?? 'cash',
            discountType: $data['discount_type'] ?? 'fixed',
            discountValue: (string) ($data['discount_value'] ?? '0.000'),
            paidAmount: (string) ($data['paid_amount'] ?? '0.000'),
            notes: $data['notes'] ?? null,
            items: $items,
            additionalExpenses: $data['additional_expenses'] ?? $data['expenses'] ?? [],
            payments: $data['payments'] ?? null,
            clientUuid: ! empty($data['client_uuid']) ? strtolower((string) $data['client_uuid']) : null,
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
            'discount_value' => $this->discountValue,
            'paid_amount' => $this->paidAmount,
            'notes' => $this->notes,
            'items' => array_map(fn ($item) => $item->toArray(), $this->items),
            'additional_expenses' => $this->additionalExpenses,
            'payments' => $this->payments,
            'client_uuid' => $this->clientUuid,
        ];
    }
}
