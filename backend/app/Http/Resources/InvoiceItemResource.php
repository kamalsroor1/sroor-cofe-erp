<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InvoiceItem;
use App\Support\Money\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InvoiceItem
 */
final class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'entry_sequence' => $this->entry_sequence !== null ? (int) $this->entry_sequence : null,
            'name' => $this->item->name ?? __('common.unnamed_item'),
            'item_name' => $this->item->name ?? __('common.unnamed_item'),
            'item_code' => $this->item->code ?? null,
            'unit' => $this->item->unit ?? __('common.unit_piece'),
            'quantity' => Decimal::normalize((string) $this->quantity),
            'unit_price' => Decimal::normalize((string) $this->unit_price),
            'discount_amount' => Decimal::normalize((string) ($this->discount_amount ?? 0)),
            'total_price' => Decimal::normalize((string) $this->total_price),
        ];
    }
}
