<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ReturnItem;
use App\Support\Money\Decimal;
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
            'quantity' => Decimal::normalize((string) $this->quantity),
            'unit_price' => Decimal::normalize((string) $this->unit_price),
            'total_price' => Decimal::normalize((string) $this->total_price),
        ];
    }
}
