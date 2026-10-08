<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ReturnItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReturnItem
 */
class ReturnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'item_name' => $this->item?->name ?? 'صنف',
            'item_code' => $this->item?->code,
            'unit' => $this->item?->unit ?? 'كجم',
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'total_price' => (float) $this->total_price,
        ];
    }
}
