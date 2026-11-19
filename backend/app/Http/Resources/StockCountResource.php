<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StockCount;
use App\Models\StockCountLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SHOP-02: a stocktake session; its lines only when loaded.
 *
 * @mixin StockCount
 */
class StockCountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hideSystem = $this->is_blind && $this->isOpen();
        $showCost = (bool) $request->user()?->can('items.view_cost');

        return [
            'id' => $this->id,
            'count_number' => $this->count_number,
            'status' => $this->status->value,
            'status_label' => __('stock_counts.status.'.$this->status->value),
            'is_open' => $this->isOpen(),
            'is_blind' => $this->is_blind,
            'store_id' => $this->store_id,
            'store_name' => $this->whenLoaded('store', fn () => $this->store?->name),
            'category_id' => $this->category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'notes' => $this->notes,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'posted_by_name' => $this->whenLoaded('poster', fn () => $this->poster?->name),
            'lines_count' => $this->lines_count ?? ($this->relationLoaded('lines') ? $this->lines->count() : null),
            'counted_lines_count' => $this->counted_lines_count
                ?? ($this->relationLoaded('lines') ? $this->lines->whereNotNull('counted_qty')->count() : null),
            'can_view_cost' => $showCost,
            'created_at' => $this->created_at?->toIso8601String(),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines
                ->map(fn (StockCountLine $line) => (new StockCountLineResource($line))->withVisibility($hideSystem, $showCost)->resolve($request))
                ->values()
                ->all()),
        ];
    }
}
