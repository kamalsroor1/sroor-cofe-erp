<?php

declare(strict_types=1);

namespace App\Actions\Items;

use App\DTOs\Items\ItemDTO;
use App\Enums\LimitResource;
use App\Models\Item;
use App\Services\Entitlements\PlanEnforcer;
use App\Services\StoreStockInitializer;
use Illuminate\Support\Facades\DB;

final class CreateItemAction
{
    public function __construct(
        private readonly StoreStockInitializer $stockInitializer,
        private readonly PlanEnforcer $planEnforcer,
    ) {}

    /**
     * Create item inside DB transaction and initialize store stocks
     */
    public function execute(ItemDTO $dto): Item
    {
        return DB::transaction(function () use ($dto) {
            // SEC-06 / BE-12: plan items limit, counted under the tenant lock.
            $this->planEnforcer->assertWithinLimit(LimitResource::Items, fn (): int => Item::query()->count());

            $code = $dto->code;
            if (! $code) {
                $count = Item::count() + 1;
                $code = 'ITM-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
            }

            $item = Item::create([
                'name' => $dto->name,
                'code' => $code,
                'category' => $dto->category,
                'category_id' => $dto->category_id,
                'unit' => $dto->unit,
                'cost_price' => $dto->cost_price,
                // '0.000' = no price floor; never default to cost (cost leak).
                'min_selling_price' => $dto->min_selling_price ?? '0.000',
                'weighted_avg_cost' => $dto->cost_price,
                'selling_price' => $dto->selling_price,
                'min_stock_level' => $dto->min_stock_level,
                'current_stock' => '0.000',
                'is_active' => $dto->is_active,
                'notes' => $dto->notes,
                'is_weighted' => $dto->is_weighted ?? false,
            ]);

            // Auto-initialize StoreStock for every existing store (bulk, idempotent)
            $this->stockInitializer->seedItem((int) $item->id, (string) $item->min_stock_level);

            return $item->fresh(['storeStocks.store']);
        });
    }
}
