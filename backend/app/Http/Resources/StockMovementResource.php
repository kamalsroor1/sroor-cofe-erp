<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'item_id'              => $this->item_id,
            'item_name'            => $this->item?->name,
            'store_id'             => $this->store_id,
            'store_name'           => $this->store?->name,
            'movement_type'        => $this->movement_type,
            'movement_type_label'  => method_exists($this->resource, 'getMovementTypeLabelAttribute') ? $this->movement_type_label : $this->movement_type,
            'quantity'             => (float)$this->quantity,
            'stock_before'         => (float)$this->stock_before,
            'stock_after'          => (float)$this->stock_after,
            'unit_cost'            => (float)$this->unit_cost,
            'document_number'      => $this->document_number,
            'user_name'            => $this->user?->name ?: 'النظام',
            'notes'                => $this->notes,
            'created_at'           => $this->created_at?->toDateTimeString(),
        ];
    }
}
