<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StockTransferItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockTransferItem
 */
class StockTransferItemResource extends JsonResource
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
        ];
    }
}
