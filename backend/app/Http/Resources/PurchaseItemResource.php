<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'item_id'           => $this->item_id,
            'item_name'         => $this->item?->name,
            'item_code'         => $this->item?->code,
            'unit'              => $this->item?->unit ?? 'كجم',
            'quantity'          => (float)$this->quantity,
            'base_cost_price'   => (float)($this->base_cost_price ?? $this->cost_price),
            'allocated_expense' => (float)($this->allocated_expense ?? 0),
            'cost_price'        => (float)$this->cost_price,
            'total_price'       => (float)($this->total_price ?? ($this->quantity * $this->cost_price)),
        ];
    }
}
