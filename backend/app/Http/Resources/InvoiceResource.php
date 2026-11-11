<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Support\Money\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'client_uuid' => $this->client_uuid,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name ?? 'عميل نقدي سريع',
            'customer_phone' => $this->customer?->phone,
            'customer_balance' => $this->customer ? Decimal::normalize((string) $this->customer->current_balance) : '0.000',
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->customer?->name ?? 'عميل نقدي سريع',
                'phone' => $this->customer?->phone,
                'balance' => $this->customer ? Decimal::normalize((string) $this->customer->current_balance) : '0.000',
            ],
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name ?? 'الفرع الرئيسي',
            'cashier_name' => $this->user?->name ?? 'الكاشير',
            'invoice_date' => $this->invoice_date ? $this->invoice_date->toDateString() : $this->created_at?->toDateString(),
            'formatted_created_at' => $this->created_at?->format('Y-m-d H:i'),
            'payment_type' => $this->payment_type,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'subtotal' => Decimal::normalize((string) $this->subtotal),
            'discount_type' => $this->discount_type,
            'discount_value' => Decimal::normalize((string) $this->discount_value),
            'discount_amount' => Decimal::normalize((string) $this->discount_amount),
            'shipping_cost' => Decimal::normalize((string) $this->shipping_cost),
            'net_total' => Decimal::normalize((string) ($this->net_total ?? $this->subtotal)),
            'net_amount' => Decimal::normalize((string) ($this->net_total ?? $this->subtotal)),
            'paid_amount' => Decimal::normalize((string) $this->paid_amount),
            'remaining_amount' => Decimal::normalize((string) $this->remaining_amount),
            'change_amount' => (string) ($this->change_amount ?? '0.000'),
            'notes' => $this->notes,
            'items_count' => $this->relationLoaded('items') ? $this->items->count() : 0,
            'items' => $this->relationLoaded('items')
                ? InvoiceItemResource::collection($this->items)->resolve($request)
                : [],
            'payments' => $this->relationLoaded('payments') ? $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'amount' => Decimal::normalize((string) $p->amount),
                'payment_method' => $p->payment_method,
                'payment_date' => $p->payment_date?->toDateString(),
                'user_name' => $p->user?->name,
            ]) : [],
        ];
    }
}
