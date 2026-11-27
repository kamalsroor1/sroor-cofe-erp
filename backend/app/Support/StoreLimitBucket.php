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
    /** @var list<string> */
    public const WAREHOUSE_TYPES = ['main_warehouse', 'warehouse'];

    /** @var list<string> */
    public const VAN_TYPES = ['wholesale_van', 'van'];

    public static function resourceFor(string $type): LimitResource
    {
        $normalized = strtolower(trim($type));

        return match (true) {
            in_array($normalized, self::WAREHOUSE_TYPES, true) => LimitResource::Warehouses,
            in_array($normalized, self::VAN_TYPES, true) => LimitResource::Vans,
            default => LimitResource::Stores,
        };
    }

    public static function count(LimitResource $resource): int
    {
        $query = Store::query();

        match ($resource) {
            LimitResource::Warehouses => $query->whereIn('type', self::WAREHOUSE_TYPES),
            LimitResource::Vans => $query->whereIn('type', self::VAN_TYPES),
            default => $query->where(fn ($q) => $q->whereNotIn('type', array_merge(self::WAREHOUSE_TYPES, self::VAN_TYPES))->orWhereNull('type')),
        };

        return $query->count();
    }
}
