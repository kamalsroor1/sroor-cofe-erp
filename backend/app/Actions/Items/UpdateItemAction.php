<?php

declare(strict_types=1);

namespace App\Actions\Items;

use App\DTOs\Items\ItemDTO;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

final class UpdateItemAction
{
    /**
     * Update item inside DB transaction
     */
    public function execute(Item $item, ItemDTO $dto): Item
    {
        return DB::transaction(function () use ($item, $dto) {
            $item->update([
                'name'            => $dto->name,
                'code'            => $dto->code ?: $item->code,
                'category'        => $dto->category,
                'unit'            => $dto->unit,
                'cost_price'      => $dto->cost_price,
                'selling_price'   => $dto->selling_price,
                'min_stock_level' => $dto->min_stock_level,
                'is_active'       => $dto->is_active,
                'notes'           => $dto->notes,
            ]);

            return $item->fresh(['storeStocks.store']);
        });
    }
}
