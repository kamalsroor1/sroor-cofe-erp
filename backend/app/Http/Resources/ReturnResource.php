<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ReturnDocument;
use App\Support\Money\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReturnDocument
 */
class ReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'return_type' => $this->return_type,
            'party_name' => $this->return_type === 'sales_return'
                ? ($this->customer?->name ?: 'عميل نقدي سريع')
                : ($this->supplier?->name ?: 'مورد عام'),
            'party_phone' => $this->return_type === 'sales_return' ? $this->customer?->phone : $this->supplier?->phone,
            'customer_id' => $this->customer_id,
            'supplier_id' => $this->supplier_id,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name ?? 'الفرع الرئيسي',
            'user_name' => $this->user?->name ?? 'المسؤول',
            'total_amount' => (float) $this->total_amount,
            // BE-10: null for purchase returns; legacy sales returns read as 'balance'.
            'refund_method' => $this->return_type === 'sales_return'
                ? ($this->refund_method ?? ReturnDocument::REFUND_BALANCE)
                : null,
            'refund_amount' => $this->refundAmount(),
            'return_date' => $this->return_date ? $this->return_date->toDateString() : $this->created_at?->toDateString(),
            'reason' => $this->reason,
            'items_count' => $this->relationLoaded('items') ? $this->items->count() : 0,
            'items' => $this->relationLoaded('items') ? ReturnItemResource::collection($this->items)->resolve() : [],
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    /** Cash handed back through the return's refund voucher, as a positive scale-3 string. */
    private function refundAmount(): string
    {
        // The list/show queries preload this with withSum(); otherwise one query for this row.
        $paid = (string) ($this->resource->getAttribute('refund_payments_sum_amount') ?? $this->refundPayments()->sum('amount'));

        return bcsub('0.000', Decimal::normalize($paid), 3);
    }
}
