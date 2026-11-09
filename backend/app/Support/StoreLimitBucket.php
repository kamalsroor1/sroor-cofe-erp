<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\LimitResource;
use App\Models\Store;

/**
 * Which plan limit a `stores.type` consumes (SEC-06 / BE-12): `main_warehouse` counts
 * against warehouses, `wholesale_van` against vans, every other type (retail_shop and any
 * free-text legacy value) against stores/branches. Soft-deleted stores do not count;
 * inactive ones do (deactivating must not free a slot for a bypass).
 */
final class StoreLimitBucket
{
    private const WAREHOUSE_TYPE = 'main_warehouse';

    private const VAN_TYPE = 'wholesale_van';

    public static function resourceFor(string $type): LimitResource
    {
        return match ($type) {
            self::WAREHOUSE_TYPE => LimitResource::Warehouses,
            self::VAN_TYPE => LimitResource::Vans,
            default => LimitResource::Stores,
        };
    }

    public static function count(LimitResource $resource): int
    {
        $query = Store::query();

        match ($resource) {
            LimitResource::Warehouses => $query->where('type', self::WAREHOUSE_TYPE),
            LimitResource::Vans => $query->where('type', self::VAN_TYPE),
            default => $query->where(fn ($q) => $q->whereNotIn('type', [self::WAREHOUSE_TYPE, self::VAN_TYPE])->orWhereNull('type')),
        };

        return $query->count();
    }
}
