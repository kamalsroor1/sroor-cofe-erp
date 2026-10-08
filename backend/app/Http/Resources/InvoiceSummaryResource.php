<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'customer_name' => $this->customer?->name ?? 'عميل نقدي',
            'customer_phone' => $this->customer?->phone,
            'store_name' => $this->store?->name ?? 'الفرع الرئيسي',
            'store_id' => $this->store_id,
            'payment_type' => $this->payment_type,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'discount_amount' => (float) $this->discount_amount,
            'net_total' => (float) ($this->net_total ?? $this->total_amount),
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'created_at' => $this->created_at?->format('H:i'),
            'formatted_created_at' => $this->created_at?->format('Y-m-d H:i'),
            'invoice_date' => $this->invoice_date?->toDateString(),
            'is_cancelled' => $this->status === 'cancelled',
        ];
    }
}
