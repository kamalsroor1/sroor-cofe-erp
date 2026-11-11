<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PurchaseItem;
use App\Support\Money\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseItem
 */
class PurchaseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'item_name' => $this->item?->name,
            'item_code' => $this->item?->code,
            'unit' => $this->item?->unit ?? 'كجم',
            'quantity' => Decimal::normalize((string) $this->quantity),
            'base_cost_price' => Decimal::normalize((string) ($this->base_cost_price ?? $this->cost_price)),
            'allocated_expense' => Decimal::normalize((string) ($this->allocated_expense ?? 0)),
            'cost_price' => Decimal::normalize((string) $this->cost_price),
            'total_price' => Decimal::normalize((string) ($this->total_price ?? bcmul((string) $this->quantity, (string) $this->cost_price, 3))),
        ];
    }
}
