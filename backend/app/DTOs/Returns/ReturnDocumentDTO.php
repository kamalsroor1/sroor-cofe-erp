<?php

declare(strict_types=1);

namespace App\DTOs\Returns;

final class ReturnDocumentDTO
{
    public function __construct(
        public readonly string $return_type,
        public readonly array $items,
        public readonly ?int $customer_id = null,
        public readonly ?int $supplier_id = null,
        public readonly ?int $invoice_id = null,
        public readonly ?int $purchase_id = null,
        public readonly ?int $store_id = null,
        public readonly string $return_date = '',
        public readonly string $refund_amount = '0.000',
        public readonly ?string $reason = null,
    ) {}

    public static function fromArray(array $data, ?int $storeId = null): self
    {
        return new self(
            return_type: (string)$data['return_type'],
            items: (array)($data['items'] ?? []),
            customer_id: isset($data['customer_id']) && $data['customer_id'] !== '' ? (int)$data['customer_id'] : null,
            supplier_id: isset($data['supplier_id']) && $data['supplier_id'] !== '' ? (int)$data['supplier_id'] : null,
            invoice_id: isset($data['invoice_id']) && $data['invoice_id'] !== '' ? (int)$data['invoice_id'] : null,
            purchase_id: isset($data['purchase_id']) && $data['purchase_id'] !== '' ? (int)$data['purchase_id'] : null,
            store_id: isset($data['store_id']) ? (int)$data['store_id'] : $storeId,
            return_date: (string)($data['return_date'] ?? now()->toDateString()),
            refund_amount: isset($data['refund_amount']) ? (string)$data['refund_amount'] : '0.000',
            reason: isset($data['reason']) && $data['reason'] !== '' ? (string)$data['reason'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'return_type'   => $this->return_type,
            'items'         => $this->items,
            'customer_id'   => $this->customer_id,
            'supplier_id'   => $this->supplier_id,
            'invoice_id'    => $this->invoice_id,
            'purchase_id'   => $this->purchase_id,
            'store_id'      => $this->store_id,
            'return_date'   => $this->return_date,
            'refund_amount' => $this->refund_amount,
            'reason'        => $this->reason,
        ];
    }
}
