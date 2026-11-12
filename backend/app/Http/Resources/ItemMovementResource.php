<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SEC-21 (6): one row of GET /items/{id}/movements. Before, the raw StockMovement model was
 * serialized with its full `user` (e-mail, phone, last_login_at, theme_preference…) and `store`
 * relations, for cashiers too. Now: the movement fields the ledger screen uses, plus the id and
 * name of the user and of the store. `unit_cost` only when the caller may see costs
 * (SEC-1 M6: `items.view_cost`). Quantities keep the decimal:3 string form of the model cast.
 *
 * @mixin StockMovement
 */
final class ItemMovementResource extends JsonResource
{
    public function __construct(StockMovement $resource, private readonly bool $includeCost = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'store_id' => $this->store_id,
            'user_id' => $this->user_id,
            'movement_type' => $this->movement_type,
            'quantity' => $this->quantity,
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'unit_cost' => $this->when($this->includeCost, fn () => $this->unit_cost),
            'document_number' => $this->document_number,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
            'user' => $this->user !== null ? ['id' => $this->user->id, 'name' => $this->user->name] : null,
            'store' => $this->store !== null ? ['id' => $this->store->id, 'name' => $this->store->name] : null,
        ];
    }
}
