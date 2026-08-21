<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'purchase_number'           => $this->purchase_number,
            'supplier_id'               => $this->supplier_id,
            'supplier_name'             => $this->supplier?->name ?: 'مورد عام',
            'supplier_company'          => $this->supplier?->company_name,
            'supplier_phone'            => $this->supplier?->phone,
            'store_id'                  => $this->store_id,
            'store_name'                => $this->store?->name,
            'user_name'                 => $this->user?->name,
            'purchase_date'             => $this->purchase_date ? $this->purchase_date->toDateString() : $this->created_at?->toDateString(),
            'status'                    => $this->status,
            'payment_status'            => $this->payment_status,
            'subtotal'                  => (float)$this->subtotal,
            'discount_amount'           => (float)$this->discount_amount,
            'additional_expenses_total' => (float)$this->additional_expenses_total,
            'net_total'                 => (float)$this->net_total,
            'paid_amount'               => (float)$this->paid_amount,
            'remaining_amount'          => (float)$this->remaining_amount,
            'supplier_invoice_ref'      => $this->supplier_invoice_ref,
            'notes'                     => $this->notes,
            'items_count'               => $this->relationLoaded('items') ? $this->items->count() : 0,
            'items'                     => $this->relationLoaded('items') ? PurchaseItemResource::collection($this->items)->resolve() : [],
            'created_at'                => $this->created_at?->toDateTimeString(),
        ];
    }
}
