<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'invoice_number'       => $this->invoice_number,
            'customer_id'          => $this->customer_id,
            'customer_name'        => $this->customer?->name ?? 'عميل نقدي سريع',
            'customer_phone'       => $this->customer?->phone,
            'customer_balance'     => $this->customer ? (float)$this->customer->balance : 0,
            'customer'             => [
                'id'      => $this->customer_id,
                'name'    => $this->customer?->name ?? 'عميل نقدي سريع',
                'phone'   => $this->customer?->phone,
                'balance' => $this->customer ? (float)$this->customer->balance : 0,
            ],
            'store_id'             => $this->store_id,
            'store_name'           => $this->store?->name ?? 'الفرع الرئيسي',
            'cashier_name'         => $this->user?->name ?? 'الكاشير',
            'invoice_date'         => $this->invoice_date ? $this->invoice_date->toDateString() : $this->created_at?->toDateString(),
            'formatted_created_at' => $this->created_at?->format('Y-m-d H:i'),
            'payment_type'         => $this->payment_type,
            'payment_method'       => $this->payment_method,
            'status'               => $this->status,
            'payment_status'       => $this->payment_status,
            'subtotal'             => (float)$this->subtotal,
            'discount_type'        => $this->discount_type,
            'discount_value'       => (float)$this->discount_value,
            'discount_amount'      => (float)$this->discount_amount,
            'shipping_cost'        => (float)$this->shipping_cost,
            'net_total'            => (float)($this->net_total ?? $this->subtotal),
            'net_amount'           => (float)($this->net_total ?? $this->subtotal),
            'paid_amount'          => (float)$this->paid_amount,
            'remaining_amount'     => (float)$this->remaining_amount,
            'notes'                => $this->notes,
            'items_count'          => $this->relationLoaded('items') ? $this->items->count() : 0,
            'items'                => $this->relationLoaded('items') ? $this->items->map(fn($it) => [
                'id'              => $it->id,
                'item_id'         => $it->item_id,
                'name'            => $it->item?->name ?? 'صنف',
                'item_name'       => $it->item?->name ?? 'صنف',
                'item_code'       => $it->item?->code,
                'unit'            => $it->item?->unit ?? 'قطعة',
                'quantity'        => (float)$it->quantity,
                'unit_price'      => (float)$it->unit_price,
                'discount_amount' => (float)($it->discount_amount ?? 0),
                'total_price'     => (float)$it->total_price,
            ]) : [],
            'payments'             => $this->relationLoaded('payments') ? $this->payments->map(fn($p) => [
                'id'             => $p->id,
                'amount'         => (float)$p->amount,
                'payment_method' => $p->payment_method,
                'payment_date'   => $p->payment_date?->toDateString(),
                'user_name'      => $p->user?->name,
            ]) : [],
        ];
    }
}
