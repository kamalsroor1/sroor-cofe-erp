<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'transfer_number' => $this->transfer_number,
            'from_store_id'   => $this->from_store_id,
            'from_store_name' => $this->fromStore?->name ?? 'الفرع المصدر',
            'to_store_id'     => $this->to_store_id,
            'to_store_name'   => $this->toStore?->name ?? 'الفرع المستلم',
            'user_id'         => $this->user_id,
            'user_name'       => $this->user?->name ?? 'المسؤول',
            'transfer_date'   => $this->transfer_date ? $this->transfer_date->toDateString() : $this->created_at?->toDateString(),
            'status'          => $this->status,
            'is_cancelled'    => $this->status === 'cancelled',
            'notes'           => $this->notes,
            'items_count'     => $this->relationLoaded('items') ? $this->items->count() : 0,
            'items'           => $this->relationLoaded('items') ? StockTransferItemResource::collection($this->items)->resolve() : [],
            'created_at'      => $this->created_at?->toDateTimeString(),
        ];
    }
}
