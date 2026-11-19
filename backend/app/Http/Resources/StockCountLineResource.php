<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StockCountLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SHOP-02: one line of a session. A blind session hides the snapshot quantity and the difference
 * until it is posted; the unit cost and values need `items.view_cost`.
 *
 * @mixin StockCountLine
 */
class StockCountLineResource extends JsonResource
{
    public bool $hideSystem = false;

    public bool $showCost = false;

    public function withVisibility(bool $hideSystem, bool $showCost): self
    {
        $this->hideSystem = $hideSystem;
        $this->showCost = $showCost;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $counted = $this->counted_qty === null ? null : (string) $this->counted_qty;
        $difference = $this->difference !== null
            ? (string) $this->difference
            : ($counted === null ? null : bcsub($counted, (string) $this->system_qty, 3));

        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'item_name' => $this->item?->name,
            'item_code' => $this->item?->code,
            'unit' => $this->item?->unit,
            'system_qty' => $this->hideSystem ? null : (string) $this->system_qty,
            'system_qty_at_post' => $this->system_qty_at_post === null ? null : (string) $this->system_qty_at_post,
            'counted_qty' => $counted,
            'difference' => $this->hideSystem ? null : $difference,
            'unit_cost' => $this->showCost ? (string) $this->unit_cost : null,
            'difference_value' => $this->showCost && ! $this->hideSystem && $difference !== null
                ? bcmul($difference, (string) $this->unit_cost, 3)
                : null,
            'counted_at' => $this->counted_at?->toIso8601String(),
        ];
    }
}
