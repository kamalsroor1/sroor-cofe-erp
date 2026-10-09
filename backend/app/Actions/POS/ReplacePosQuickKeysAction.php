<?php

declare(strict_types=1);

namespace App\Actions\POS;

use App\DTOs\Pos\QuickKeyDTO;
use App\Models\PosQuickKey;
use App\Models\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * POSB-6: full replace of one store's quick-key layout. Delete + insert run in one
 * transaction under a lock on the store row, so two concurrent saves serialize and the
 * unique (store_id, page, position) index never sees a half-replaced layout.
 */
final class ReplacePosQuickKeysAction
{
    public function __construct(
        private readonly GetPosQuickKeysAction $getPosQuickKeysAction,
    ) {}

    /**
     * @param  list<QuickKeyDTO>  $keys
     * @return Collection<int, PosQuickKey>
     */
    public function execute(Store $store, array $keys): Collection
    {
        $storeId = (int) $store->getKey();

        DB::transaction(function () use ($storeId, $keys): void {
            Store::query()->whereKey($storeId)->lockForUpdate()->firstOrFail();

            PosQuickKey::query()->where('store_id', $storeId)->delete();

            if ($keys === []) {
                return;
            }

            $now = now();
            $rows = array_map(static fn (QuickKeyDTO $key): array => [
                ...$key->toArray(),
                'store_id' => $storeId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $keys);

            // 300 rows max (request limit); chunked to stay far below driver placeholder limits.
            foreach (array_chunk($rows, 100) as $chunk) {
                PosQuickKey::query()->insert($chunk);
            }
        });

        return $this->getPosQuickKeysAction->execute($storeId);
    }
}
