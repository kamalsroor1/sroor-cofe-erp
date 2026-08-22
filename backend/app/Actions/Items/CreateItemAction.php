<?php

declare(strict_types=1);

namespace App\Actions\Items;

use App\DTOs\Items\ItemDTO;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use Illuminate\Support\Facades\DB;

final class CreateItemAction
{
    /**
     * Create item inside DB transaction and initialize store stocks
     */
    public function execute(ItemDTO $dto): Item
    {
        return DB::transaction(function () use ($dto) {
            $code = $dto->code;
            if (!$code) {
                $count = Item::count() + 1;
                $code = 'ITM-' . str_pad((string)$count, 4, '0', STR_PAD_LEFT);
            }

            $item = Item::create([
                'name'              => $dto->name,
                'code'              => $code,
                'category'          => $dto->category,
                'unit'              => $dto->unit,
                'cost_price'        => $dto->cost_price,
                'weighted_avg_cost' => $dto->cost_price,
                'selling_price'     => $dto->selling_price,
                'min_stock_level'   => $dto->min_stock_level,
                'current_stock'     => '0.000',
                'is_active'         => $dto->is_active,
                'notes'             => $dto->notes,
            ]);

            // Auto-initialize StoreStock for existing stores
            $stores = Store::all();
            foreach ($stores as $store) {
                StoreStock::firstOrCreate(
                    ['store_id' => $store->id, 'item_id' => $item->id],
                    ['quantity' => '0.000']
                );
            }

            return $item->fresh(['storeStocks.store']);
        });
    }
}
