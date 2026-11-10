<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InvoiceItem;
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
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'discount_amount' => (float) ($this->discount_amount ?? 0),
            'total_price' => (float) $this->total_price,
        ];
    }
}
