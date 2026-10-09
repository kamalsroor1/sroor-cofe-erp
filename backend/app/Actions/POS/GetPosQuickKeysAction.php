<?php

declare(strict_types=1);

namespace App\Actions\POS;

use App\Enums\PosQuickKeyType;
use App\Models\PosQuickKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * POSB-6: the quick keys of one store, ordered by page then position. Always three
 * queries (keys + items + categories), whatever the number of keys. Keys whose item or
 * category is inactive or soft-deleted are dropped silently (no error, no hole-filling).
 */
final class GetPosQuickKeysAction
{
    /**
     * @return Collection<int, PosQuickKey>
     */
    public function execute(int $storeId): Collection
    {
        $keys = PosQuickKey::query()
            ->select(['id', 'store_id', 'page', 'position', 'type', 'item_id', 'category_id', 'label', 'color'])
            ->where('store_id', $storeId)
            ->with([
                // SoftDeletes scopes of Item/Category apply here too: deleted rows never load.
                'item' => static fn (BelongsTo $query) => $query
                    ->select(['id', 'name', 'selling_price', 'unit', 'is_weighted', 'image'])
                    ->where('is_active', true),
                'category' => static fn (BelongsTo $query) => $query
                    ->select(['id', 'name', 'icon', 'color'])
                    ->where('is_active', true),
            ])
            ->orderBy('page')
            ->orderBy('position')
            ->get();

        return $keys
            ->filter(static fn (PosQuickKey $key): bool => $key->type === PosQuickKeyType::Item
                ? $key->item !== null
                : $key->category !== null)
            ->values();
    }
}
