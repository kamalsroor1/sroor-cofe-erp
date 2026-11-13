<?php

declare(strict_types=1);

namespace App\DTOs\Logs;

use App\Models\User;
use App\Support\ActiveStore;

/**
 * LOG-01: which branches' activity logs a reader may see.
 *
 *  - $readableStoreIds null  → every branch plus the shop-wide logs without a store
 *    (admin role, `stores.manage`, `stores.view_all`: ActiveStore::canViewAll);
 *  - $readableStoreIds list  → only those branches; shop-wide logs (store_id NULL) are hidden;
 *  - $storeId                → the explicit `?store_id=` filter, already access-checked (403 otherwise).
 */
final readonly class ActivityLogStoreScope
{
    /**
     * @param  list<int>|null  $readableStoreIds
     */
    public function __construct(
        public ?array $readableStoreIds,
        public ?int $storeId = null,
    ) {}

    public static function for(User $user): self
    {
        return new self(ActiveStore::readableStoreIds($user));
    }

    public function seesEveryStore(): bool
    {
        return $this->readableStoreIds === null;
    }
}
